<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\SMSSettings;
use App\Models\SMSActivity;
use App\Services\BulkSMSService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomersController extends Controller
{
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $customers = Customer::where('company_id', $currentCompany->id)
            ->when($request->string('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
            'currentCompany' => $currentCompany,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('customers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email,NULL,id,company_id,' . $currentCompany->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        Customer::create($validated);

        return redirect()->route('customers.index')->with('success', 'Customer created');
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('customers/Edit', [
            'customer' => $customer,
        ]);
    }

    public function show(Customer $customer, Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the customer belongs to the current company
        if ($customer->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to customer.');
        }

        // Load contacts with pagination
        $contactsPerPage = $request->get('contacts_per_page', 5);
        $contacts = $customer->contacts()
            ->when($request->filled('contact_search'), function ($query) use ($request) {
                $search = $request->get('contact_search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->orderBy('is_primary', 'desc')
            ->orderBy('name', 'asc')
            ->paginate($contactsPerPage, ['*'], 'contacts_page');

        // Load SMS activities with pagination
        $smsPerPage = $request->get('sms_per_page', 5);
        $smsActivities = $customer->smsActivities()
            ->with('user')
            ->when($request->filled('sms_search'), function ($query) use ($request) {
                $search = $request->get('sms_search');
                $query->where(function ($q) use ($search) {
                    $q->where('message', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('sms_status'), function ($query) use ($request) {
                $query->where('status', $request->get('sms_status'));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($smsPerPage, ['*'], 'sms_page');

        return Inertia::render('customers/Show', [
            'customer' => $customer,
            'contacts' => $contacts,
            'smsActivities' => $smsActivities,
            'filters' => [
                'contact_search' => $request->get('contact_search'),
                'contacts_per_page' => $contactsPerPage,
                'sms_search' => $request->get('sms_search'),
                'sms_status' => $request->get('sms_status'),
                'sms_per_page' => $smsPerPage,
            ],
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email,' . $customer->id . ',id,company_id,' . $currentCompany->id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();
        return redirect()->route('customers.index')->with('success', 'Customer deleted');
    }

    /**
     * Send SMS to a customer
     */
    public function sendSMS(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:160'],
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the customer belongs to the current company
        if ($customer->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to customer.');
        }

        // Check if customer has a phone number
        if (!$customer->phone) {
            return redirect()->back()
                ->withErrors(['message' => 'Customer does not have a phone number.']);
        }

        // Get system SMS settings
        $smsSettings = SMSSettings::getActive();
        if (!$smsSettings || !$smsSettings->is_active) {
            return redirect()->back()
                ->withErrors(['message' => 'SMS functionality is not configured or disabled. Please contact your administrator.']);
        }

        // Create SMS activity record
        $smsActivity = SMSActivity::create([
            'customer_id' => $customer->id,
            'user_id' => auth()->id(),
            'company_id' => $currentCompany->id,
            'phone_number' => $customer->phone,
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
            $result = $smsService->sendSMS($customer->phone, $validated['message']);

            if ($result['success']) {
                // Update activity as successful
                $smsActivity->update([
                    'status' => 'sent',
                    'bulksms_reference' => $result['reference'] ?? null,
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->with('success', 'SMS sent successfully to ' . $customer->name);
            } else {
                // Update activity as failed
                $smsActivity->update([
                    'status' => 'failed',
                    'error_message' => $result['message'],
                    'bulksms_response' => $result,
                ]);

                return redirect()->back()
                    ->withErrors(['message' => $result['message']]);
            }

        } catch (\Exception $e) {
            // Update activity as failed
            $smsActivity->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->back()
                ->withErrors(['message' => 'Failed to send SMS: ' . $e->getMessage()]);
        }
    }
}


