<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\EmailActivity;
use App\Models\Jobcard;
use App\Models\Note;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\TaxRate;
use App\Services\ReminderService;
use App\Support\CompanyScopedRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class QuotesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Quote::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Quote::with(['customer'])
            ->where('company_id', $currentCompany->id);

        // Hide closed (accepted/rejected) quotes unless "show closed" is checked
        $showClosed = filter_var($request->input('show_closed', false), FILTER_VALIDATE_BOOLEAN);
        if (! $showClosed) {
            $query->whereNotIn('status', ['accepted', 'rejected']);
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
                $q->where('quote_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $columnFilters = collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'colf_'))
            ->mapWithKeys(function ($value, $key) {
                $trimmed = trim((string) $value);

                return [substr((string) $key, 5) => $trimmed];
            })
            ->filter(fn ($value) => $value !== '');

        foreach ($columnFilters as $filterKey => $filterValue) {
            switch ($filterKey) {
                case 'quote':
                    $query->where('quote_number', 'like', "%{$filterValue}%");
                    break;
                case 'title':
                    $query->where('title', 'like', "%{$filterValue}%");
                    break;
                case 'customer':
                    $query->whereHas('customer', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'status':
                    $query->where('status', 'like', "%{$filterValue}%");
                    break;
                case 'expiry_date':
                    $query->where('expiry_date', 'like', "%{$filterValue}%");
                    break;
                case 'total':
                    $query->where('total', 'like', "%{$filterValue}%");
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
                case 'created':
                    $query->where('created_at', 'like', "%{$filterValue}%");
                    break;
                case 'updated':
                    $query->where('updated_at', 'like', "%{$filterValue}%");
                    break;
            }
        }

        $sortableFields = ['quote_number', 'title', 'customer_name', 'status', 'expiry_date', 'total', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        if ($sortBy === 'customer_name') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'quotes.customer_id')->limit(1),
                $sortDir
            );
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $quotes = $query->paginate(15)->withQueryString();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);

        return Inertia::render('quotes/Index', [
            'quotes' => $quotes,
            'customers' => $customers,
            'statusOptions' => $currentCompany->getQuoteStatusOptions(),
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'search' => $request->input('search', ''),
                'show_closed' => $showClosed,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'column_filters' => $columnFilters->all(),
            ],
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Quote::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);
        $defaultSalesCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);
        $prefill = null;

        if ($request->input('source_type') === 'jobcard' && $request->filled('source_id')) {
            $sourceJobcard = Jobcard::where('company_id', $currentCompany->id)
                ->with(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups'])
                ->find($request->integer('source_id'));

            if ($sourceJobcard) {
                $groupSort = $sourceJobcard->lineGroups->sortBy('sort_order')->values();
                $groupIdToIndex = [];
                foreach ($groupSort as $index => $group) {
                    $groupIdToIndex[$group->id] = $index + 1;
                }

                $prefillLineGroups = $groupSort->map(function ($group, $index) {
                    return [
                        'name' => $group->name ?: 'Items',
                        'sort_order' => $index,
                    ];
                })->values()->toArray();
                if (empty($prefillLineGroups)) {
                    $prefillLineGroups = [['name' => 'Items', 'sort_order' => 0]];
                }

                $prefillLineItems = $sourceJobcard->lineItems->sortBy('sort_order')->map(function ($item) use ($groupIdToIndex) {
                    return [
                        'product_id' => $item->product_id,
                        'line_group_id' => $groupIdToIndex[$item->line_group_id] ?? 1,
                        'description' => $item->description,
                        'quantity' => (int) ($item->quantity ?? 1),
                        'unit_price' => (float) ($item->unit_price ?? 0),
                        'discount_amount' => (float) ($item->discount_amount ?? 0),
                        'discount_percentage' => (float) ($item->discount_percentage ?? 0),
                        'tax_rate_id' => $item->tax_rate_id,
                        'account_id' => $item->account_id,
                        'total' => (float) ($item->total ?? 0),
                    ];
                })->values()->toArray();
                if (empty($prefillLineItems)) {
                    $prefillLineItems = [[
                        'product_id' => null,
                        'line_group_id' => 1,
                        'description' => '',
                        'quantity' => 1,
                        'unit_price' => 0,
                        'discount_amount' => 0,
                        'discount_percentage' => 0,
                        'tax_rate_id' => $defaultSalesTaxRate?->id,
                        'account_id' => $defaultSalesAccount?->id,
                        'total' => 0,
                    ]];
                }

                $prefill = [
                    'source_type' => 'jobcard',
                    'source_id' => $sourceJobcard->id,
                    'customer_id' => $sourceJobcard->customer_id,
                    'contact_id' => $sourceJobcard->contact_id,
                    'email' => $sourceJobcard->email,
                    'phone' => $sourceJobcard->phone,
                    'order_number' => $sourceJobcard->order_number,
                    'title' => $sourceJobcard->title,
                    'description' => $sourceJobcard->description,
                    'tax_rate' => (float) ($sourceJobcard->tax_rate ?? 0),
                    'discount_amount' => (float) ($sourceJobcard->discount_amount ?? 0),
                    'discount_percentage' => (float) ($sourceJobcard->discount_percentage ?? 0),
                    'notes' => $sourceJobcard->notes,
                    'terms_conditions' => $sourceJobcard->terms_conditions,
                    'line_groups' => $prefillLineGroups,
                    'line_items' => $prefillLineItems,
                ];

                $defaultSalesCustomer = $sourceJobcard->customer;
            }
        }

        return Inertia::render('quotes/Create', [
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_quote_terms,
            'statusOptions' => $currentCompany->getQuoteStatusOptions(),
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'defaultSalesCustomerId' => $defaultSalesCustomer?->id,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Quote::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($cid)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'in:jobcard'],
            'source_id' => ['nullable', 'integer', 'required_with:source_type'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
            'expiry_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($cid)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ]);
        $validator->after(CompanyScopedRules::afterSourceJobcardInCompany($cid));
        $validated = $validator->validate();

        // Create the quote
        $quote = Quote::create([
            'company_id' => $currentCompany->id,
            'customer_id' => $validated['customer_id'],
            'contact_id' => $validated['contact_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'source_type' => $validated['source_type'] ?? null,
            'source_id' => $validated['source_id'] ?? null,
            'quote_number' => Quote::generateQuoteNumber($currentCompany->id),
            'order_number' => $validated['order_number'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'expiry_date' => $validated['expiry_date'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'notes' => $validated['notes'],
            'terms_conditions' => $validated['terms_conditions'],
        ]);

        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $quote->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        // Create line items
        $defaultSalesAccountId = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id)?->id;
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new QuoteLineItem([
                'quote_id' => $quote->id,
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'account_id' => $lineItemData['account_id'] ?? $defaultSalesAccountId,
                'sort_order' => $index,
            ]);
            $lineItem->calculateTotal();
            $lineItem->save();
        }

        // Calculate totals
        $quote->calculateTotals();
        $quote->refresh();
        $quote->load('customer', 'company');

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService;
            $reminderService->sendQuoteCreatedConfirmation($quote);
        } catch (\Exception $e) {
            \Log::error('Failed to send quote created confirmation', [
                'quote_id' => $quote->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the quote creation if reminder fails
        }

        return redirect()->route('quotes.show', $quote)
            ->with('success', 'Quote created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Quote $quote): Response
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups', 'company', 'invoice', 'source', 'signatures.user']);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Get available PDF templates for quotes and proforma invoices
        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->whereIn('module', ['quote', 'proforma-invoice'])
            ->where('is_active', true)
            ->orderBy('module')
            ->orderBy('name')
            ->get(['id', 'name', 'module', 'is_default']);

        // Get default template IDs for both modules
        $defaultQuoteTemplateId = $pdfTemplates->where('module', 'quote')->where('is_default', true)->first()?->id ?? null;
        $defaultProformaTemplateId = $pdfTemplates->where('module', 'proforma-invoice')->where('is_default', true)->first()?->id ?? null;
        $convertedJobcardId = Jobcard::where('company_id', $currentCompany->id)
            ->where('source_type', 'quote')
            ->where('source_id', $quote->id)
            ->value('id');
        $relatedPurchaseOrders = PurchaseOrder::where('company_id', $currentCompany->id)
            ->where('source_type', 'quote')
            ->where('source_id', $quote->id)
            ->latest()
            ->get(['id', 'po_number', 'status', 'total', 'created_at']);
        $signatures = $quote->signatures->map(fn ($signature) => [
            'id' => $signature->id,
            'signer_name' => $signature->signer_name,
            'signature_url' => $signature->signature_url,
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'user_name' => $signature->user?->name,
        ])->toArray();

        return Inertia::render('quotes/Show', [
            'quote' => $quote,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
            'statusOptions' => $currentCompany->getQuoteStatusOptions(),
            'pdfTemplates' => $pdfTemplates,
            'defaultQuoteTemplateId' => $defaultQuoteTemplateId,
            'defaultProformaTemplateId' => $defaultProformaTemplateId,
            'convertedJobcardId' => $convertedJobcardId,
            'relatedPurchaseOrders' => $relatedPurchaseOrders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'status' => $po->status,
                'total' => (float) $po->total,
                'created_at' => $po->created_at?->toIso8601String(),
            ])->toArray(),
            'purchaseOrdersTotal' => (float) $relatedPurchaseOrders->sum('total'),
            'signatures' => $signatures,
            'documentSigningEnabled' => (bool) ($currentCompany->enable_document_signing ?? false),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Quote $quote): Response
    {
        $this->authorize('update', $quote);

        $currentCompany = auth()->user()->getCurrentCompany();
        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.lineGroup', 'lineGroups']);
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);

        // Pass quote as array to ensure line_items include tax_rate_id and account_id
        $quoteData = $quote->toArray();

        return Inertia::render('quotes/Edit', [
            'quote' => $quoteData,
            'customers' => $customers,
            'products' => $products,
            'currentCompany' => $currentCompany,
            'statusOptions' => $currentCompany->getQuoteStatusOptions(),
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'canEditCompleted' => auth()->user()->hasModulePermission('quotes', 'edit_completed'),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $cid = $quote->company_id;

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($cid)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
            'expiry_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($cid)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        // Update the quote
        $quote->update([
            'customer_id' => $validated['customer_id'],
            'contact_id' => $validated['contact_id'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'order_number' => $validated['order_number'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'expiry_date' => $validated['expiry_date'],
            'tax_rate' => $validated['tax_rate'] ?? 0,
            'discount_amount' => $validated['discount_amount'] ?? 0,
            'discount_percentage' => $validated['discount_percentage'] ?? 0,
            'notes' => $validated['notes'],
            'terms_conditions' => $validated['terms_conditions'],
        ]);

        // Delete existing line items
        $quote->lineItems()->delete();
        $quote->lineGroups()->delete();
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $quote->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        // Create new line items
        $defaultSalesAccountId = ChartOfAccount::getDefaultSalesForCompany($quote->company_id)?->id;
        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new QuoteLineItem([
                'quote_id' => $quote->id,
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'account_id' => $lineItemData['account_id'] ?? $defaultSalesAccountId,
                'sort_order' => $index,
            ]);
            $lineItem->calculateTotal();
            $lineItem->save();
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
        $this->authorize('delete', $quote);

        $quote->delete();

        return redirect()->route('quotes.index')
            ->with('success', 'Quote deleted successfully');
    }

    /**
     * Update quote status only
     */
    public function updateStatus(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

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
        $this->authorize('convertToJobcard', $quote);

        $currentCompany = auth()->user()->getCurrentCompany();

        $existingJobcardId = Jobcard::where('company_id', $currentCompany->id)
            ->where('source_type', 'quote')
            ->where('source_id', $quote->id)
            ->value('id');

        if ($existingJobcardId) {
            return redirect()->route('jobcards.show', $existingJobcardId)
                ->with('info', 'This quote has already been converted to a jobcard.');
        }

        return redirect()->route('jobcards.create', [
            'source_type' => 'quote',
            'source_id' => $quote->id,
        ]);
    }

    /**
     * Convert quote to invoice
     */
    public function convertToInvoice(Quote $quote): RedirectResponse
    {
        $this->authorize('convertToInvoice', $quote);

        return redirect()->route('invoices.create', [
            'source_type' => 'quote',
            'source_id' => $quote->id,
        ]);
    }

    /**
     * Download quote as PDF
     */
    public function downloadPDF(Request $request, Quote $quote)
    {
        $this->authorize('view', $quote);

        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);
        $company = $quote->company;

        // Update status to sent when PDF is downloaded
        if ($quote->status === 'draft') {
            $quote->update(['status' => 'sent']);
        }

        $company = $quote->company;
        $type = $request->get('type', 'quotation'); // 'quotation' or 'proforma-invoice'
        $templateId = $request->get('template_id');

        $module = $type === 'proforma-invoice' ? 'proforma-invoice' : 'quote';
        $filename = $type === 'proforma-invoice'
            ? "proforma-invoice-{$quote->quote_number}.pdf"
            : "quote-{$quote->quote_number}.pdf";

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf($module, compact('quote', 'company'), $company, $templateId);
        $pdfContent = $pdf->output();

        $this->storePrintedDocumentNote(
            $quote,
            $filename,
            $pdfContent,
            $type === 'proforma-invoice' ? "Proforma Invoice {$quote->quote_number} printed" : "Quote {$quote->quote_number} printed"
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Email quote to customer
     */
    public function emailQuote(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'email' => ['required', 'string'],
            'message' => ['nullable', 'string'],
            'type' => ['nullable', 'in:quotation,proforma-invoice'],
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

        $quote->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);
        $company = $quote->company;

        try {
            // Generate PDF
            $type = $validated['type'] ?? 'quotation'; // 'quotation' or 'proforma-invoice'
            $templateId = $request->get('template_id');

            $module = $type === 'proforma-invoice' ? 'proforma-invoice' : 'quote';
            $filename = $type === 'proforma-invoice'
                ? "proforma-invoice-{$quote->quote_number}.pdf"
                : "quote-{$quote->quote_number}.pdf";
            $subjectPrefix = $type === 'proforma-invoice' ? 'Proforma Invoice' : 'Quote';

            $pdfService = new \App\Services\PdfGenerationService;
            $pdf = $pdfService->generatePdf($module, compact('quote', 'company'), $company, $templateId);
            $pdfContent = $pdf->output();

            // Send email
            Mail::mailer('smtp')->send('emails.quote', [
                'quote' => $quote,
                'customMessage' => $validated['message'],
            ], function ($message) use ($emails, $quote, $pdfContent, $filename, $subjectPrefix, $company) {
                $fromName = $company?->name ?: config('mail.from.name');
                $message->to($emails)
                    ->subject("{$subjectPrefix} {$quote->quote_number} - {$quote->title}")
                    ->from(config('mail.from.address'), $fromName)
                    ->attachData($pdfContent, $filename, [
                        'mime' => 'application/pdf',
                    ]);

                if (! empty($company->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
            });

            $subject = "{$subjectPrefix} {$quote->quote_number} - {$quote->title}";
            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $currentCompany->id,
                    'customer_id' => $quote->customer_id,
                    'contact_id' => $quote->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['message'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'quote',
                    'related_id' => $quote->id,
                    'status' => 'sent',
                    'metadata' => [
                        'pdf_module' => $module,
                        'pdf_template_id' => $templateId,
                    ],
                    'sent_at' => now(),
                ]);
            }

            // Update status to sent when email is sent successfully
            if ($quote->status === 'draft') {
                $quote->update(['status' => 'sent']);
            }

            return redirect()->back()->with('success', 'Quote emailed successfully');
        } catch (\Exception $e) {
            $subject = ($validated['type'] ?? 'quotation') === 'proforma-invoice'
                ? "Proforma Invoice {$quote->quote_number} - {$quote->title}"
                : "Quote {$quote->quote_number} - {$quote->title}";
            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $currentCompany->id,
                    'customer_id' => $quote->customer_id,
                    'contact_id' => $quote->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['message'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'quote',
                    'related_id' => $quote->id,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withErrors(['message' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    public function sign(Request $request, Quote $quote): RedirectResponse
    {
        $this->authorize('update', $quote);

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
            'signatures/%d/quotes/%d/%s-%s.png',
            $quote->company_id,
            $quote->id,
            now()->format('YmdHis'),
            bin2hex(random_bytes(4))
        );
        Storage::disk('public')->put($path, $binary);

        $quote->signatures()->create([
            'company_id' => $quote->company_id,
            'user_id' => auth()->id(),
            'signer_name' => trim($validated['signer_name']),
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Signature captured successfully.');
    }

    private function storePrintedDocumentNote(Quote $quote, string $filename, string $pdfContent, string $subject): void
    {
        $companyId = (int) $quote->company_id;
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

        $note->noteable()->associate($quote);
        $note->save();
    }
}
