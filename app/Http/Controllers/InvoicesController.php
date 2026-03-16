<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Jobcard;
use App\Models\ChartOfAccount;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Payment;
use App\Services\ReminderService;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';
        
        $query = Invoice::with(['customer', 'payments', 'creditNotes'])
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

        $sortableFields = ['invoice_number', 'customer_name', 'salesperson_name', 'invoice_date', 'due_date', 'status', 'total', 'created_at'];
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        $invoicesQuery = $query->with(['customer', 'salesperson']);
        if ($sortBy === 'customer_name') {
            $invoicesQuery->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'invoices.customer_id')->limit(1),
                $sortDir
            );
        } elseif ($sortBy === 'salesperson_name') {
            $invoicesQuery->orderBy(
                User::select('name')->whereColumn('users.id', 'invoices.salesperson_id')->limit(1),
                $sortDir
            );
        } else {
            $invoicesQuery->orderBy($sortBy, $sortDir);
        }

        $invoices = $invoicesQuery->paginate(15)->withQueryString();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'customers' => $customers,
            'currentCompany' => $currentCompany,
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'search' => $request->input('search', ''),
                'show_paid' => $request->boolean('show_paid'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
            'canCreateInvoices' => auth()->user()->hasModulePermission('invoices', 'create'),
            'isPosEnabled' => (bool) ($currentCompany?->enable_pos ?? false),
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
            ->with(['serialNumbers' => function($query) {
                $query->where('status', 'available');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'track_stock' => $product->track_stock,
                    'track_serial_numbers' => $product->track_serial_numbers,
                    'serialNumbers' => $product->serialNumbers->map(function ($serial) {
                        return [
                            'id' => $serial->id,
                            'serial_number' => $serial->serial_number,
                            'status' => $serial->status,
                        ];
                    })->toArray(),
                ];
            });

        $users = User::orderBy('name')->get();

        // Pre-fill customer if provided
        $selectedCustomer = null;
        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);
        } else {
            $selectedCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);
        }

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = $this->resolveInvoiceFallbackAccount($currentCompany->id);

        return Inertia::render('invoices/Create', [
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'selectedCustomer' => $selectedCustomer,
            'defaultTerms' => $currentCompany->default_invoice_terms,
            'currentUser' => auth()->user(),
            'currentCompany' => $currentCompany,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
        ]);
    }

    public function pos(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (!$currentCompany || !$currentCompany->enable_pos) {
            abort(403, 'POS is not enabled for this company.');
        }

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'track_stock', 'stock_quantity']);
        $selectedCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);
        $printPdfUrl = null;
        if ($request->filled('print_invoice')) {
            $printInvoice = Invoice::where('company_id', $currentCompany->id)
                ->whereKey((int) $request->input('print_invoice'))
                ->first();
            if ($printInvoice) {
                $printPdfUrl = route('invoices.print-pdf', $printInvoice->id);
            }
        }

        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultSalesAccount = $this->resolveInvoiceFallbackAccount($currentCompany->id);

        return Inertia::render('invoices/Pos', [
            'customers' => $customers,
            'products' => $products,
            'selectedCustomer' => $selectedCustomer,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_invoice_terms,
            'printPdfUrl' => $printPdfUrl,
            'defaultSalesTaxRate' => $defaultSalesTaxRate ? [
                'id' => $defaultSalesTaxRate->id,
                'name' => $defaultSalesTaxRate->name,
                'rate' => (float) $defaultSalesTaxRate->rate,
            ] : null,
            'defaultSalesAccountLabel' => $defaultSalesAccount
                ? trim(($defaultSalesAccount->account_code ? $defaultSalesAccount->account_code . ' - ' : '') . $defaultSalesAccount->account_name)
                : null,
        ]);
    }

    public function storePos(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        if (!$currentCompany || !$currentCompany->enable_pos) {
            abort(403, 'POS is not enabled for this company.');
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'order_number' => 'nullable|string|max:255',
            'invoice_date' => 'required|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required|in:cash,card,eft,account',
            'amount_paid' => 'nullable|numeric|min:0',
            'tendered_amount' => 'nullable|numeric|min:0',
        ]);

        $customer = Customer::where('company_id', $currentCompany->id)->findOrFail($validated['customer_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date'])->startOfDay();
        $invoiceNumber = Invoice::generateInvoiceNumber($currentCompany->id);
        $dueDate = $this->resolveInvoiceDueDateFromCustomerTerms($customer, $invoiceDate);
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultTaxRateId = $defaultTaxRate?->id;
        $defaultTaxRateRate = (float) ($defaultTaxRate?->rate ?? 0);
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);
        $terms = !empty(trim((string) ($validated['terms'] ?? '')))
            ? trim((string) $validated['terms'])
            : ((string) ($customer->terms ?: 'COD'));

        $salespersonId = auth()->id();

        $created = DB::transaction(function () use ($validated, $currentCompany, $invoiceNumber, $dueDate, $terms, $invoiceDate, $defaultTaxRateId, $defaultTaxRateRate, $defaultAccountId, $salespersonId) {
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'order_number' => $validated['order_number'] ?? null,
                'title' => $invoiceNumber,
                'description' => 'POS Sale',
                'customer_id' => $validated['customer_id'],
                'salesperson_id' => $salespersonId,
                'company_id' => $currentCompany->id,
                'status' => 'sent',
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'tax_rate' => 0,
                'notes' => $validated['notes'] ?? null,
                'terms' => $terms,
            ]);

            // POS invoices should always retain the currently logged-in user as salesperson.
            if (!$invoice->salesperson_id && $salespersonId) {
                $invoice->update(['salesperson_id' => $salespersonId]);
            }

            $stockService = new StockService();
            foreach ($validated['line_items'] as $index => $lineItemData) {
                $quantity = (int) ($lineItemData['quantity'] ?? 0);
                $unitPrice = (float) ($lineItemData['unit_price'] ?? 0);
                $discountAmount = (float) ($lineItemData['discount_amount'] ?? 0);
                $discountPercentage = (float) ($lineItemData['discount_percentage'] ?? 0);
                $subtotal = $quantity * $unitPrice;

                if ($discountPercentage > 0) {
                    $discountAmount = $subtotal * ($discountPercentage / 100);
                }

                $total = $subtotal - $discountAmount;
                $lineTaxAmount = 0;
                if ($defaultTaxRateId) {
                    $lineTaxAmount = ceil(($total * ($defaultTaxRateRate / 100)) * 100) / 100;
                }

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $lineItemData['product_id'],
                    'description' => $lineItemData['description'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                    'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                    'total' => $total,
                    'tax_rate_id' => $defaultTaxRateId,
                    'tax_amount' => $lineTaxAmount,
                    'account_id' => $defaultAccountId,
                    'sort_order' => $index,
                ]);

                if (!empty($lineItemData['product_id'])) {
                    $product = Product::find($lineItemData['product_id']);
                    if ($product && $product->track_stock) {
                        try {
                            $stockService->removeStock(
                                $product,
                                $quantity,
                                "POS Sale: {$invoiceNumber}",
                                'invoice',
                                $invoice->id,
                                "Stock deducted for POS sale {$invoiceNumber}"
                            );
                        } catch (\Exception $e) {
                            Log::warning('POS stock deduction skipped', [
                                'invoice_number' => $invoiceNumber,
                                'product_id' => $product->id,
                                'quantity' => $quantity,
                                'reason' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            $invoice->calculateTotals();
            $invoice->refresh();

            $isAccountSale = ($validated['payment_method'] ?? '') === 'account';
            if (! $isAccountSale) {
                $amountPaid = (float) ($validated['amount_paid'] ?? 0);
                if ($amountPaid <= 0) {
                    abort(422, 'Payment amount is required for this payment method.');
                }

                if ($amountPaid > (float) $invoice->total) {
                    abort(422, 'Payment amount cannot exceed invoice total.');
                }

                if (($validated['payment_method'] ?? '') === 'cash') {
                    $tendered = (float) ($validated['tendered_amount'] ?? 0);
                    if ($tendered < $amountPaid) {
                        abort(422, 'Tendered cash must be greater than or equal to amount paid.');
                    }
                }

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'company_id' => $currentCompany->id,
                    'amount' => $amountPaid,
                    'payment_method' => $validated['payment_method'],
                    'payment_date' => $invoiceDate->toDateString(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                $invoice->refresh();
                $invoice->load('payments');
                if ($invoice->isFullyPaid()) {
                    $invoice->update(['status' => 'paid']);
                }
            }

            return $invoice;
        });

        return redirect()->route('invoices.pos', ['print_invoice' => $created->id])
            ->with('success', 'POS sale created successfully.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:255',
            'salesperson_id' => 'nullable|exists:users,id',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => 'nullable|exists:tax_rates,id',
            'line_items.*.account_id' => 'nullable|exists:chart_of_accounts,id',
            'line_items.*.serial_number_ids' => 'nullable|array',
            'line_items.*.serial_number_ids.*' => 'exists:product_serial_numbers,id',
        ]);

        // Generate invoice number
        $invoiceNumber = Invoice::generateInvoiceNumber($currentCompany->id);
        $customer = Customer::where('company_id', $currentCompany->id)->findOrFail($validated['customer_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date'])->startOfDay();
        $dueDate = $this->resolveInvoiceDueDateFromCustomerTerms($customer, $invoiceDate);
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);

        // Set default salesperson to current user if not provided
        $salespersonId = $validated['salesperson_id'] ?? auth()->id();

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'order_number' => $validated['order_number'] ?? null,
            'title' => !empty(trim((string) ($validated['title'] ?? ''))) ? trim((string) $validated['title']) : $invoiceNumber,
            'description' => $validated['description'],
            'customer_id' => $validated['customer_id'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'salesperson_id' => $salespersonId,
            'company_id' => $currentCompany->id,
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $dueDate->toDateString(),
            'tax_rate' => $validated['tax_rate'],
            'notes' => $validated['notes'],
            'terms' => $validated['terms'],
        ]);

        // Create line items and deduct stock
        $stockService = new StockService();
        foreach ($validated['line_items'] as $index => $lineItemData) {
            // Calculate total before creating
            $quantity = $lineItemData['quantity'] ?? 0;
            $unitPrice = $lineItemData['unit_price'] ?? 0;
            $discountAmount = $lineItemData['discount_amount'] ?? 0;
            $discountPercentage = $lineItemData['discount_percentage'] ?? 0;
            
            $subtotal = $quantity * $unitPrice;
            
            // Apply discount: percentage takes precedence over amount
            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }
            
            $total = $subtotal - $discountAmount;
            
            // Calculate per-line-item tax
            $taxRateId = $lineItemData['tax_rate_id'] ?? null;
            $lineTaxAmount = 0;
            if ($taxRateId) {
                $taxRateModel = TaxRate::find($taxRateId);
                if ($taxRateModel) {
                    $lineTaxAmount = ceil(($total * ($taxRateModel->rate / 100)) * 100) / 100;
                }
            }

            $lineItem = InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItemData['product_id'],
                'description' => $lineItemData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'total' => $total,
                'tax_rate_id' => $taxRateId,
                'tax_amount' => $lineTaxAmount,
                'account_id' => $lineItemData['account_id'] ?? $defaultAccountId,
                'sort_order' => $index,
                'serial_number_ids' => $lineItemData['serial_number_ids'] ?? null,
            ]);

            // Update serial numbers to sold status and link to invoice
            if (!empty($lineItemData['serial_number_ids'])) {
                try {
                    \App\Models\ProductSerialNumber::whereIn('id', $lineItemData['serial_number_ids'])
                        ->update([
                            'status' => 'sold',
                            'invoice_id' => $invoice->id,
                            'sale_date' => now(),
                        ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update serial numbers for invoice line item', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $lineItemData['product_id'],
                        'serial_number_ids' => $lineItemData['serial_number_ids'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if serial number update fails
                }
            }

            // Deduct stock if product is tracked
            if ($lineItemData['product_id']) {
                try {
                    $product = Product::find($lineItemData['product_id']);
                    if ($product && $product->track_stock) {
                        $stockService->removeStock(
                            $product,
                            $lineItemData['quantity'],
                            "Invoice: {$invoiceNumber}",
                            'invoice',
                            $invoice->id,
                            "Stock deducted for invoice {$invoiceNumber}",
                            $lineItemData['serial_number_ids'] ?? null
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to deduct stock for invoice line item', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $lineItemData['product_id'],
                        'quantity' => $lineItemData['quantity'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if stock deduction fails
                }
            }
        }

        // Calculate totals
        $invoice->calculateTotals();
        $invoice->refresh();
        $invoice->load('customer', 'company');

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService();
            $reminderService->sendInvoiceCreatedConfirmation($invoice);
        } catch (\Exception $e) {
            \Log::error('Failed to send invoice created confirmation', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the invoice creation if reminder fails
        }

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

        $invoice->load(['customer', 'salesperson', 'lineItems.product', 'lineItems.taxRate', 'company', 'source', 'payments', 'creditNotes']);
        
        // Load serial numbers for line items that have serial_number_ids
        $invoice->load('lineItems');
        
        // Ensure payments are loaded for accurate total_paid and remaining_balance calculations
        if (!$invoice->relationLoaded('payments')) {
            $invoice->load('payments');
        }
        
        // Convert invoice to array first
        $invoiceData = $invoice->toArray();
        
        // Then manually add serial numbers to each line item in the array
        if (isset($invoiceData['line_items']) && is_array($invoiceData['line_items'])) {
            foreach ($invoiceData['line_items'] as $key => $lineItemData) {
                $serialNumberIds = $lineItemData['serial_number_ids'] ?? null;
                if ($serialNumberIds && is_array($serialNumberIds) && count($serialNumberIds) > 0) {
                    $serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $serialNumberIds)
                        ->get(['id', 'serial_number', 'status'])
                        ->toArray();
                    $invoiceData['line_items'][$key]['serialNumbers'] = $serialNumbers;
                } else {
                    $invoiceData['line_items'][$key]['serialNumbers'] = [];
                }
            }
        }

        // Get available PDF templates for invoices
        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->where('module', 'invoice')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);
        
        $defaultTemplateId = $pdfTemplates->where('is_default', true)->first()?->id ?? null;
        
        return Inertia::render('invoices/Show', [
            'invoice' => $invoiceData,
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
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

        // Get all invoice line item serial number IDs for this invoice
        $invoiceSerialNumberIds = $invoice->lineItems->pluck('serial_number_ids')->flatten()->filter()->unique()->toArray();

        $products = Product::where('company_id', $currentCompany->id)
            ->with(['serialNumbers' => function($query) use ($invoiceSerialNumberIds) {
                // Include available serial numbers OR sold ones that are on this invoice
                $query->where(function($q) use ($invoiceSerialNumberIds) {
                    $q->where('status', 'available')
                      ->orWhere(function($subQ) use ($invoiceSerialNumberIds) {
                          $subQ->where('status', 'sold')
                               ->whereIn('id', $invoiceSerialNumberIds);
                      });
                });
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'track_stock' => $product->track_stock,
                    'track_serial_numbers' => $product->track_serial_numbers,
                    'serialNumbers' => $product->serialNumbers->map(function ($serial) {
                        return [
                            'id' => $serial->id,
                            'serial_number' => $serial->serial_number,
                            'status' => $serial->status,
                        ];
                    })->toArray(),
                ];
            });

        $users = User::orderBy('name')->get();

        // Convert invoice to array and ensure serial_number_ids are included in line items
        $invoiceData = $invoice->toArray();
        
        // Ensure line items have serial_number_ids properly set
        if (isset($invoiceData['line_items']) && is_array($invoiceData['line_items'])) {
            foreach ($invoiceData['line_items'] as $key => $lineItemData) {
                // Make sure serial_number_ids is an array (it might be null or not set)
                if (!isset($lineItemData['serial_number_ids']) || !is_array($lineItemData['serial_number_ids'])) {
                    $invoiceData['line_items'][$key]['serial_number_ids'] = [];
                }
            }
        }

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = $this->resolveInvoiceFallbackAccount($currentCompany->id);

        return Inertia::render('invoices/Edit', [
            'invoice' => $invoiceData,
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
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
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'customer_id' => 'required|exists:customers,id',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'order_number' => 'nullable|string|max:255',
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
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => 'nullable|exists:tax_rates,id',
            'line_items.*.account_id' => 'nullable|exists:chart_of_accounts,id',
            'line_items.*.serial_number_ids' => 'nullable|array',
            'line_items.*.serial_number_ids.*' => 'exists:product_serial_numbers,id',
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
            'title' => !empty(trim((string) ($validated['title'] ?? ''))) ? trim((string) $validated['title']) : $invoice->invoice_number,
            'description' => $validated['description'],
            'customer_id' => $validated['customer_id'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'order_number' => $validated['order_number'] ?? null,
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
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($invoice->company_id);

        // Handle stock adjustments for invoice updates
        // Only adjust stock if invoice is not cancelled (cancelled invoices don't affect stock)
        $stockService = new StockService();
        $invoice->load('lineItems.product');
        $wasCancelled = $invoice->status === 'cancelled';
        
        // Restore stock and serial numbers for old line items (if invoice was not cancelled)
        // Stock was already restored when invoice was cancelled, so skip if it was cancelled
        if (!$wasCancelled) {
            foreach ($invoice->lineItems as $oldLineItem) {
                // Restore serial numbers to available status
                if (!empty($oldLineItem->serial_number_ids)) {
                    try {
                        \App\Models\ProductSerialNumber::whereIn('id', $oldLineItem->serial_number_ids)
                            ->where('invoice_id', $invoice->id)
                            ->update([
                                'status' => 'available',
                                'invoice_id' => null,
                                'sale_date' => null,
                            ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to restore serial numbers for updated invoice line item', [
                            'invoice_id' => $invoice->id,
                            'line_item_id' => $oldLineItem->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($oldLineItem->product_id && $oldLineItem->product && $oldLineItem->product->track_stock) {
                    try {
                        $stockService->addStock(
                            $oldLineItem->product,
                            $oldLineItem->quantity,
                            null,
                            "Invoice Updated: {$invoice->invoice_number}",
                            'invoice',
                            $invoice->id,
                            "Stock restored due to invoice line item update"
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to restore stock for updated invoice line item', [
                            'invoice_id' => $invoice->id,
                            'product_id' => $oldLineItem->product_id,
                            'quantity' => $oldLineItem->quantity,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue even if stock restoration fails
                    }
                }
            }
        }

        // Delete existing line items
        $invoice->lineItems()->delete();

        // Create new line items and deduct stock (if invoice is not cancelled)
        foreach ($validated['line_items'] as $index => $lineItemData) {
            // Calculate total before creating
            $quantity = $lineItemData['quantity'] ?? 0;
            $unitPrice = $lineItemData['unit_price'] ?? 0;
            $discountAmount = $lineItemData['discount_amount'] ?? 0;
            $discountPercentage = $lineItemData['discount_percentage'] ?? 0;
            
            $subtotal = $quantity * $unitPrice;
            
            // Apply discount: percentage takes precedence over amount
            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }
            
            $total = $subtotal - $discountAmount;
            
            // Calculate per-line-item tax
            $taxRateId = $lineItemData['tax_rate_id'] ?? null;
            $lineTaxAmount = 0;
            if ($taxRateId) {
                $taxRateModel = TaxRate::find($taxRateId);
                if ($taxRateModel) {
                    $lineTaxAmount = ceil(($total * ($taxRateModel->rate / 100)) * 100) / 100;
                }
            }

            $lineItem = InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItemData['product_id'],
                'description' => $lineItemData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'total' => $total,
                'tax_rate_id' => $taxRateId,
                'tax_amount' => $lineTaxAmount,
                'account_id' => $lineItemData['account_id'] ?? $defaultAccountId,
                'sort_order' => $index,
                'serial_number_ids' => $lineItemData['serial_number_ids'] ?? null,
            ]);

            // Update serial numbers to sold status and link to invoice
            if (!empty($lineItemData['serial_number_ids']) && !$wasCancelled) {
                try {
                    \App\Models\ProductSerialNumber::whereIn('id', $lineItemData['serial_number_ids'])
                        ->update([
                            'status' => 'sold',
                            'invoice_id' => $invoice->id,
                            'sale_date' => now(),
                        ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update serial numbers for updated invoice line item', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $lineItemData['product_id'],
                        'serial_number_ids' => $lineItemData['serial_number_ids'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if serial number update fails
                }
            }

            // Deduct stock if product is tracked and invoice is not cancelled
            if ($lineItemData['product_id'] && !$wasCancelled) {
                try {
                    $product = Product::find($lineItemData['product_id']);
                    if ($product && $product->track_stock) {
                        $stockService->removeStock(
                            $product,
                            $lineItemData['quantity'],
                            "Invoice Updated: {$invoice->invoice_number}",
                            'invoice',
                            $invoice->id,
                            "Stock deducted for invoice line item update",
                            $lineItemData['serial_number_ids'] ?? null
                        );
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to deduct stock for updated invoice line item', [
                        'invoice_id' => $invoice->id,
                        'product_id' => $lineItemData['product_id'],
                        'quantity' => $lineItemData['quantity'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if stock deduction fails
                }
            }
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
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($invoice->company_id !== $currentCompany->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        // Restore stock and serial numbers for all line items before deleting
        // Skip if invoice is cancelled (stock was already restored when cancelled)
        if ($invoice->status !== 'cancelled') {
            $stockService = new StockService();
            $invoice->load('lineItems.product');
            
            foreach ($invoice->lineItems as $lineItem) {
                // Restore serial numbers to available status
                if (!empty($lineItem->serial_number_ids)) {
                    try {
                        \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                            ->where('invoice_id', $invoice->id)
                            ->update([
                                'status' => 'available',
                                'invoice_id' => null,
                                'sale_date' => null,
                            ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to restore serial numbers for deleted invoice', [
                            'invoice_id' => $invoice->id,
                            'line_item_id' => $lineItem->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($lineItem->product_id && $lineItem->product && $lineItem->product->track_stock) {
                    try {
                        $stockService->addStock(
                            $lineItem->product,
                            $lineItem->quantity,
                            null, // No unit cost for restoration
                            "Invoice Deleted: {$invoice->invoice_number}",
                            'invoice',
                            $invoice->id,
                            "Stock restored due to invoice deletion"
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to restore stock for deleted invoice line item', [
                            'invoice_id' => $invoice->id,
                            'product_id' => $lineItem->product_id,
                            'quantity' => $lineItem->quantity,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue even if stock restoration fails
                    }
                }
            }
        }

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Update the status of the invoice.
     */
    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        
        if ($invoice->company_id !== $currentCompany->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $validated = $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        $oldStatus = $invoice->status;
        $newStatus = $validated['status'];

        // Handle stock adjustments based on status changes
        $stockService = new StockService();
        $invoice->load('lineItems.product');
        
        // If changing TO cancelled, restore stock and serial numbers
        if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
            foreach ($invoice->lineItems as $lineItem) {
                // Restore serial numbers to available status
                if (!empty($lineItem->serial_number_ids)) {
                    try {
                        \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                            ->where('invoice_id', $invoice->id)
                            ->update([
                                'status' => 'available',
                                'invoice_id' => null,
                                'sale_date' => null,
                            ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to restore serial numbers for cancelled invoice', [
                            'invoice_id' => $invoice->id,
                            'line_item_id' => $lineItem->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($lineItem->product_id && $lineItem->product && $lineItem->product->track_stock) {
                    try {
                        $stockService->addStock(
                            $lineItem->product,
                            $lineItem->quantity,
                            null, // No unit cost for restoration
                            "Invoice Cancelled: {$invoice->invoice_number}",
                            'invoice',
                            $invoice->id,
                            "Stock restored due to invoice cancellation"
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to restore stock for cancelled invoice line item', [
                            'invoice_id' => $invoice->id,
                            'product_id' => $lineItem->product_id,
                            'quantity' => $lineItem->quantity,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue even if stock restoration fails
                    }
                }
            }
        }
        
        // If changing FROM cancelled TO another status, deduct stock again
        if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
            foreach ($invoice->lineItems as $lineItem) {
                if ($lineItem->product_id && $lineItem->product && $lineItem->product->track_stock) {
                    try {
                        $stockService->removeStock(
                            $lineItem->product,
                            $lineItem->quantity,
                            "Invoice Status Changed: {$invoice->invoice_number}",
                            'invoice',
                            $invoice->id,
                            "Stock deducted - invoice status changed from cancelled to {$newStatus}"
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to deduct stock when invoice status changed from cancelled', [
                            'invoice_id' => $invoice->id,
                            'product_id' => $lineItem->product_id,
                            'quantity' => $lineItem->quantity,
                            'error' => $e->getMessage(),
                        ]);
                        // Continue even if stock deduction fails
                    }
                }
            }
        }

        $invoice->update(['status' => $newStatus]);

        return redirect()->back()
            ->with('success', 'Invoice status updated successfully.');
    }

    /**
     * Download PDF of the invoice.
     */
    public function downloadPdf(Request $request, Invoice $invoice)
    {
        $invoice->load(['customer', 'lineItems.product', 'lineItems.taxRate', 'company', 'source']);
        
        // Load serial numbers for line items that have serial_number_ids
        foreach ($invoice->lineItems as $lineItem) {
            if (!empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        
        $company = $invoice->company;
        $templateId = $request->get('template_id');
        
        $pdfService = new \App\Services\PdfGenerationService();
        $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);
        
        // Update status to sent if it was draft
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }
        
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Stream printable PDF of the invoice.
     */
    public function printPdf(Request $request, Invoice $invoice)
    {
        $invoice->load(['customer', 'lineItems.product', 'lineItems.taxRate', 'company', 'source']);
        
        foreach ($invoice->lineItems as $lineItem) {
            if (!empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        
        $company = $invoice->company;
        $templateId = $request->get('template_id');
        
        $pdfService = new \App\Services\PdfGenerationService();
        $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);

        $filename = "invoice-{$invoice->invoice_number}.pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
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

        $invoice->load(['customer', 'lineItems.product', 'lineItems.taxRate', 'company']);
        
        // Load serial numbers for line items that have serial_number_ids
        foreach ($invoice->lineItems as $lineItem) {
            if (!empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        
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
            $company = $invoice->company;
            $templateId = $request->get('template_id');
            
            $pdfService = new \App\Services\PdfGenerationService();
            $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);
            $pdfContent = $pdf->output();
            
            // Send email
            Mail::mailer('smtp')->send('emails.invoice', [
                'invoice' => $invoice,
                'customMessage' => $validated['customMessage'],
            ], function ($message) use ($validated, $invoice, $pdfContent, $company) {
                $message->to($validated['email'])
                    ->subject("Invoice {$invoice->invoice_number} - {$invoice->title}")
                    ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);

                if (!empty($company->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
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
        $salespersonId = auth()->id();
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);
        
        // Generate invoice number
        $invoiceNumber = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber($currentCompany->id),
            'order_number' => $quote->order_number,
            'title' => $quote->title,
            'description' => $quote->description,
            'customer_id' => $quote->customer_id,
            'email' => $quote->email,
            'phone' => $quote->phone,
            'salesperson_id' => $salespersonId,
            'company_id' => $currentCompany->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => $this->resolveInvoiceDueDateFromCustomerTerms(
                $quote->customer,
                now()->startOfDay()
            )->toDateString(),
            'tax_rate' => $quote->tax_rate,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'source_type' => 'quote',
            'source_id' => $quote->id,
        ]);

        if (!$invoiceNumber->salesperson_id && $salespersonId) {
            $invoiceNumber->update(['salesperson_id' => $salespersonId]);
        }

        // Copy line items
        foreach ($quote->lineItems as $quoteLineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoiceNumber->id,
                'product_id' => $quoteLineItem->product_id,
                'description' => $quoteLineItem->description,
                'quantity' => $quoteLineItem->quantity,
                'unit_price' => $quoteLineItem->unit_price,
                'total' => $quoteLineItem->total,
                'tax_rate_id' => $quoteLineItem->tax_rate_id,
                'tax_amount' => $quoteLineItem->tax_amount,
                'account_id' => $quoteLineItem->account_id ?? $defaultAccountId,
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
        $salespersonId = auth()->id();
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);
        
        // Generate invoice number
        $invoiceNumber = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber($currentCompany->id),
            'order_number' => $jobcard->order_number,
            'title' => $jobcard->title,
            'description' => $jobcard->description,
            'customer_id' => $jobcard->customer_id,
            'email' => $jobcard->email,
            'phone' => $jobcard->phone,
            'salesperson_id' => $salespersonId,
            'company_id' => $currentCompany->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => $this->resolveInvoiceDueDateFromCustomerTerms(
                $jobcard->customer,
                now()->startOfDay()
            )->toDateString(),
            'tax_rate' => $jobcard->tax_rate,
            'notes' => $jobcard->notes,
            'terms' => $jobcard->terms,
            'source_type' => 'jobcard',
            'source_id' => $jobcard->id,
        ]);

        if (!$invoiceNumber->salesperson_id && $salespersonId) {
            $invoiceNumber->update(['salesperson_id' => $salespersonId]);
        }

        // Copy line items
        foreach ($jobcard->lineItems as $jobcardLineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoiceNumber->id,
                'product_id' => $jobcardLineItem->product_id,
                'description' => $jobcardLineItem->description,
                'quantity' => $jobcardLineItem->quantity,
                'unit_price' => $jobcardLineItem->unit_price,
                'total' => $jobcardLineItem->total,
                'tax_rate_id' => $jobcardLineItem->tax_rate_id,
                'tax_amount' => $jobcardLineItem->tax_amount,
                'account_id' => $jobcardLineItem->account_id ?? $defaultAccountId,
                'sort_order' => $jobcardLineItem->sort_order,
            ]);
        }

        // Calculate totals
        $invoiceNumber->calculateTotals();

        // Update jobcard status to completed and link to invoice
        $oldStatus = $jobcard->status;
        $jobcard->update([
            'status' => 'completed',
            'completed_date' => now(),
            'invoice_id' => $invoiceNumber->id,
        ]);

        // Send invoice created notification if enabled
        // Load invoice relationships needed for notifications
        $invoiceNumber->load('customer', 'company');
        try {
            $reminderService = new ReminderService();
            $reminderService->sendInvoiceCreatedConfirmation($invoiceNumber);
        } catch (\Exception $e) {
            Log::error('Failed to send invoice created confirmation after jobcard conversion', [
                'invoice_id' => $invoiceNumber->id,
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the conversion if reminder fails
        }

        // Send jobcard status updated notification if status changed
        if ($oldStatus !== 'completed') {
            try {
                $reminderService = new ReminderService();
                $reminderService->sendJobcardStatusUpdatedConfirmation($jobcard, $oldStatus);
            } catch (\Exception $e) {
                Log::error('Failed to send jobcard status updated confirmation after conversion', [
                    'jobcard_id' => $jobcard->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the conversion if reminder fails
            }
        }

        return redirect()->route('invoices.show', $invoiceNumber)
            ->with('success', 'Invoice created from jobcard successfully.');
    }

    private function resolveInvoiceDueDateFromCustomerTerms(?Customer $customer, Carbon $invoiceDate): Carbon
    {
        $terms = trim((string) ($customer?->terms ?: 'COD'));
        $days = $this->extractNetDaysFromTerms($terms);

        return $invoiceDate->copy()->addDays($days);
    }

    private function extractNetDaysFromTerms(string $terms): int
    {
        if ($terms === '') {
            return 0;
        }

        if (preg_match('/^cod$/i', $terms) === 1) {
            return 0;
        }

        if (preg_match('/net\s*(\d+)\s*days?/i', $terms, $matches) === 1) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)/', $terms, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function resolveInvoiceFallbackAccount(int $companyId): ?ChartOfAccount
    {
        return ChartOfAccount::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('account_code', '1000')
            ->first()
            ?? ChartOfAccount::getDefaultSalesForCompany($companyId);
    }

    private function resolveInvoiceFallbackAccountId(int $companyId): ?int
    {
        return $this->resolveInvoiceFallbackAccount($companyId)?->id;
    }

}