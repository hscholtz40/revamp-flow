<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\ChartOfAccount;
use App\Models\CreditNoteAllocation;
use App\Models\Customer;
use App\Models\EmailActivity;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Jobcard;
use App\Models\LineGroup;
use App\Models\Note;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringDocument;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\InvoiceUpsertService;
use App\Services\ReminderService;
use App\Services\StockService;
use App\Support\ColumnFilters;
use App\Support\CompanyScopedRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Invoice::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Invoice::with(['customer', 'payments', 'creditNotes'])
            ->where('company_id', $currentCompany->id);

        // Hide paid invoices by default unless explicitly requested
        if (! $request->boolean('show_paid')) {
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

        $columnFilters = ColumnFilters::fromRequest($request);

        foreach ($columnFilters as $filterKey => $filterValue) {
            switch ($filterKey) {
                case 'invoice':
                    $query->where(function ($q) use ($filterValue) {
                        $q->where('invoice_number', 'like', "%{$filterValue}%")
                            ->orWhere('title', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'customer':
                    $query->whereHas('customer', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'salesperson':
                    $query->whereHas('salesperson', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'date':
                    $query->where('invoice_date', 'like', "%{$filterValue}%");
                    break;
                case 'due_date':
                    $query->where('due_date', 'like', "%{$filterValue}%");
                    break;
                case 'status':
                    $query->where('status', 'like', "%{$filterValue}%");
                    break;
                case 'total':
                    $query->where('total', 'like', "%{$filterValue}%");
                    break;
                case 'created':
                    $query->where('created_at', 'like', "%{$filterValue}%");
                    break;
                case 'order_number':
                    $query->where('order_number', 'like', "%{$filterValue}%");
                    break;
                case 'description':
                    $query->where('description', 'like', "%{$filterValue}%");
                    break;
                case 'email':
                    $query->where('email', 'like', "%{$filterValue}%");
                    break;
                case 'phone':
                    $query->where('phone', 'like', "%{$filterValue}%");
                    break;
                case 'subtotal':
                    $query->where('subtotal', 'like', "%{$filterValue}%");
                    break;
                case 'discount':
                    $query->where('discount_amount', 'like', "%{$filterValue}%");
                    break;
                case 'tax':
                    $query->where('tax_amount', 'like', "%{$filterValue}%");
                    break;
                case 'updated':
                    $query->where('updated_at', 'like', "%{$filterValue}%");
                    break;
            }
        }

        $sortableFields = ['invoice_number', 'customer_name', 'salesperson_name', 'invoice_date', 'due_date', 'status', 'total', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        $invoicesQuery = $query->with(['customer', 'salesperson', 'source', 'source.source']);
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
        $recurringSourceOptions = Invoice::query()
            ->where('company_id', $currentCompany->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'invoice_number', 'title']);
        $recurringSourceLookup = $recurringSourceOptions->keyBy('id');
        $recurringDocuments = RecurringDocument::query()
            ->where('company_id', $currentCompany->id)
            ->where('document_type', RecurringDocument::TYPE_INVOICE)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (RecurringDocument $recurring) use ($recurringSourceLookup) {
                $source = $recurringSourceLookup->get($recurring->source_id);
                $recurring->source_label = $source ? ($source->invoice_number.' - '.$source->title) : 'Source not found';

                return $recurring;
            })
            ->values();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'customers' => $customers,
            'currentCompany' => $currentCompany,
            'recurringDocuments' => $recurringDocuments,
            'recurringSourceOptions' => $recurringSourceOptions,
            'recurringFrequencies' => [
                RecurringDocument::FREQ_DAILY,
                RecurringDocument::FREQ_WEEKLY,
                RecurringDocument::FREQ_MONTHLY,
                RecurringDocument::FREQ_QUARTERLY,
                RecurringDocument::FREQ_YEARLY,
            ],
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'search' => $request->input('search', ''),
                'show_paid' => $request->boolean('show_paid'),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'column_filters' => $columnFilters->all(),
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
        $this->authorize('create', Invoice::class);

        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->with(['serialNumbers' => function ($query) {
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

        $users = User::query()
            ->excludeClientUsers()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Pre-fill customer if provided
        $selectedCustomer = null;
        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);
        } else {
            $selectedCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);
        }
        $prefill = null;

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = $this->resolveInvoiceFallbackAccount($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);

        if (in_array($request->input('source_type'), ['quote', 'jobcard'], true) && $request->filled('source_id')) {
            $sourceType = $request->input('source_type');
            $sourceId = $request->integer('source_id');
            $source = null;

            if ($sourceType === 'quote') {
                $source = Quote::where('company_id', $currentCompany->id)
                    ->with(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups'])
                    ->find($sourceId);
            } elseif ($sourceType === 'jobcard') {
                $source = Jobcard::where('company_id', $currentCompany->id)
                    ->with(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups'])
                    ->find($sourceId);
            }

            if ($source) {
                $selectedCustomer = $source->customer;
                $sourceGroups = $source->lineGroups->sortBy('sort_order')->values();
                $groupIdToIndex = [];
                foreach ($sourceGroups as $index => $group) {
                    $groupIdToIndex[$group->id] = $index + 1;
                }

                $lineGroups = $sourceGroups->map(function ($group, $index) {
                    return [
                        'name' => $group->name ?: 'Items',
                        'sort_order' => $index,
                    ];
                })->values()->toArray();
                if (empty($lineGroups)) {
                    $lineGroups = [['name' => 'Items', 'sort_order' => 0]];
                }

                $lineItems = $source->lineItems->sortBy('sort_order')->map(function ($item) use ($groupIdToIndex) {
                    return [
                        'product_id' => $item->product_id,
                        'line_group_id' => $groupIdToIndex[$item->line_group_id] ?? 1,
                        'description' => $item->description,
                        'quantity' => (int) ($item->quantity ?? 1),
                        'unit_price' => (float) ($item->unit_price ?? 0),
                        'discount_amount' => (float) ($item->discount_amount ?? 0),
                        'discount_percentage' => (float) ($item->discount_percentage ?? 0),
                        'total' => (float) ($item->total ?? 0),
                        'tax_rate_id' => $item->tax_rate_id,
                        'account_id' => $item->account_id,
                        'serial_number_ids' => $item->serial_number_ids ?? [],
                    ];
                })->values()->toArray();
                if (empty($lineItems)) {
                    $lineItems = [[
                        'product_id' => null,
                        'line_group_id' => 1,
                        'description' => '',
                        'quantity' => 1,
                        'unit_price' => 0,
                        'discount_amount' => 0,
                        'discount_percentage' => 0,
                        'total' => 0,
                        'tax_rate_id' => $defaultSalesTaxRate?->id,
                        'account_id' => $defaultSalesAccount?->id,
                        'serial_number_ids' => [],
                    ]];
                }

                $source->loadMissing('customer');
                $prefillPaymentTerms = trim((string) ($source->customer?->terms ?? 'COD')) ?: 'COD';

                $prefill = [
                    'source_type' => $sourceType,
                    'source_id' => $source->id,
                    'title' => $source->title,
                    'description' => $source->description,
                    'customer_id' => $source->customer_id,
                    'contact_id' => $source->contact_id,
                    'email' => $source->email,
                    'phone' => $source->phone,
                    'order_number' => $source->order_number,
                    'tax_rate' => (float) ($source->tax_rate ?? 0),
                    'discount_amount' => (float) ($source->discount_amount ?? 0),
                    'discount_percentage' => (float) ($source->discount_percentage ?? 0),
                    'notes' => $source->notes,
                    'terms' => $prefillPaymentTerms,
                    'terms_conditions' => $source->terms_conditions,
                    'line_groups' => $lineGroups,
                    'line_items' => $lineItems,
                ];
            }
        }

        return Inertia::render('invoices/Create', [
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'selectedCustomer' => $selectedCustomer,
            'defaultTermsConditions' => $currentCompany->default_invoice_terms,
            'currentUser' => auth()->user(),
            'currentCompany' => $currentCompany,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'prefill' => $prefill,
        ]);
    }

    public function pos(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany || ! $currentCompany->enable_pos) {
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
            'defaultTermsConditions' => $currentCompany->default_invoice_terms,
            'printPdfUrl' => $printPdfUrl,
            'defaultSalesTaxRate' => $defaultSalesTaxRate ? [
                'id' => $defaultSalesTaxRate->id,
                'name' => $defaultSalesTaxRate->name,
                'rate' => (float) $defaultSalesTaxRate->rate,
            ] : null,
            'defaultSalesAccountLabel' => $defaultSalesAccount
                ? trim(($defaultSalesAccount->account_code ? $defaultSalesAccount->account_code.' - ' : '').$defaultSalesAccount->account_name)
                : null,
        ]);
    }

    public function storePos(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany || ! $currentCompany->enable_pos) {
            abort(403, 'POS is not enabled for this company.');
        }

        $cid = $currentCompany->id;
        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'order_number' => 'nullable|string|max:255',
            'invoice_date' => 'required|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string|max:255',
            'terms_conditions' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required|in:cash,card,eft,account',
            'amount_paid' => 'nullable|numeric|min:0',
            'tendered_amount' => 'nullable|numeric|min:0',
        ]);

        if (($validated['payment_method'] ?? '') !== 'account') {
            abort_unless(
                auth()->user()->canRecordPaymentMethod($validated['payment_method']),
                403,
                'You are not permitted to record POS payments with this method.'
            );
        }

        $customer = Customer::where('company_id', $currentCompany->id)->findOrFail($validated['customer_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date'])->startOfDay();
        $invoiceNumber = Invoice::generateInvoiceNumber($currentCompany->id);
        $dueDate = $this->resolveInvoiceDueDateFromCustomerTerms($customer, $invoiceDate);
        $defaultTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $defaultTaxRateId = $defaultTaxRate?->id;
        $defaultTaxRateRate = (float) ($defaultTaxRate?->rate ?? 0);
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);
        $terms = ! empty(trim((string) ($validated['terms'] ?? '')))
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
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ]);

            // POS invoices should always retain the currently logged-in user as salesperson.
            if (! $invoice->salesperson_id && $salespersonId) {
                $invoice->update(['salesperson_id' => $salespersonId]);
            }

            $defaultGroup = LineGroup::createDefaultFor($invoice);

            $stockService = new StockService;
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
                    $lineTaxAmount = round($total * ($defaultTaxRateRate / 100), 2);
                }

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'line_group_id' => $defaultGroup->id,
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

                if (! empty($lineItemData['product_id'])) {
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

            $this->ensureConvertedInvoiceRoundingLine($invoice, $defaultAccountId);

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
    public function store(StoreInvoiceRequest $request, InvoiceUpsertService $invoiceUpsertService): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $validated = $request->validated();

        // Generate invoice number
        $invoiceNumber = Invoice::generateInvoiceNumber($currentCompany->id);
        $customer = Customer::where('company_id', $currentCompany->id)->findOrFail($validated['customer_id']);
        $invoiceDate = Carbon::parse($validated['invoice_date'])->startOfDay();
        $dueDate = $this->resolveInvoiceDueDateFromCustomerTerms($customer, $invoiceDate);
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($currentCompany->id);
        $sourceJobcard = $this->resolveSourceJobcardForInvoiceSource(
            $currentCompany->id,
            $validated['source_type'] ?? null,
            $validated['source_id'] ?? null
        );

        // Set default salesperson to current user if not provided
        $salespersonId = $validated['salesperson_id'] ?? auth()->id();

        $invoice = $invoiceUpsertService->createForCompany(
            $validated,
            $currentCompany->id,
            $invoiceNumber,
            $dueDate->toDateString(),
            $salespersonId,
            $defaultAccountId,
            $sourceJobcard,
            fn (...$args) => $this->syncInvoiceStockAdjustments(...$args),
            fn (Invoice $targetInvoice, ?int $fallbackAccountId = null) => $this->ensureConvertedInvoiceRoundingLine($targetInvoice, $fallbackAccountId)
        );

        if (($validated['source_type'] ?? null) === 'quote' && ! empty($validated['source_id'])) {
            $sourceQuote = Quote::where('company_id', $currentCompany->id)->find($validated['source_id']);
            if ($sourceQuote) {
                $sourceQuote->update([
                    'status' => 'accepted',
                    'invoice_id' => $invoice->id,
                ]);

                if ($sourceQuote->source_type === 'jobcard' && ! empty($sourceQuote->source_id)) {
                    $sourceJobcard = Jobcard::where('company_id', $currentCompany->id)->find($sourceQuote->source_id);
                    if ($sourceJobcard) {
                        $sourceJobcard->update([
                            'invoice_id' => $invoice->id,
                        ]);
                    }
                }
            }
        } elseif (($validated['source_type'] ?? null) === 'jobcard' && ! empty($validated['source_id'])) {
            $sourceJobcard = Jobcard::where('company_id', $currentCompany->id)->find($validated['source_id']);
            if ($sourceJobcard) {
                $sourceJobcard->update([
                    'status' => 'completed',
                    'completed_date' => now(),
                    'invoice_id' => $invoice->id,
                ]);
            }
        }

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService;
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
        $this->authorize('view', $invoice);

        $currentCompany = auth()->user()->getCurrentCompany();

        $invoice->load(['customer', 'contact', 'salesperson', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups', 'company', 'source', 'source.source', 'payments', 'creditNotes', 'signatures.user']);

        // Load serial numbers for line items that have serial_number_ids
        $invoice->load('lineItems');

        // Ensure payments are loaded for accurate total_paid and remaining_balance calculations
        if (! $invoice->relationLoaded('payments')) {
            $invoice->load('payments');
        }

        // Convert invoice to array first
        $invoiceData = $invoice->toArray();

        $allocatedCreditNotes = CreditNoteAllocation::query()
            ->where('company_id', $currentCompany->id)
            ->where('invoice_id', $invoice->id)
            ->with('creditNote')
            ->get()
            ->map(function (CreditNoteAllocation $allocation) {
                $cn = $allocation->creditNote;
                if (! $cn) {
                    return null;
                }

                return [
                    'id' => $cn->id,
                    'credit_note_number' => $cn->credit_note_number,
                    'credit_note_date' => optional($cn->credit_note_date)->toDateString(),
                    'status' => $cn->status,
                    'allocated_amount' => (float) $allocation->amount,
                ];
            })
            ->filter()
            ->values();
        $invoiceData['allocated_credit_notes'] = $allocatedCreditNotes;

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

        $signatures = $invoice->signatures->map(fn ($signature) => [
            'id' => $signature->id,
            'signer_name' => $signature->signer_name,
            'signature_url' => $signature->signature_url,
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'user_name' => $signature->user?->name,
        ])->toArray();

        return Inertia::render('invoices/Show', [
            'invoice' => $invoiceData,
            'canEditInvoices' => auth()->user()->hasModulePermission('invoices', 'edit'),
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
            'signatures' => $signatures,
            'documentSigningEnabled' => (bool) ($currentCompany->enable_document_signing ?? false),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice): Response
    {
        $this->authorize('update', $invoice);

        $currentCompany = auth()->user()->getCurrentCompany();

        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.lineGroup', 'lineGroups']);

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        // Get all invoice line item serial number IDs for this invoice
        $invoiceSerialNumberIds = $invoice->lineItems->pluck('serial_number_ids')->flatten()->filter()->unique()->toArray();

        $products = Product::where('company_id', $currentCompany->id)
            ->with(['serialNumbers' => function ($query) use ($invoiceSerialNumberIds) {
                // Include available serial numbers OR sold ones that are on this invoice
                $query->where(function ($q) use ($invoiceSerialNumberIds) {
                    $q->where('status', 'available')
                        ->orWhere(function ($subQ) use ($invoiceSerialNumberIds) {
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

        $users = User::query()
            ->excludeClientUsers()
            ->orderBy('name')
            ->get(['id', 'name']);

        // Convert invoice to array and ensure serial_number_ids are included in line items
        $invoiceData = $invoice->toArray();

        // Ensure line items have serial_number_ids properly set
        if (isset($invoiceData['line_items']) && is_array($invoiceData['line_items'])) {
            foreach ($invoiceData['line_items'] as $key => $lineItemData) {
                // Make sure serial_number_ids is an array (it might be null or not set)
                if (! isset($lineItemData['serial_number_ids']) || ! is_array($lineItemData['serial_number_ids'])) {
                    $invoiceData['line_items'][$key]['serial_number_ids'] = [];
                }
            }
        }

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = $this->resolveInvoiceFallbackAccount($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);

        return Inertia::render('invoices/Edit', [
            'invoice' => $invoiceData,
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'canEditInvoices' => auth()->user()->hasModulePermission('invoices', 'edit'),
            'canEditSalesperson' => auth()->user()->canEditSalesperson('invoices'),
            'canEditCompleted' => auth()->user()->hasModulePermission('invoices', 'edit_completed'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceUpsertService $invoiceUpsertService): RedirectResponse
    {
        $validated = $request->validated();

        // Check if user can edit salesperson
        $canEditSalesperson = auth()->user()->canEditSalesperson('invoices');
        $sourceJobcard = $this->resolveSourceJobcardForInvoice($invoice);
        $defaultAccountId = $this->resolveInvoiceFallbackAccountId($invoice->company_id);
        $invoice = $invoiceUpsertService->update(
            $invoice,
            $validated,
            $canEditSalesperson,
            $defaultAccountId,
            $sourceJobcard,
            fn (...$args) => $this->syncInvoiceStockAdjustments(...$args),
            fn (Invoice $targetInvoice, ?int $fallbackAccountId = null) => $this->ensureConvertedInvoiceRoundingLine($targetInvoice, $fallbackAccountId)
        );

        return redirect()->route('invoices.show', $invoice)
            ->with('success', 'Invoice updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorize('delete', $invoice);

        $currentCompany = auth()->user()->getCurrentCompany();

        $sourceJobcard = $this->resolveSourceJobcardForInvoice($invoice);

        // Restore serial numbers for all line items before deleting
        // Skip if invoice is cancelled (serials were already restored when cancelled)
        if ($invoice->status !== 'cancelled') {
            $invoice->load('lineItems.product');

            foreach ($invoice->lineItems as $lineItem) {
                // Restore serial numbers to available status
                if (! empty($lineItem->serial_number_ids)) {
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
            }
        }

        $this->syncInvoiceStockAdjustments(
            $invoice->company_id,
            $sourceJobcard,
            $invoice->status !== 'cancelled' ? $invoice->lineItems : null,
            null,
            "Invoice Deleted: {$invoice->invoice_number}",
            'Stock synced due to invoice deletion',
            $invoice->id
        );

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    /**
     * Update the status of the invoice.
     */
    public function updateStatus(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'status' => 'required|in:draft,sent,paid,overdue,cancelled',
        ]);

        $oldStatus = $invoice->status;
        $newStatus = $validated['status'];

        // Handle stock adjustments based on status changes
        $sourceJobcard = $this->resolveSourceJobcardForInvoice($invoice);
        $invoice->load('lineItems.product');

        // If changing TO cancelled, restore stock and serial numbers
        if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
            foreach ($invoice->lineItems as $lineItem) {
                // Restore serial numbers to available status
                if (! empty($lineItem->serial_number_ids)) {
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

            }

            $this->syncInvoiceStockAdjustments(
                $invoice->company_id,
                $sourceJobcard,
                $invoice->lineItems,
                null,
                "Invoice Cancelled: {$invoice->invoice_number}",
                'Stock synced due to invoice cancellation',
                $invoice->id
            );
        }

        // If changing FROM cancelled TO another status, deduct stock again
        if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
            $this->syncInvoiceStockAdjustments(
                $invoice->company_id,
                $sourceJobcard,
                null,
                $invoice->lineItems,
                "Invoice Status Changed: {$invoice->invoice_number}",
                "Stock synced - invoice status changed from cancelled to {$newStatus}",
                $invoice->id
            );
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
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'source', 'source.source', 'signatures']);

        // Load serial numbers for line items that have serial_number_ids
        foreach ($invoice->lineItems as $lineItem) {
            if (! empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        $invoice->setRelation('lineItemsForRoundingTotals', $invoice->lineItems->values());
        $invoice->setRelation('lineItems', $invoice->lineItems->reject(function ($item) {
            return strtolower(trim((string) ($item->description ?? ''))) === 'rounding adjustment';
        })->values());

        $company = $invoice->company;
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);
        $pdfContent = $pdf->output();
        $filename = "invoice-{$invoice->invoice_number}.pdf";

        $this->storePrintedDocumentNote($invoice, $filename, $pdfContent, "Invoice {$invoice->invoice_number} printed");

        // Update status to sent if it was draft
        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'sent']);
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Stream printable PDF of the invoice.
     */
    public function printPdf(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'source', 'source.source', 'signatures']);

        foreach ($invoice->lineItems as $lineItem) {
            if (! empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        $invoice->setRelation('lineItemsForRoundingTotals', $invoice->lineItems->values());
        $invoice->setRelation('lineItems', $invoice->lineItems->reject(function ($item) {
            return strtolower(trim((string) ($item->description ?? ''))) === 'rounding adjustment';
        })->values());

        $company = $invoice->company;
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);

        $filename = "invoice-{$invoice->invoice_number}.pdf";
        $pdfContent = $pdf->output();

        $this->storePrintedDocumentNote($invoice, $filename, $pdfContent, "Invoice {$invoice->invoice_number} printed");

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Email the invoice.
     */
    public function email(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $validated = $request->validate([
            'email' => 'required|string',
            'customMessage' => 'nullable|string',
        ]);

        $emails = array_unique(array_filter(array_map('trim', explode(',', $validated['email']))));
        foreach ($emails as $e) {
            if (! filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return redirect()->back()->withErrors(['email' => "Invalid email address: {$e}"]);
            }
        }
        if (empty($emails)) {
            return redirect()->back()->withErrors(['email' => 'At least one valid email is required.']);
        }

        $invoice->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'company', 'signatures']);
        $company = $invoice->company;

        // Load serial numbers for line items that have serial_number_ids
        foreach ($invoice->lineItems as $lineItem) {
            if (! empty($lineItem->serial_number_ids)) {
                $lineItem->serialNumbers = \App\Models\ProductSerialNumber::whereIn('id', $lineItem->serial_number_ids)
                    ->get(['id', 'serial_number', 'status']);
            } else {
                $lineItem->serialNumbers = collect([]);
            }
        }
        $invoice->setRelation('lineItemsForRoundingTotals', $invoice->lineItems->values());
        $invoice->setRelation('lineItems', $invoice->lineItems->reject(function ($item) {
            return strtolower(trim((string) ($item->description ?? ''))) === 'rounding adjustment';
        })->values());

        try {
            // Generate PDF
            $templateId = $request->get('template_id');

            $pdfService = new \App\Services\PdfGenerationService;
            $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company, $templateId);
            $pdfContent = $pdf->output();

            // Send email
            Mail::mailer('smtp')->send('emails.invoice', [
                'invoice' => $invoice,
                'customMessage' => $validated['customMessage'],
            ], function ($message) use ($emails, $invoice, $pdfContent, $company) {
                $fromName = $company?->name ?: config('mail.from.name');
                $message->to($emails)
                    ->subject("Invoice {$invoice->invoice_number} - {$invoice->title}")
                    ->from(config('mail.from.address'), $fromName)
                    ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);

                if (! empty($company->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
            });

            $subject = "Invoice {$invoice->invoice_number} - {$invoice->title}";
            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $invoice->company_id,
                    'customer_id' => $invoice->customer_id,
                    'contact_id' => $invoice->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['customMessage'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'invoice',
                    'related_id' => $invoice->id,
                    'status' => 'sent',
                    'metadata' => [
                        'pdf_template_id' => $templateId,
                    ],
                    'sent_at' => now(),
                ]);
            }

            // Update status to sent if it was draft
            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'sent']);
            }

            return redirect()->back()->with('success', 'Invoice sent successfully to '.implode(', ', $emails));
        } catch (\Exception $e) {
            $subject = "Invoice {$invoice->invoice_number} - {$invoice->title}";
            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $invoice->company_id,
                    'customer_id' => $invoice->customer_id,
                    'contact_id' => $invoice->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['customMessage'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'invoice',
                    'related_id' => $invoice->id,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withErrors(['message' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    public function sign(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorize('update', $invoice);

        $currentCompany = auth()->user()->getCurrentCompany();
        if (! $currentCompany || ! $currentCompany->enable_document_signing) {
            return redirect()->back()->withErrors(['signature' => 'Document signing is disabled for this company.']);
        }

        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:255'],
            'signature_data' => ['required', 'string'],
        ]);

        if (! preg_match('/^data:image\/png;base64,/', $validated['signature_data'])) {
            return redirect()->back()->withErrors(['signature_data' => 'Invalid signature format.']);
        }

        $raw = substr($validated['signature_data'], strpos($validated['signature_data'], ',') + 1);
        $binary = base64_decode($raw, true);
        if ($binary === false) {
            return redirect()->back()->withErrors(['signature_data' => 'Invalid signature data.']);
        }

        $path = sprintf(
            'signatures/%d/invoices/%d/%s-%s.png',
            $invoice->company_id,
            $invoice->id,
            now()->format('YmdHis'),
            bin2hex(random_bytes(4))
        );
        Storage::disk('public')->put($path, $binary);

        $invoice->signatures()->create([
            'company_id' => $invoice->company_id,
            'user_id' => auth()->id(),
            'signer_name' => trim($validated['signer_name']),
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Signature captured successfully.');
    }

    /**
     * Convert a quote to an invoice.
     */
    public function convertFromQuote(Quote $quote): RedirectResponse
    {
        return redirect()->route('invoices.create', [
            'source_type' => 'quote',
            'source_id' => $quote->id,
        ]);
    }

    /**
     * Convert a jobcard to an invoice.
     */
    public function convertFromJobcard(Jobcard $jobcard): RedirectResponse
    {
        return redirect()->route('invoices.create', [
            'source_type' => 'jobcard',
            'source_id' => $jobcard->id,
        ]);
    }

    public function storeRecurring(Request $request): RedirectResponse
    {
        $this->authorize('create', Invoice::class);
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'source_id' => ['required', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $source = Invoice::query()
            ->where('company_id', $currentCompany->id)
            ->findOrFail((int) $validated['source_id']);

        RecurringDocument::create([
            'company_id' => $currentCompany->id,
            'document_type' => RecurringDocument::TYPE_INVOICE,
            'source_id' => $source->id,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'next_run_date' => $validated['start_date'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('invoices.index')
            ->with('success', 'Recurring invoice added.');
    }

    public function destroyRecurring(int $recurringDocument): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        RecurringDocument::query()
            ->where('company_id', $currentCompany->id)
            ->where('document_type', RecurringDocument::TYPE_INVOICE)
            ->findOrFail($recurringDocument)
            ->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Recurring invoice removed.');
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

    private function ensureConvertedInvoiceRoundingLine(Invoice $invoice, ?int $fallbackAccountId = null): void
    {
        $roundingDescription = 'Rounding Adjustment';

        try {
            $invoice->loadMissing('lineItems');

            $baseItems = $invoice->lineItems->reject(
                fn ($item) => Invoice::isRoundingAdjustmentLineItem($item->description ?? null)
            );

            $baseSubtotal = (float) $baseItems->sum(fn ($item) => (float) ($item->total ?? 0));
            $baseTax = (float) $baseItems->sum(fn ($item) => (float) ($item->tax_amount ?? 0));
            $baseTotal = $baseSubtotal + $baseTax;
            $roundedTargetTotal = round($baseTotal * 10) / 10;
            $adjustment = round($roundedTargetTotal - $baseTotal, 2);

            $roundingAccountId = ChartOfAccount::getDefaultRoundingForCompany($invoice->company_id)?->id ?? $fallbackAccountId;
            $defaultLineGroupId = $invoice->lineGroups()->orderBy('sort_order')->value('id');

            // Remove every rounding row so client + server cannot stack duplicates (only the first match was updated before).
            InvoiceLineItem::where('invoice_id', $invoice->id)
                ->whereRaw('LOWER(TRIM(description)) = ?', [strtolower($roundingDescription)])
                ->delete();

            $invoice->unsetRelation('lineItems');

            if (abs($adjustment) < 0.0001 || ! $roundingAccountId) {
                return;
            }

            $nextSortOrder = ((int) $invoice->lineItems()->max('sort_order')) + 1;

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => null,
                'description' => $roundingDescription,
                'quantity' => 1,
                'unit_price' => $adjustment,
                'discount_amount' => 0,
                'discount_percentage' => 0,
                'total' => $adjustment,
                'tax_rate_id' => null,
                'tax_amount' => 0,
                'account_id' => $roundingAccountId,
                'line_group_id' => $defaultLineGroupId,
                'sort_order' => $nextSortOrder,
            ]);
        } finally {
            $invoice->unsetRelation('lineItems');
        }
    }

    private function resolveSourceJobcardForInvoice(Invoice $invoice): ?Jobcard
    {
        return $this->resolveSourceJobcardForInvoiceSource(
            $invoice->company_id,
            $invoice->source_type,
            $invoice->source_id
        );
    }

    private function resolveSourceJobcardForInvoiceSource(int $companyId, ?string $sourceType, ?int $sourceId): ?Jobcard
    {
        if (! $sourceType || ! $sourceId) {
            return null;
        }

        if ($sourceType === 'jobcard') {
            return Jobcard::where('company_id', $companyId)
                ->with('lineItems')
                ->find($sourceId);
        }

        if ($sourceType !== 'quote') {
            return null;
        }

        $sourceQuote = Quote::where('company_id', $companyId)->find($sourceId);
        if (! $sourceQuote || $sourceQuote->source_type !== 'jobcard' || ! $sourceQuote->source_id) {
            return null;
        }

        return Jobcard::where('company_id', $companyId)
            ->with('lineItems')
            ->find($sourceQuote->source_id);
    }

    private function syncInvoiceStockAdjustments(
        int $companyId,
        ?Jobcard $sourceJobcard,
        ?iterable $previousLineItems,
        ?iterable $nextLineItems,
        string $reference,
        string $notes,
        int $invoiceId
    ): void {
        $stockService = new StockService;
        $baselineQuantities = $this->getTrackedProductQuantities($sourceJobcard?->lineItems ?? []);
        $previousExtraQuantities = $previousLineItems === null
            ? []
            : $this->getInvoiceExtraStockQuantities($previousLineItems, $baselineQuantities);
        $nextExtraQuantities = $nextLineItems === null
            ? []
            : $this->getInvoiceExtraStockQuantities($nextLineItems, $baselineQuantities);

        $productIds = array_values(array_unique(array_merge(
            array_keys($previousExtraQuantities),
            array_keys($nextExtraQuantities)
        )));

        foreach ($productIds as $productId) {
            $previousExtra = (int) ($previousExtraQuantities[$productId] ?? 0);
            $nextExtra = (int) ($nextExtraQuantities[$productId] ?? 0);
            $quantityChange = $nextExtra - $previousExtra;

            if ($quantityChange === 0) {
                continue;
            }

            $product = Product::where('company_id', $companyId)->find($productId);
            if (! $product || ! $product->track_stock) {
                continue;
            }

            try {
                if ($quantityChange > 0) {
                    $stockService->removeStock(
                        $product,
                        $quantityChange,
                        $reference,
                        'invoice',
                        $invoiceId,
                        $notes
                    );
                } else {
                    $stockService->addStock(
                        $product,
                        abs($quantityChange),
                        null,
                        $reference,
                        'invoice',
                        $invoiceId,
                        $notes
                    );
                }
            } catch (\Exception $e) {
                Log::warning('Invoice stock sync skipped for product delta', [
                    'invoice_id' => $invoiceId,
                    'product_id' => $productId,
                    'baseline_quantity' => (int) ($baselineQuantities[$productId] ?? 0),
                    'previous_extra_quantity' => $previousExtra,
                    'next_extra_quantity' => $nextExtra,
                    'quantity_change' => $quantityChange,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getInvoiceExtraStockQuantities(iterable $lineItems, array $baselineQuantities): array
    {
        $invoiceQuantities = $this->getTrackedProductQuantities($lineItems);
        $productIds = array_values(array_unique(array_merge(
            array_keys($invoiceQuantities),
            array_keys($baselineQuantities)
        )));
        $extraQuantities = [];

        foreach ($productIds as $productId) {
            $extraQuantity = (int) ($invoiceQuantities[$productId] ?? 0) - (int) ($baselineQuantities[$productId] ?? 0);
            if ($extraQuantity !== 0) {
                $extraQuantities[$productId] = $extraQuantity;
            }
        }

        return $extraQuantities;
    }

    private function getTrackedProductQuantities(iterable $lineItems): array
    {
        $quantities = [];

        foreach ($lineItems as $lineItem) {
            $productId = (int) (is_array($lineItem) ? ($lineItem['product_id'] ?? 0) : ($lineItem->product_id ?? 0));
            $quantity = (int) (is_array($lineItem) ? ($lineItem['quantity'] ?? 0) : ($lineItem->quantity ?? 0));

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $quantities[$productId] = (int) ($quantities[$productId] ?? 0) + $quantity;
        }

        return $quantities;
    }

    private function calculateInvoiceLineTaxAmount(float $lineTotal, $taxRateId): float
    {
        if (! $taxRateId) {
            return 0.0;
        }

        $rate = (float) (TaxRate::find($taxRateId)?->rate ?? 0);
        if ($rate <= 0) {
            return 0.0;
        }

        return round($lineTotal * ($rate / 100), 2);
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

    private function storePrintedDocumentNote(Invoice $invoice, string $filename, string $pdfContent, string $subject): void
    {
        $companyId = (int) $invoice->company_id;
        $path = sprintf(
            'notes/%d/printed/%s-%s',
            $companyId,
            now()->format('YmdHis'),
            $filename
        );

        Storage::disk('public')->put($path, $pdfContent);

        $note = new Note([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'subject' => $subject,
            'description' => null,
            'attachment_path' => $path,
            'attachment_original_name' => $filename,
            'attachment_mime' => 'application/pdf',
            'attachment_size' => strlen($pdfContent),
        ]);

        $note->noteable()->associate($invoice);
        $note->save();
    }
}
