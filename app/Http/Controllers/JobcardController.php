<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;

class JobcardController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $query = Jobcard::with(['customer'])
            ->where('company_id', $currentCompany->id);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('job_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $jobcards = $query->orderByDesc('created_at')->paginate(15);
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('jobcards/Index', [
            'jobcards' => $jobcards,
            'customers' => $customers,
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'search' => $request->input('search', ''),
            ],
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type']);

        return Inertia::render('jobcards/Create', [
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_jobcard_terms,
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['company_id'] = $currentCompany->id;
        $validated['job_number'] = Jobcard::generateJobNumber();
        $validated['tax_rate'] = $validated['tax_rate'] ?? 15;

        $jobcard = Jobcard::create($validated);

        // Create line items
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new JobcardLineItem([
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'sort_order' => $index,
            ]);
            $lineItem->calculateTotal();
            $jobcard->lineItems()->save($lineItem);
        }

        // Calculate totals
        $jobcard->calculateTotals();

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Jobcard $jobcard): Response
    {
        $jobcard->load(['customer', 'lineItems', 'invoice']);

        return Inertia::render('jobcards/Show', [
            'jobcard' => $jobcard,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jobcard $jobcard): Response
    {
        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && !auth()->user()->canEditCompletedJobcards()) {
            return redirect()->route('jobcards.show', $jobcard)
                ->with('error', 'You do not have permission to edit completed jobcards.');
        }

        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type']);
        $jobcard->load(['lineItems']);

        return Inertia::render('jobcards/Edit', [
            'jobcard' => $jobcard,
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.id' => ['nullable', 'exists:jobcard_line_items,id'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;

        $jobcard->update($validated);

        // Update line items
        $existingLineItemIds = [];
        foreach ($validated['line_items'] as $index => $lineItemData) {
            if (isset($lineItemData['id'])) {
                // Update existing line item
                $lineItem = JobcardLineItem::find($lineItemData['id']);
                $lineItem->update([
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'sort_order' => $index,
                ]);
                $lineItem->calculateTotal();
                $lineItem->save();
                $existingLineItemIds[] = $lineItem->id;
            } else {
                // Create new line item
                $lineItem = new JobcardLineItem([
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'sort_order' => $index,
                ]);
                $lineItem->calculateTotal();
                $jobcard->lineItems()->save($lineItem);
                $existingLineItemIds[] = $lineItem->id;
            }
        }

        // Delete removed line items
        $jobcard->lineItems()->whereNotIn('id', $existingLineItemIds)->delete();

        // Calculate totals
        $jobcard->calculateTotals();

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard updated successfully');
    }

    /**
     * Update the status of the specified jobcard.
     */
    public function updateStatus(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:draft,pending,in_progress,completed,cancelled',
        ]);

        $newStatus = $request->status;
        
        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && $newStatus !== 'completed') {
            if (!auth()->user()->canEditCompletedJobcards()) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to edit completed jobcards.');
            }
        }

        $jobcard->status = $newStatus;
        
        // Set completed_date if status is completed
        if ($newStatus === 'completed' && !$jobcard->completed_date) {
            $jobcard->completed_date = now();
        }
        
        $jobcard->save();

        return redirect()->back()
            ->with('success', "Jobcard status updated to {$newStatus}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jobcard $jobcard): RedirectResponse
    {
        // Check if user can delete completed jobcards
        if ($jobcard->status === 'completed' && !auth()->user()->canEditCompletedJobcards()) {
            return redirect()->back()
                ->with('error', 'You do not have permission to delete completed jobcards.');
        }

        $jobcard->delete();

        return redirect()->route('jobcards.index')
            ->with('success', 'Jobcard deleted successfully');
    }

    /**
     * Generate and download a PDF version of the jobcard.
     */
    public function print(Jobcard $jobcard)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to jobcard.');
        }

        $jobcard->load(['customer', 'lineItems.product', 'company']);

        $pdf = Pdf::loadView('pdf.jobcard', [
            'jobcard' => $jobcard,
            'company' => $currentCompany,
        ]);

        $filename = 'jobcard-' . $jobcard->job_number . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Send jobcard via email.
     */
    public function email(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            abort(403, 'Unauthorized access to jobcard.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $user = auth()->user();
        
        // Check if user has SMTP settings configured
        if (!$user->smtp_host || !$user->smtp_username || !$user->smtp_password) {
            return redirect()->back()
                ->withErrors(['message' => 'Please configure your SMTP settings in your user profile to send emails.']);
        }

        try {
            // Configure mail using user's SMTP settings
            Config::set([
                'mail.mailers.smtp.host' => $user->smtp_host,
                'mail.mailers.smtp.port' => $user->smtp_port ?? 587,
                'mail.mailers.smtp.username' => $user->smtp_username,
                'mail.mailers.smtp.password' => $user->smtp_password,
                'mail.mailers.smtp.encryption' => $user->smtp_encryption ?? 'tls',
            ]);

            // Log the SMTP configuration for debugging
            \Log::info('Email Configuration', [
                'host' => $user->smtp_host,
                'port' => $user->smtp_port ?? 587,
                'username' => $user->smtp_username,
                'encryption' => $user->smtp_encryption ?? 'tls',
                'from_email' => $user->smtp_from_email ?? $user->email,
                'from_name' => $user->smtp_from_name ?? $user->name,
                'to_email' => $validated['email'],
            ]);

            $jobcard->load(['customer', 'lineItems.product', 'company']);

            $subject = $validated['subject'] ?? "Jobcard #{$jobcard->job_number} - {$jobcard->title}";
            $fromEmail = $user->smtp_from_email ?? $user->email;
            $fromName = $user->smtp_from_name ?? $user->name;

            // Generate PDF
            $pdf = Pdf::loadView('pdf.jobcard', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ]);

            $filename = 'jobcard-' . $jobcard->job_number . '.pdf';

            Mail::mailer('smtp')->send('emails.jobcard-pdf', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ], function ($message) use ($validated, $subject, $fromEmail, $fromName, $pdf, $filename) {
                $message->to($validated['email'])
                    ->subject($subject)
                    ->from($fromEmail, $fromName)
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);
            });

            \Log::info('Email sent successfully', [
                'to' => $validated['email'],
                'subject' => $subject,
                'from' => $fromEmail,
            ]);

            return redirect()->back()
                ->with('success', 'Jobcard sent successfully to ' . $validated['email']);

        } catch (\Exception $e) {
            \Log::error('Email sending failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'smtp_host' => $user->smtp_host,
                'smtp_port' => $user->smtp_port,
                'smtp_username' => $user->smtp_username,
                'to_email' => $validated['email'],
            ]);
            
            return redirect()->back()
                ->withErrors(['message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }

    /**
     * Convert jobcard to invoice
     */
    public function convertToInvoice(Jobcard $jobcard): RedirectResponse
    {
        // Log the start of conversion
        \Log::info('Jobcard conversion started', [
            'jobcard_id' => $jobcard->id,
            'current_invoice_id' => $jobcard->invoice_id,
            'user_id' => auth()->id(),
            'request_method' => request()->method(),
            'request_url' => request()->url()
        ]);
        
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Ensure the jobcard belongs to the current company
        if ($jobcard->company_id !== $currentCompany->id) {
            \Log::error('Jobcard conversion failed - company mismatch', [
                'jobcard_id' => $jobcard->id,
                'jobcard_company_id' => $jobcard->company_id,
                'current_company_id' => $currentCompany->id
            ]);
            abort(403, 'Unauthorized access to jobcard.');
        }

        try {
            $invoice = $jobcard->convertToInvoice();
            
            // Update jobcard to link to invoice
            $updated = $jobcard->update(['invoice_id' => $invoice->id]);
            
            // Refresh the jobcard to get updated data
            $jobcard->refresh();
            
            // Log for debugging
            \Log::info('Jobcard conversion completed', [
                'jobcard_id' => $jobcard->id,
                'invoice_id' => $invoice->id,
                'update_success' => $updated,
                'jobcard_invoice_id_after' => $jobcard->invoice_id
            ]);
            
            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Jobcard converted to invoice successfully');
        } catch (\Exception $e) {
            \Log::error('Jobcard conversion failed', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['message' => 'Failed to convert jobcard to invoice: ' . $e->getMessage()]);
        }
    }
}
