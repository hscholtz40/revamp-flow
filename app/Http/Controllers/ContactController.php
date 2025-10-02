<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\SMSSettings;
use App\Models\SMSActivity;
use App\Services\BulkSMSService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $contacts = Contact::with('customer')
            ->where('company_id', $currentCompany->id)
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get();
        $selectedCustomerId = $request->get('customer_id');

        return Inertia::render('contacts/Create', [
            'customers' => $customers,
            'selectedCustomerId' => $selectedCustomerId,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['boolean'],
        ]);

        // If this contact is marked as primary, unset other primary contacts for this customer
        if ($validated['is_primary'] ?? false) {
            Contact::where('customer_id', $validated['customer_id'])
                ->where('company_id', $currentCompany->id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $validated['company_id'] = $currentCompany->id;
        Contact::create($validated);

        return redirect()->route('customers.show', $validated['customer_id'])
            ->with('success', 'Contact created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact): Response
    {
        $contact->load('customer');

        return Inertia::render('contacts/Show', [
            'contact' => $contact,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact): Response
    {
        $contact->load('customer');
        $customers = Customer::orderBy('name')->get();

        return Inertia::render('contacts/Edit', [
            'contact' => $contact,
            'customers' => $customers,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['boolean'],
        ]);

        // If this contact is marked as primary, unset other primary contacts for this customer
        if ($validated['is_primary'] ?? false) {
            Contact::where('customer_id', $validated['customer_id'])
                ->where('is_primary', true)
                ->where('id', '!=', $contact->id)
                ->update(['is_primary' => false]);
        }

        $contact->update($validated);

        return redirect()->route('customers.show', $validated['customer_id'])
            ->with('success', 'Contact updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        $customerId = $contact->customer_id;
        $contact->delete();

        return redirect()->route('customers.show', $customerId)
            ->with('success', 'Contact deleted successfully');
    }

    /**
     * Send SMS to a contact
     */
    public function sendSMS(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:160'],
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the contact belongs to the current company
        if ($contact->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to contact.');
        }

        // Check if contact has a phone number
        if (!$contact->phone) {
            return redirect()->back()
                ->withErrors(['message' => 'Contact does not have a phone number.']);
        }

        // Get system SMS settings
        $smsSettings = SMSSettings::getActive();
        if (!$smsSettings || !$smsSettings->is_active) {
            return redirect()->back()
                ->withErrors(['message' => 'SMS functionality is not configured or disabled. Please contact your administrator.']);
        }

        // Create SMS activity record
        $smsActivity = SMSActivity::create([
            'customer_id' => $contact->customer_id,
            'user_id' => auth()->id(),
            'company_id' => $currentCompany->id,
            'phone_number' => $contact->phone,
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        try {
            // Initialize SMS service
            $smsService = new BulkSMSService(
                $smsSettings->bulksms_username,
                $smsSettings->bulksms_password,
                $smsSettings->bulksms_sender_name
            );

            // Send SMS
            $result = $smsService->sendSMS($contact->phone, $validated['message']);

            if ($result['success']) {
                // Update activity as successful
                $smsActivity->update([
                    'status' => 'sent',
                    'bulksms_reference' => $result['reference'] ?? null,
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->with('success', 'SMS sent successfully to ' . $contact->name);
            } else {
                // Update activity as failed
                $smsActivity->update([
                    'status' => 'failed',
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->withErrors(['message' => 'Failed to send SMS: ' . ($result['message'] ?? 'Unknown error')]);
            }
        } catch (\Exception $e) {
            // Update activity as failed
            $smsActivity->update([
                'status' => 'failed',
                'bulksms_response' => ['error' => $e->getMessage()],
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Failed to send SMS: ' . $e->getMessage()]);
        }
    }
}
