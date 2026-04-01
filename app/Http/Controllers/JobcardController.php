<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\EmailActivity;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\Note;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringDocument;
use App\Models\TaxRate;
use App\Models\Team;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\StockService;
use App\Support\CompanyScopedRules;
use App\Support\SafeLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Jobcard::with(['customer', 'assignedUser', 'assignedTeam', 'invoice'])
            ->where('company_id', $currentCompany->id);

        // Hide closed (completed/cancelled) jobcards unless "show closed" is checked
        $showClosed = filter_var($request->input('show_closed', false), FILTER_VALIDATE_BOOLEAN);
        if (! $showClosed) {
            $query->whereNotIn('status', ['completed', 'cancelled']);
        }

        // Limited users can only see jobcards assigned to them or their teams
        if (auth()->user()->isLimitedUser()) {
            $teamIds = auth()->user()->teams()->pluck('teams.id');
            $query->where(function ($q) use ($teamIds) {
                $q->where('assigned_to_user_id', auth()->id());
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('assigned_to_team_id', $teamIds);
                }
            });
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('assigned_to_user_id')) {
            $query->where('assigned_to_user_id', $request->assigned_to_user_id);
        }

        if ($request->filled('assigned_to_team_id')) {
            $query->where('assigned_to_team_id', $request->assigned_to_team_id);
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

        $columnFilters = collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'colf_'))
            ->mapWithKeys(function ($value, $key) {
                $trimmed = trim((string) $value);

                return [substr((string) $key, 5) => $trimmed];
            })
            ->filter(fn ($value) => $value !== '');

        foreach ($columnFilters as $filterKey => $filterValue) {
            switch ($filterKey) {
                case 'job_number':
                    $query->where('job_number', 'like', "%{$filterValue}%");
                    break;
                case 'title':
                    $query->where('title', 'like', "%{$filterValue}%");
                    break;
                case 'customer':
                    $query->whereHas('customer', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'invoice':
                    $query->whereHas('invoice', function ($q) use ($filterValue) {
                        $q->where('invoice_number', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'assigned_to':
                    $query->where(function ($q) use ($filterValue) {
                        $q->whereHas('assignedUser', function ($userQuery) use ($filterValue) {
                            $userQuery->where('name', 'like', "%{$filterValue}%");
                        })->orWhereHas('assignedTeam', function ($teamQuery) use ($filterValue) {
                            $teamQuery->where('name', 'like', "%{$filterValue}%");
                        });
                    });
                    break;
                case 'status':
                    $query->where('status', 'like', "%{$filterValue}%");
                    break;
                case 'due_date':
                    $query->where('due_date', 'like', "%{$filterValue}%");
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
                case 'start_date':
                    $query->where('start_date', 'like', "%{$filterValue}%");
                    break;
                case 'completed_date':
                    $query->where('completed_date', 'like', "%{$filterValue}%");
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

        $sortableFields = ['job_number', 'title', 'customer_name', 'status', 'due_date', 'total', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        if ($sortBy === 'customer_name') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'jobcards.customer_id')->limit(1),
                $sortDir
            );
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $jobcards = $query->paginate(15)->withQueryString();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $recurringSourceOptions = Jobcard::query()
            ->where('company_id', $currentCompany->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'job_number', 'title']);
        $recurringSourceLookup = $recurringSourceOptions->keyBy('id');
        $recurringDocuments = RecurringDocument::query()
            ->where('company_id', $currentCompany->id)
            ->where('document_type', RecurringDocument::TYPE_JOBCARD)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (RecurringDocument $recurring) use ($recurringSourceLookup) {
                $source = $recurringSourceLookup->get($recurring->source_id);
                $recurring->source_label = $source ? ($source->job_number.' - '.$source->title) : 'Source not found';

                return $recurring;
            })
            ->values();

        return Inertia::render('jobcards/Index', [
            'jobcards' => $jobcards,
            'customers' => $customers,
            'users' => $users,
            'teams' => $teams,
            'recurringDocuments' => $recurringDocuments,
            'recurringSourceOptions' => $recurringSourceOptions,
            'recurringFrequencies' => [
                RecurringDocument::FREQ_DAILY,
                RecurringDocument::FREQ_WEEKLY,
                RecurringDocument::FREQ_MONTHLY,
                RecurringDocument::FREQ_QUARTERLY,
                RecurringDocument::FREQ_YEARLY,
            ],
            'statusOptions' => $currentCompany->getJobcardStatusOptions(),
            'filters' => [
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'assigned_to_user_id' => $request->input('assigned_to_user_id', ''),
                'assigned_to_team_id' => $request->input('assigned_to_team_id', ''),
                'search' => $request->input('search', ''),
                'show_closed' => $showClosed,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'column_filters' => $columnFilters->all(),
            ],
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);
        $defaultSalesCustomer = Customer::getDefaultSalesForCompany($currentCompany->id);
        $prefill = null;

        if ($request->input('source_type') === 'quote' && $request->filled('source_id')) {
            $sourceQuote = Quote::where('company_id', $currentCompany->id)
                ->with(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups'])
                ->find($request->integer('source_id'));

            if ($sourceQuote) {
                $groupSort = $sourceQuote->lineGroups->sortBy('sort_order')->values();
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

                $prefillLineItems = $sourceQuote->lineItems->sortBy('sort_order')->map(function ($item) use ($groupIdToIndex) {
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
                    ]];
                }

                $prefill = [
                    'source_type' => 'quote',
                    'source_id' => $sourceQuote->id,
                    'customer_id' => $sourceQuote->customer_id,
                    'contact_id' => $sourceQuote->contact_id,
                    'email' => $sourceQuote->email,
                    'phone' => $sourceQuote->phone,
                    'order_number' => $sourceQuote->order_number,
                    'title' => $sourceQuote->title,
                    'description' => $sourceQuote->description,
                    'tax_rate' => (float) ($sourceQuote->tax_rate ?? 0),
                    'discount_amount' => (float) ($sourceQuote->discount_amount ?? 0),
                    'discount_percentage' => (float) ($sourceQuote->discount_percentage ?? 0),
                    'notes' => $sourceQuote->notes,
                    'terms_conditions' => $sourceQuote->terms_conditions,
                    'line_groups' => $prefillLineGroups,
                    'line_items' => $prefillLineItems,
                ];

                $defaultSalesCustomer = $sourceQuote->customer;
            }
        }

        return Inertia::render('jobcards/Create', [
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'teams' => $teams,
            'statusOptions' => $currentCompany->getJobcardStatusOptions(),
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'defaultSalesCustomerId' => $defaultSalesCustomer?->id,
            'currentCompany' => $currentCompany,
            'defaultTerms' => $currentCompany->default_jobcard_terms,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($cid)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'in:quote'],
            'source_id' => ['nullable', 'integer', 'required_with:source_type'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', CompanyScopedRules::team($cid)],
            'order_number' => ['nullable', 'string', 'max:255'],
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
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        if (($validated['source_type'] ?? null) === 'quote' && ! empty($validated['source_id'])) {
            $sourceQuote = Quote::where('company_id', $currentCompany->id)->find($validated['source_id']);
            if (! $sourceQuote) {
                return back()
                    ->withInput()
                    ->withErrors(['source_id' => 'Selected source quote is invalid.']);
            }
        }

        $validated['company_id'] = $currentCompany->id;
        $validated['job_number'] = Jobcard::generateJobNumber($currentCompany->id);
        $validated['order_number'] = $validated['order_number'] ?? null;
        $validated['contact_id'] = $validated['contact_id'] ?? null;
        $validated['email'] = $validated['email'] ?? null;
        $validated['phone'] = $validated['phone'] ?? null;
        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $lineItemsPayload = $validated['line_items'] ?? [];
        unset($validated['line_groups'], $validated['line_items']);

        $jobcard = Jobcard::create($validated);

        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $jobcard->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        // Create line items and deduct stock
        $stockService = new StockService;
        foreach ($lineItemsPayload as $index => $lineItemData) {
            $lineItem = new JobcardLineItem([
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'sort_order' => $index,
            ]);
            $lineItem->calculateTotal();
            $jobcard->lineItems()->save($lineItem);

            // Deduct stock if product is tracked
            if ($lineItemData['product_id']) {
                try {
                    $product = Product::find($lineItemData['product_id']);
                    if ($product && $product->track_stock) {
                        $stockService->removeStock(
                            $product,
                            $lineItemData['quantity'],
                            "Jobcard: {$jobcard->job_number}",
                            'jobcard',
                            $jobcard->id,
                            "Stock deducted for jobcard {$jobcard->job_number}"
                        );
                    }
                } catch (\Exception $e) {
                    Log::warning('Stock deduction skipped for jobcard line item', [
                        'jobcard_id' => $jobcard->id,
                        'product_id' => $lineItemData['product_id'],
                        'quantity' => $lineItemData['quantity'],
                        'error' => $e->getMessage(),
                    ]);
                    // Continue even if stock deduction fails
                }
            }
        }

        // Calculate totals
        $jobcard->calculateTotals();

        // Send automated reminder if enabled
        try {
            $reminderService = new ReminderService;
            $reminderService->sendJobcardCreatedConfirmation($jobcard);
        } catch (\Exception $e) {
            Log::error('Failed to send jobcard created confirmation', [
                'jobcard_id' => $jobcard->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the jobcard creation if reminder fails
        }

        return redirect()->route('jobcards.show', $jobcard)
            ->with('success', 'Jobcard created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Jobcard $jobcard): Response
    {
        $this->authorize('view', $jobcard);

        $jobcard->load([
            'customer',
            'contact',
            'assignedUser',
            'assignedTeam',
            'lineItems.product',
            'lineItems.taxRate',
            'lineItems.lineGroup',
            'lineGroups',
            'invoice',
            'source',
            'timeEntries.user',
            'statusTransitions.user',
            'signatures.user',
        ]);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Get available PDF templates for jobcards
        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->where('module', 'jobcard')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $defaultTemplateId = $pdfTemplates->where('is_default', true)->first()?->id ?? null;
        $convertedQuoteId = Quote::where('company_id', $currentCompany->id)
            ->where('source_type', 'jobcard')
            ->where('source_id', $jobcard->id)
            ->value('id');

        $authUser = auth()->user();
        $relatedPurchaseOrders = $authUser->hasModulePermission('purchase-orders', 'list')
            ? PurchaseOrder::where('company_id', $currentCompany->id)
                ->where('source_type', 'jobcard')
                ->where('source_id', $jobcard->id)
                ->latest()
                ->get(['id', 'po_number', 'status', 'total', 'created_at'])
            : collect();

        // Get running timer for current user and this jobcard
        $runningTimer = \App\Models\TimeEntry::getRunningEntry(auth()->id(), $jobcard->id);

        // Calculate time summary
        $timeSummary = [
            'total_hours' => $jobcard->timeEntries->sum('duration_minutes') / 60,
            'billable_hours' => $jobcard->timeEntries->where('is_billable', true)->sum('duration_minutes') / 60,
            'total_amount' => $jobcard->timeEntries->where('is_billable', true)->sum('total_amount'),
        ];

        $statusDurations = collect($jobcard->getStatusDurations())
            ->map(fn ($minutes) => [
                'minutes' => $minutes,
                'formatted' => $this->formatDuration((int) $minutes),
            ])->toArray();

        $statusTransitions = $jobcard->statusTransitions->map(fn ($transition) => [
            'from_status' => $transition->from_status,
            'to_status' => $transition->to_status,
            'transitioned_at' => $transition->transitioned_at?->toIso8601String(),
            'user_name' => $transition->user?->name ?? 'System',
        ])->toArray();

        $signatures = $jobcard->signatures->map(fn ($signature) => [
            'id' => $signature->id,
            'signer_name' => $signature->signer_name,
            'signature_url' => $signature->signature_url,
            'signed_at' => $signature->signed_at?->toIso8601String(),
            'user_name' => $signature->user?->name,
        ])->toArray();

        return Inertia::render('jobcards/Show', [
            'jobcard' => $jobcard,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
            'statusOptions' => $currentCompany->getJobcardStatusOptions(),
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
            'convertedQuoteId' => $convertedQuoteId,
            'relatedPurchaseOrders' => $relatedPurchaseOrders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'status' => $po->status,
                'total' => (float) $po->total,
                'created_at' => $po->created_at?->toIso8601String(),
            ])->toArray(),
            'purchaseOrdersTotal' => (float) $relatedPurchaseOrders->sum('total'),
            'runningTimer' => $runningTimer,
            'timeSummary' => $timeSummary,
            'statusDurations' => $statusDurations,
            'statusTransitions' => $statusTransitions,
            'signatures' => $signatures,
            'documentSigningEnabled' => (bool) ($currentCompany->enable_document_signing ?? false),
        ]);
    }

    /**
     * Format minutes into a human-readable duration with days, hours, minutes.
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        if ($hours > 0) {
            $parts[] = "{$hours}h";
        }
        if ($mins > 0) {
            $parts[] = "{$mins}m";
        }

        return implode(' ', $parts);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Jobcard $jobcard): Response
    {
        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && ! auth()->user()->canEditCompletedJobcards()) {
            return redirect()->route('jobcards.show', $jobcard)
                ->with('error', 'You do not have permission to edit completed jobcards.');
        }

        $currentCompany = auth()->user()->getCurrentCompany();
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name', 'email', 'phone', 'account_code']);
        $products = Product::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'price', 'type']);
        $users = User::whereHas('companies', function ($q) use ($currentCompany) {
            $q->where('company_id', $currentCompany->id);
        })->orWhereDoesntHave('companies')->orderBy('name')->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_sales']);
        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);
        $defaultRoundingAccount = ChartOfAccount::getDefaultRoundingForCompany($currentCompany->id);
        $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.lineGroup', 'lineGroups']);

        return Inertia::render('jobcards/Edit', [
            'jobcard' => $jobcard->toArray(),
            'customers' => $customers,
            'products' => $products,
            'users' => $users,
            'teams' => $teams,
            'statusOptions' => $currentCompany->getJobcardStatusOptions(),
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'defaultRoundingAccountId' => $defaultRoundingAccount?->id,
            'currentCompany' => $currentCompany,
            'canEditCompleted' => auth()->user()->canEditCompletedJobcards(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $cid = $jobcard->company_id;

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($cid)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'assigned_to_team_id' => ['nullable', CompanyScopedRules::team($cid)],
            'order_number' => ['nullable', 'string', 'max:255'],
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
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.id' => ['nullable', CompanyScopedRules::jobcardLineItemForJobcard($jobcard->id)],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($cid)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        $validated['tax_rate'] = $validated['tax_rate'] ?? 0;
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $lineItemsPayload = $validated['line_items'] ?? [];
        unset($validated['line_groups'], $validated['line_items']);

        $jobcard->update($validated);
        $jobcard->lineGroups()->delete();
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $jobcard->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        // Update line items
        $existingLineItemIds = [];
        foreach ($lineItemsPayload as $index => $lineItemData) {
            if (isset($lineItemData['id'])) {
                // Update existing line item
                $lineItem = JobcardLineItem::find($lineItemData['id']);
                $groupId = $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId;
                $lineItem->update([
                    'line_group_id' => $groupId,
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                    'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                    'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                    'account_id' => $lineItemData['account_id'] ?? null,
                    'sort_order' => $index,
                ]);
                $lineItem->calculateTotal();
                $lineItem->save();
                $existingLineItemIds[] = $lineItem->id;
            } else {
                // Create new line item
                $lineItem = new JobcardLineItem([
                    'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                    'product_id' => $lineItemData['product_id'] ?? null,
                    'description' => $lineItemData['description'],
                    'quantity' => $lineItemData['quantity'],
                    'unit_price' => $lineItemData['unit_price'],
                    'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                    'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                    'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                    'account_id' => $lineItemData['account_id'] ?? null,
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
        $this->authorize('updateStatus', $jobcard);

        $request->validate([
            'status' => 'required|in:draft,pending,in_progress,completed,cancelled',
        ]);

        $newStatus = $request->status;

        // Check if user can edit completed jobcards
        if ($jobcard->status === 'completed' && $newStatus !== 'completed') {
            if (! auth()->user()->canEditCompletedJobcards()) {
                return redirect()->back()
                    ->with('error', 'You do not have permission to edit completed jobcards.');
            }
        }

        $oldStatus = $jobcard->status;
        $jobcard->status = $newStatus;

        // Set completed_date if status is completed
        if ($newStatus === 'completed' && ! $jobcard->completed_date) {
            $jobcard->completed_date = now();
        }

        $jobcard->save();

        // Send automated reminder if enabled and status actually changed
        if ($oldStatus !== $newStatus) {
            try {
                $reminderService = new ReminderService;
                $reminderService->sendJobcardStatusUpdatedConfirmation($jobcard, $oldStatus);
            } catch (\Exception $e) {
                Log::error('Failed to send jobcard status updated confirmation', [
                    'jobcard_id' => $jobcard->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the status update if reminder fails
            }
        }

        return redirect()->back()
            ->with('success', "Jobcard status updated to {$newStatus}");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jobcard $jobcard): RedirectResponse
    {
        // Check if user can delete completed jobcards
        if ($jobcard->status === 'completed' && ! auth()->user()->canEditCompletedJobcards()) {
            return redirect()->back()
                ->with('error', 'You do not have permission to delete completed jobcards.');
        }

        $jobcard->delete();

        return redirect()->route('jobcards.index')
            ->with('success', 'Jobcard deleted successfully');
    }

    public function storeRecurring(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'source_id' => ['required', 'integer'],
            'frequency' => ['required', 'in:daily,weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $source = Jobcard::query()
            ->where('company_id', $currentCompany->id)
            ->findOrFail((int) $validated['source_id']);

        RecurringDocument::create([
            'company_id' => $currentCompany->id,
            'document_type' => RecurringDocument::TYPE_JOBCARD,
            'source_id' => $source->id,
            'frequency' => $validated['frequency'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'next_run_date' => $validated['start_date'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('jobcards.index')
            ->with('success', 'Recurring jobcard added.');
    }

    public function destroyRecurring(int $recurringDocument): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        RecurringDocument::query()
            ->where('company_id', $currentCompany->id)
            ->where('document_type', RecurringDocument::TYPE_JOBCARD)
            ->findOrFail($recurringDocument)
            ->delete();

        return redirect()->route('jobcards.index')
            ->with('success', 'Recurring jobcard removed.');
    }

    /**
     * Generate and download a PDF version of the jobcard.
     */
    public function print(Request $request, Jobcard $jobcard)
    {
        $this->authorize('view', $jobcard);

        $currentCompany = auth()->user()->getCurrentCompany();

        $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf('jobcard', [
            'jobcard' => $jobcard,
            'company' => $currentCompany,
        ], $currentCompany, $templateId);

        $filename = 'jobcard-'.$jobcard->job_number.'.pdf';
        $pdfContent = $pdf->output();

        $this->storePrintedDocumentNote($jobcard, $filename, $pdfContent, "Jobcard {$jobcard->job_number} printed");

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function sign(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $this->authorize('update', $jobcard);

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
            'signatures/%d/jobcards/%d/%s-%s.png',
            $jobcard->company_id,
            $jobcard->id,
            now()->format('YmdHis'),
            bin2hex(random_bytes(4))
        );

        Storage::disk('public')->put($path, $binary);

        $jobcard->signatures()->create([
            'company_id' => $jobcard->company_id,
            'user_id' => auth()->id(),
            'signer_name' => trim($validated['signer_name']),
            'signature_path' => $path,
            'signed_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Signature captured successfully.');
    }

    /**
     * Send jobcard via email.
     */
    public function email(Request $request, Jobcard $jobcard): RedirectResponse
    {
        $this->authorize('view', $jobcard);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'email' => ['required', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
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

        try {
            $jobcard->load(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'lineGroups', 'company', 'signatures']);

            $subject = $validated['subject'] ?? "Jobcard #{$jobcard->job_number} - {$jobcard->title}";
            $fromName = $currentCompany->name ?: config('mail.from.name');

            // Generate PDF
            $templateId = $request->get('template_id');

            $pdfService = new \App\Services\PdfGenerationService;
            $pdf = $pdfService->generatePdf('jobcard', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ], $currentCompany, $templateId);

            $filename = 'jobcard-'.$jobcard->job_number.'.pdf';

            Mail::mailer('smtp')->send('emails.jobcard-pdf', [
                'jobcard' => $jobcard,
                'company' => $currentCompany,
                'customMessage' => $validated['message'] ?? '',
            ], function ($message) use ($emails, $subject, $fromName, $pdf, $filename, $currentCompany) {
                $message->to($emails)
                    ->subject($subject)
                    ->from(config('mail.from.address'), $fromName)
                    ->attachData($pdf->output(), $filename, [
                        'mime' => 'application/pdf',
                    ]);

                if (! empty($currentCompany->email)) {
                    $message->replyTo($currentCompany->email, $currentCompany->name ?? null);
                }
            });

            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $currentCompany->id,
                    'customer_id' => $jobcard->customer_id,
                    'contact_id' => $jobcard->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['message'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'jobcard',
                    'related_id' => $jobcard->id,
                    'status' => 'sent',
                    'metadata' => [
                        'pdf_template_id' => $templateId,
                    ],
                    'sent_at' => now(),
                ]);
            }

            \Log::info('Email sent successfully', SafeLog::redactContext([
                'to' => $emails,
                'subject' => SafeLog::excerpt($subject, 120),
                'from' => config('mail.from.address'),
            ]));

            return redirect()->back()
                ->with('success', 'Jobcard sent successfully to '.implode(', ', $emails));

        } catch (\Exception $e) {
            $subject = $validated['subject'] ?? "Jobcard #{$jobcard->job_number} - {$jobcard->title}";
            foreach ($emails as $recipientEmail) {
                EmailActivity::create([
                    'company_id' => $currentCompany->id,
                    'customer_id' => $jobcard->customer_id,
                    'contact_id' => $jobcard->contact_id,
                    'user_id' => auth()->id(),
                    'recipient_email' => $recipientEmail,
                    'recipient_name' => null,
                    'subject' => $subject,
                    'body' => $validated['message'] ?? '',
                    'email_type' => 'document',
                    'related_type' => 'jobcard',
                    'related_id' => $jobcard->id,
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
            \Log::error('Email sending failed', SafeLog::redactContext([
                'error' => $e->getMessage(),
                'to' => $emails,
                'jobcard_id' => $jobcard->id,
            ]));

            return redirect()->back()
                ->withErrors(['message' => 'Failed to send email: '.$e->getMessage()]);
        }
    }

    /**
     * Convert jobcard to quote
     */
    public function convertToQuote(Jobcard $jobcard): RedirectResponse
    {
        $this->authorize('convertToQuote', $jobcard);

        $currentCompany = auth()->user()->getCurrentCompany();

        $existingQuoteId = Quote::where('company_id', $currentCompany->id)
            ->where('source_type', 'jobcard')
            ->where('source_id', $jobcard->id)
            ->value('id');

        if ($existingQuoteId) {
            return redirect()->route('quotes.show', $existingQuoteId)
                ->with('info', 'This jobcard has already been converted to a quote.');
        }

        return redirect()->route('quotes.create', [
            'source_type' => 'jobcard',
            'source_id' => $jobcard->id,
        ]);
    }

    /**
     * Convert jobcard to invoice
     */
    public function convertToInvoice(Jobcard $jobcard): RedirectResponse
    {
        $this->authorize('convertToInvoice', $jobcard);

        $currentCompany = auth()->user()->getCurrentCompany();

        return redirect()->route('invoices.create', [
            'source_type' => 'jobcard',
            'source_id' => $jobcard->id,
        ]);
    }

    private function storePrintedDocumentNote(Jobcard $jobcard, string $filename, string $pdfContent, string $subject): void
    {
        $companyId = (int) $jobcard->company_id;
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

        $note->noteable()->associate($jobcard);
        $note->save();
    }
}
