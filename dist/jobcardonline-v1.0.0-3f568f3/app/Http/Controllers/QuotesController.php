<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Product;
use App\Models\Jobcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;

class QuotesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $query = Quote::with(['customer'])
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
                $q->where('quote_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $quotes = $query->orderByDesc('created_at')->paginate(15);
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('quotes/Index', [
            'quotes' => $quotes,
            'customers' => $customers,
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'search' => $request->input('search', ''),
            ],
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
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

        return Inertia::render('quotes/Create', [
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_quote_terms,
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
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
            'expiry_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
        ]);

        // Create the quote
        $quote = Quote::create([
            'company_id' => $currentCompany->id,
            'customer_id' => $validated['customer_id'],
            'quote_number' => Quote::generateQuoteNumber(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'expiry_date' => $validated['expiry_date'],
            'tax_rate' => $validated['tax_rate'] ?? 15,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'notes' => $validated['notes'],
            'terms_conditions' => $validated['terms_conditions'],
        ]);

        // Create line items
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $total = $lineItemData['quantity'] * $lineItemData['unit_price'];
            
            $lineItem = QuoteLineItem::create([
                'quote_id' => $quote->id,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'total' => $total,
                'sort_order' => $index,
            ]);
        }

        // Calculate totals
        $quote->calculateTotals();

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Quote created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote): Response
    {
        $quote->load(['customer', 'lineItems.product', 'company', 'invoice']);
        
        // Ensure totals are calculated
        $quote->calculateTotals();
        $quote->refresh();
        
        return Inertia::render('quotes/Show', [
            'quote' => $quote,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quote $quote): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $quote->load(['lineItems.product']);
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'price', 'type']);

        return Inertia::render('quotes/Edit', [
            'quote' => $quote,
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
            'expiry_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'line_items.*.product_id' => ['nullable', 'exists:products,id'],
        ]);

        // Check if user can edit completed quotes (accepted status)
        $canEditCompleted = auth()->user()->hasModulePermission('quotes', 'edit_completed');
        $isCompleted = $quote->status === 'accepted';
        
        // If quote is completed (accepted) and user can't edit completed, prevent update
        if ($isCompleted && !$canEditCompleted) {
            return redirect()->back()
                ->with('error', 'You cannot edit an accepted quote.');
        }

        // Update the quote
        $quote->update([
            'customer_id' => $validated['customer_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'expiry_date' => $validated['expiry_date'],
            'tax_rate' => $validated['tax_rate'] ?? 15,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'notes' => $validated['notes'],
            'terms_conditions' => $validated['terms_conditions'],
        ]);

        // Delete existing line items
        $quote->lineItems()->delete();

        // Create new line items
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $total = $lineItemData['quantity'] * $lineItemData['unit_price'];
            
            $lineItem = QuoteLineItem::create([
                'quote_id' => $quote->id,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'total' => $total,
                'sort_order' => $index,
            ]);
        }

        // Calculate totals
        $quote->calculateTotals();

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Quote updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        $quote->delete();
        return redirect()->route('quotes.index')
            ->with('success', 'Quote deleted successfully');
    }

    /**
     * Update quote status only
     */
    public function updateStatus(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
        ]);

        $quote->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Quote status updated successfully');
    }

    /**
     * Convert quote to jobcard
     */
    public function convertToJobcard(Quote $quote): RedirectResponse
    {
        $jobcard = $quote->convertToJobcard();
        
        // Update quote status to accepted when converted to jobcard
        $quote->update(['status' => 'accepted']);
        
        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Quote converted to jobcard successfully');
    }

    /**
     * Convert quote to invoice
     */
    public function convertToInvoice(Quote $quote): RedirectResponse
    {
        try {
            $invoice = $quote->convertToInvoice();
            
            // Update quote status to accepted and link to invoice
            $updated = $quote->update(['status' => 'accepted', 'invoice_id' => $invoice->id]);
            
            // Refresh the quote to get updated data
            $quote->refresh();
            
            // Log for debugging
            \Log::info('Quote conversion debug', [
                'quote_id' => $quote->id,
                'invoice_id' => $invoice->id,
                'update_success' => $updated,
                'quote_invoice_id_after' => $quote->invoice_id
            ]);
            
            return redirect()->route('invoices.show', $invoice)
                ->with('success', 'Quote converted to invoice successfully');
        } catch (\Exception $e) {
            \Log::error('Quote conversion failed', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['message' => 'Failed to convert quote to invoice: ' . $e->getMessage()]);
        }
    }

    /**
     * Download quote as PDF
     */
    public function downloadPDF(Quote $quote)
    {
        $quote->load(['customer', 'lineItems.product', 'company']);
        
        // Update status to sent when PDF is downloaded
        if ($quote->status === 'draft') {
            $quote->update(['status' => 'sent']);
        }
        
        $pdf = Pdf::loadView('pdf.quote', compact('quote'));
        return $pdf->download("quote-{$quote->quote_number}.pdf");
    }

    /**
     * Email quote to customer
     */
    public function emailQuote(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'message' => ['nullable', 'string'],
        ]);

        $quote->load(['customer', 'lineItems.product', 'company']);
        
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
            $pdf = Pdf::loadView('pdf.quote', compact('quote'));
            $pdfContent = $pdf->output();
            
            // Send email
            Mail::mailer('smtp')->send('emails.quote', [
                'quote' => $quote,
                'customMessage' => $validated['message'],
            ], function ($message) use ($validated, $quote, $pdfContent) {
                $message->to($validated['email'])
                    ->subject("Quote {$quote->quote_number} - {$quote->title}")
                    ->attachData($pdfContent, "quote-{$quote->quote_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
            });

            // Update status to sent when email is sent successfully
            if ($quote->status === 'draft') {
                $quote->update(['status' => 'sent']);
            }

            return redirect()->back()->with('success', 'Quote emailed successfully');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'Failed to send email: ' . $e->getMessage()]);
        }
    }
}
