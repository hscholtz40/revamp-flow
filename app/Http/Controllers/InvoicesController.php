<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Jobcard;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $query = Invoice::with(['customer'])
            ->where('company_id', $currentCompany->id);

        // Hide paid invoices by default unless explicitly requested
        if (!$request->boolean('show_paid')) {
            $query->where('status', '!=', 'paid');
        }

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
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $invoices = $query->with(['customer', 'salesperson'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'customers' => $customers,
            'filters' => $request->only(['status', 'customer_id', 'search', 'show_paid']),
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $users = User::orderBy('name')->get();

        // Pre-fill customer if provided
        $selectedCustomer = null;
        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);
        }

        return Inertia::render('invoices/Create', [
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'selectedCustomer' => $selectedCustomer,
            'defaultTerms' => $currentCompany->default_invoice_terms,
            'currentUser' => auth()->user(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'salesperson_id' => 'nullable|exists:users,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Generate invoice number
        $invoiceNumber = Invoice::generateInvoiceNumber();

        // Set default salesperson to current user if not provided
        $salespersonId = $validated['salesperson_id'] ?? auth()->id();

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'customer_id' => $validated['customer_id'],
            'salesperson_id' => $salespersonId,
            'company_id' => $currentCompany->id,
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $validated['tax_rate'],
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
        ]);

        // Create line items
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $total = $lineItemData['quantity'] * $lineItemData['unit_price'];
            
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItemData['product_id'],
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'total' => $total,
                'sort_order' => $index,
            ]);
        }

        // Calculate totals
        $invoice->calculateTotals();

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Check if user has access to this invoice
        if ($invoice->company_id !== $currentCompany->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['customer', 'salesperson', 'lineItems.product', 'company', 'source', 'payments']);
        
        // Ensure totals are calculated
        $invoice->calculateTotals();
        $invoice->refresh();

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $invoice->load(['customer', 'lineItems.product']);

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $users = User::orderBy('name')->get();

        return Inertia::render('invoices/Edit', [
            'invoice' => $invoice,
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'canEditSalesperson' => auth()->user()->canEditSalesperson('invoices'),
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'salesperson_id' => 'nullable|exists:users,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
        ]);

        // Check if user can edit salesperson
        $canEditSalesperson = auth()->user()->canEditSalesperson('invoices');
        
        // Check if user can edit completed invoices (paid status)
        $canEditCompleted = auth()->user()->hasModulePermission('invoices', 'edit_completed');
        $isCompleted = $invoice->status === 'paid';
        
        // If invoice is completed (paid) and user can't edit completed, prevent update
        if ($isCompleted && !$canEditCompleted) {
            return redirect()->back()
                ->with('error', 'You cannot edit a paid invoice.');
        }
        
        // Prepare update data
        $updateData = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            'customer_id' => $validated['customer_id'],
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $validated['due_date'],
            'tax_rate' => $validated['tax_rate'],
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
        ];

        // Only update salesperson if user has permission
        if ($canEditSalesperson && isset($validated['salesperson_id'])) {
            $updateData['salesperson_id'] = $validated['salesperson_id'];
        }

        // Update invoice
        $invoice->update($updateData);

        // Delete existing line items
        $invoice->lineItems()->delete();

        // Create new line items
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $total = $lineItemData['quantity'] * $lineItemData['unit_price'];
            
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItemData['product_id'],
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'total' => $total,
                'sort_order' => $index,
            ]);
        }

        // Calculate totals
        $invoice->calculateTotals();

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Update the status of the invoice.
     */
    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        $invoice->update(['status' => $validated['status']]);

        return redirect()->back()
            ->with('success', 'Invoice status updated successfully.');
    }

    /**
     * Download PDF of the invoice.
     */
    public function downloadPdf(Invoice $invoice)
    {
        $invoice->load(['customer', 'lineItems.product', 'company']);
        
        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        
        // Update status to sent if it was draft
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }
        
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Email the invoice.
     */
    public function email(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'customMessage' => 'nullable|string',
        ]);

        $invoice->load(['customer', 'lineItems.product', 'company']);
        
        // Get user's SMTP settings
        $user = auth()->user();
        if ($user->smtp_host && $user->smtp_username && $user->smtp_password) {
            Config::set('mail.mailers.smtp.host', $user->smtp_host);
            Config::set('mail.mailers.smtp.port', $user->smtp_port ?? 587);
            Config::set('mail.mailers.smtp.username', $user->smtp_username);
            Config::set('mail.mailers.smtp.password', $user->smtp_password);
            Config::set('mail.mailers.smtp.encryption', $user->smtp_encryption ?? 'tls');
            Config::set('mail.from.address', $user->smtp_username);
            Config::set('mail.from.name', $user->name);
        }

        try {
            // Generate PDF
            $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
            $pdfContent = $pdf->output();
            
            // Send email
            Mail::mailer('smtp')->send('emails.invoice', [
                'invoice' => $invoice,
                'customMessage' => $validated['customMessage'],
            ], function ($message) use ($validated, $invoice, $pdfContent) {
                $message->to($validated['email'])
                    ->subject("Invoice {$invoice->invoice_number} - {$invoice->title}")
                    ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            // Update status to sent if it was draft
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'sent']);
            }

            return redirect()->back()->with('success', 'Invoice sent successfully to ' . $validated['email']);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }

    /**
     * Convert a quote to an invoice.
     */
    public function convertFromQuote(Quote $quote): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Generate invoice number
        $invoiceNumber = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'title' => $quote->title,
            'description' => $quote->description,
            'customer_id' => $quote->customer_id,
            'company_id' => $currentCompany->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tax_rate' => $quote->tax_rate,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'source_type' => 'quote',
            'source_id' => $quote->id,
        ]);

        // Copy line items
        foreach ($quote->lineItems as $quoteLineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoiceNumber->id,
                'product_id' => $quoteLineItem->product_id,
                'description' => $quoteLineItem->description,
                'quantity' => $quoteLineItem->quantity,
                'unit_price' => $quoteLineItem->unit_price,
                'total' => $quoteLineItem->total,
                'sort_order' => $quoteLineItem->sort_order,
            ]);
        }

        // Calculate totals
        $invoiceNumber->calculateTotals();

        // Update quote status
        $quote->update(['status' => 'accepted']);

        return redirect()->route('invoices.show', $invoiceNumber)
            ->with('success', 'Invoice created from quote successfully.');
    }

    /**
     * Convert a jobcard to an invoice.
     */
    public function convertFromJobcard(Jobcard $jobcard): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        // Generate invoice number
        $invoiceNumber = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'title' => $jobcard->title,
            'description' => $jobcard->description,
            'customer_id' => $jobcard->customer_id,
            'company_id' => $currentCompany->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tax_rate' => $jobcard->tax_rate,
            'notes' => $jobcard->notes,
            'terms' => $jobcard->terms,
            'source_type' => 'jobcard',
            'source_id' => $jobcard->id,
        ]);

        // Copy line items
        foreach ($jobcard->lineItems as $jobcardLineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoiceNumber->id,
                'product_id' => $jobcardLineItem->product_id,
                'description' => $jobcardLineItem->description,
                'quantity' => $jobcardLineItem->quantity,
                'unit_price' => $jobcardLineItem->unit_price,
                'total' => $jobcardLineItem->total,
                'sort_order' => $jobcardLineItem->sort_order,
            ]);
        }

        // Calculate totals
        $invoiceNumber->calculateTotals();

        // Update jobcard status
        $jobcard->update(['status' => 'completed']);

        return redirect()->route('invoices.show', $invoiceNumber)
            ->with('success', 'Invoice created from jobcard successfully.');
    }

}