<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Jobcard;
use App\Models\Quote;
use App\Models\Report;
use App\Models\ReportTemplate;
use App\Services\ReportFiltersService;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display a listing of reports and templates
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $reports = Report::where('company_id', $currentCompany->id)
            ->with(['template', 'creator'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $templates = ReportTemplate::where(function ($query) use ($currentCompany) {
            $query->where('company_id', $currentCompany->id)
                ->orWhere('is_default', true);
        })
            ->with(['creator'])
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('reports/Index', [
            'reports' => $reports,
            'templates' => $templates,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Show the form for creating a new report
     */
    public function create(Request $request, ReportFiltersService $reportFiltersService): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $templates = ReportTemplate::where(function ($query) use ($currentCompany) {
            $query->where('company_id', $currentCompany->id)
                ->orWhere('is_default', true);
        })
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $entityType = $request->input('entity_type', 'invoice');
        $templateId = $request->input('template_id');

        $template = null;
        if ($templateId) {
            $template = ReportTemplate::find($templateId);
        }

        $filterOptions = $reportFiltersService->resolveFilterOptions($currentCompany->id);

        return Inertia::render('reports/Create', [
            'templates' => $templates,
            'currentCompany' => $currentCompany,
            'entityType' => $entityType,
            'template' => $template,
            'customers' => $filterOptions['customers'],
            'products' => $filterOptions['products'],
        ]);
    }

    /**
     * Store a newly created report
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_type' => 'required|in:invoice,quote,jobcard',
            'config' => 'required|array',
            'report_template_id' => ['nullable', CompanyScopedRules::reportTemplateSelectableForCompany($currentCompany->id)],
        ]);

        $validated['config'] = $this->sanitizeReportConfig($validated['entity_type'], $validated['config']);

        $report = Report::create([
            'company_id' => $currentCompany->id,
            'report_template_id' => $validated['report_template_id'] ?? null,
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'config' => $validated['config'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Report created successfully.');
    }

    /**
     * Display the specified report with data
     */
    public function show(Request $request, Report $report, ReportFiltersService $reportFiltersService): Response
    {
        $this->authorize('view', $report);

        $currentCompany = auth()->user()->getCurrentCompany();

        $report->load(['template', 'creator']);
        $mergedFilters = $reportFiltersService->mergeTemplateAndRequestFilters($report, $request);

        $data = $this->getReportData($report, $mergedFilters, $request->input('page', 1));
        $filterOptions = $reportFiltersService->resolveFilterOptions($currentCompany->id);

        return Inertia::render('reports/Show', [
            'report' => $report,
            'data' => $data,
            'currentCompany' => $currentCompany,
            'customers' => $filterOptions['customers'],
            'products' => $filterOptions['products'],
            'filters' => [
                'customer_id' => $request->input('customer_id') ?? $mergedFilters['customer_id'] ?? null,
                'product_id' => $request->input('product_id') ?? $mergedFilters['product_id'] ?? null,
                'date_from' => $request->input('date_from') ?? $mergedFilters['date_from'] ?? null,
                'date_to' => $request->input('date_to') ?? $mergedFilters['date_to'] ?? null,
                'status' => $request->input('status') ?? $mergedFilters['status'] ?? null,
            ],
            'templateFilters' => $report->template?->filters ?? [],
        ]);
    }

    /**
     * Show the form for editing the specified report
     */
    public function edit(Report $report): Response
    {
        $this->authorize('update', $report);

        $currentCompany = auth()->user()->getCurrentCompany();

        $templates = ReportTemplate::where(function ($query) use ($currentCompany) {
            $query->where('company_id', $currentCompany->id)
                ->orWhere('is_default', true);
        })
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return Inertia::render('reports/Edit', [
            'report' => $report->load('template'),
            'templates' => $templates,
            'currentCompany' => $currentCompany,
        ]);
    }

    /**
     * Update the specified report
     */
    public function update(Request $request, Report $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'entity_type' => 'required|in:invoice,quote,jobcard',
            'config' => 'required|array',
            'report_template_id' => ['nullable', CompanyScopedRules::reportTemplateSelectableForCompany($currentCompany->id)],
        ]);

        $validated['config'] = $this->sanitizeReportConfig($validated['entity_type'], $validated['config']);

        $report->update([
            'report_template_id' => $validated['report_template_id'] ?? null,
            'name' => $validated['name'],
            'entity_type' => $validated['entity_type'],
            'config' => $validated['config'],
        ]);

        return redirect()->route('reports.show', $report->id)
            ->with('success', 'Report updated successfully.');
    }

    /**
     * Remove the specified report
     */
    public function destroy(Report $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report deleted successfully.');
    }

    /**
     * Get report data based on filters and config
     */
    public function getData(Request $request, Report $report, ReportFiltersService $reportFiltersService)
    {
        $this->authorize('view', $report);
        $report->load('template');
        $mergedFilters = $reportFiltersService->mergeTemplateAndRequestFilters($report, $request);

        $data = $this->getReportData($report, $mergedFilters, $request->input('page', 1));

        return response()->json($data);
    }

    /**
     * Save report as template
     */
    public function saveTemplate(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:invoice,quote,jobcard',
            'config' => 'required|array',
            'filters' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        // If setting as default, unset other defaults for this entity type
        if ($validated['is_default'] ?? false) {
            ReportTemplate::where('company_id', $currentCompany->id)
                ->where('entity_type', $validated['entity_type'])
                ->update(['is_default' => false]);
        }

        $template = ReportTemplate::create([
            'company_id' => $currentCompany->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'entity_type' => $validated['entity_type'],
            'config' => $validated['config'],
            'filters' => $validated['filters'] ?? [],
            'is_default' => $validated['is_default'] ?? false,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('reports.index')
            ->with('success', 'Report template saved successfully.');
    }

    /**
     * Show the form for editing the specified template
     */
    public function editTemplate(ReportTemplate $template): Response
    {
        $this->authorize('update', $template);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Get customers and products for filters
        $customers = \App\Models\Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'account_code']);

        $products = \App\Models\Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        return Inertia::render('reports/EditTemplate', [
            'template' => $template->load('creator'),
            'currentCompany' => $currentCompany,
            'customers' => $customers,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified template
     */
    public function updateTemplate(Request $request, ReportTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'entity_type' => 'required|in:invoice,quote,jobcard',
            'config' => 'required|array',
            'filters' => 'nullable|array',
            'is_default' => 'boolean',
        ]);

        // If setting as default, unset other defaults for this entity type
        if ($validated['is_default'] ?? false) {
            ReportTemplate::where('company_id', $currentCompany->id)
                ->where('entity_type', $validated['entity_type'])
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'entity_type' => $validated['entity_type'],
            'config' => $validated['config'],
            'filters' => $validated['filters'] ?? [],
            'is_default' => $validated['is_default'] ?? false,
        ]);

        return redirect()->route('reports.index')
            ->with('success', 'Report template updated successfully.');
    }

    /**
     * Remove the specified template
     */
    public function destroyTemplate(ReportTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Check if template is being used by any reports
        $reportCount = $template->reports()->count();
        if ($reportCount > 0) {
            return redirect()->route('reports.index')
                ->with('error', "Cannot delete template. It is being used by {$reportCount} report(s).");
        }

        $template->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report template deleted successfully.');
    }

    /**
     * Get report data based on entity type, filters, and config
     */
    private function getReportData(Report $report, ?array $filters = null, int $page = 1, int $perPage = 50): array
    {
        $entityType = $report->entity_type;
        // Filters come from template or passed parameter, not from report
        $filters = $filters ?? [];
        $config = $this->sanitizeReportConfig($entityType, $report->config ?? []);
        $columns = $config['columns'] ?? [];
        $groupBy = $config['group_by'] ?? null;
        $sortBy = $config['sort_by'] ?? null;
        $sortDirection = $config['sort_direction'] ?? 'asc';

        $query = $this->getBaseQuery($entityType, $filters, $groupBy, $sortBy, $groupBy ? [] : $columns);

        // Apply grouping if specified
        if ($groupBy) {
            // Determine the actual column to group by
            $groupByColumn = $this->getGroupByColumn($entityType, $groupBy);

            if ($groupByColumn) {
                // When grouping, select only the grouping column and aggregated values
                $tableName = match ($entityType) {
                    'invoice' => 'invoices',
                    'quote' => 'quotes',
                    'jobcard' => 'jobcards',
                    default => null,
                };

                if ($tableName) {
                    // Select grouping column and aggregates only
                    // Use DB::raw to ensure proper column references
                    $query->select([
                        DB::raw($groupByColumn.' as group_value'),
                        DB::raw('COUNT(*) as count'),
                        DB::raw('SUM('.$tableName.'.subtotal) as subtotal'),
                        DB::raw('SUM('.$tableName.'.tax_amount) as tax_amount'),
                        DB::raw('SUM('.$tableName.'.discount_amount) as discount_amount'),
                        DB::raw('SUM('.$tableName.'.total) as total'),
                    ]);

                    $query->groupBy(DB::raw($groupByColumn));
                }
            }
        } else {
            // When not grouping, select all columns but use table prefix to avoid ambiguity
            $tableName = match ($entityType) {
                'invoice' => 'invoices',
                'quote' => 'quotes',
                'jobcard' => 'jobcards',
                default => null,
            };

            if ($tableName) {
                $query->select($tableName.'.*');
            }
        }

        // Apply sorting
        if ($groupBy) {
            // When grouping, we can only sort by:
            // 1. The grouping column (group_value)
            // 2. Aggregate functions (count, total, subtotal, etc.)

            if ($sortBy) {
                // Check if sorting by the grouping column
                if ($sortBy === $groupBy) {
                    // Sort by the grouping column
                    $query->orderBy('group_value', $sortDirection);
                } elseif (in_array($sortBy, ['total', 'subtotal', 'tax_amount', 'discount_amount'])) {
                    // Sort by aggregate column
                    $query->orderBy($sortBy, $sortDirection);
                } elseif ($sortBy === 'count') {
                    // Sort by count
                    $query->orderBy('count', $sortDirection);
                } else {
                    // Default to sorting by the grouping column if sort column is not compatible
                    $query->orderBy('group_value', $sortDirection);
                }
            } else {
                // Default sort by grouping column
                $query->orderBy('group_value', 'asc');
            }
        } else {
            // Normal sorting when not grouping
            if ($sortBy) {
                // Handle relationship columns in sorting
                if (str_contains($sortBy, '.')) {
                    [$relation, $field] = explode('.', $sortBy, 2);

                    // Check if join already exists, if not add it
                    $joins = $query->getQuery()->joins ?? [];
                    $hasJoin = false;

                    if ($entityType === 'invoice') {
                        if ($relation === 'salesperson') {
                            $hasJoin = collect($joins)->contains(fn ($join) => str_contains($join->table ?? '', 'salesperson_users'));
                            if (! $hasJoin) {
                                $query->leftJoin('users as salesperson_users', 'invoices.salesperson_id', '=', 'salesperson_users.id');
                            }
                            $query->orderBy('salesperson_users.'.$field, $sortDirection);
                        } elseif ($relation === 'customer') {
                            $hasJoin = collect($joins)->contains(fn ($join) => $join->table === 'customers');
                            if (! $hasJoin) {
                                $query->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id');
                            }
                            $query->orderBy('customers.'.$field, $sortDirection);
                        } else {
                            $tableName = 'invoices';
                            $query->orderBy($tableName.'.'.$sortBy, $sortDirection);
                        }
                    } elseif ($entityType === 'quote') {
                        if ($relation === 'customer') {
                            $hasJoin = collect($joins)->contains(fn ($join) => $join->table === 'customers');
                            if (! $hasJoin) {
                                $query->leftJoin('customers', 'quotes.customer_id', '=', 'customers.id');
                            }
                            $query->orderBy('customers.'.$field, $sortDirection);
                        } else {
                            $tableName = 'quotes';
                            $query->orderBy($tableName.'.'.$sortBy, $sortDirection);
                        }
                    } elseif ($entityType === 'jobcard') {
                        if ($relation === 'customer') {
                            $hasJoin = collect($joins)->contains(fn ($join) => $join->table === 'customers');
                            if (! $hasJoin) {
                                $query->leftJoin('customers', 'jobcards.customer_id', '=', 'customers.id');
                            }
                            $query->orderBy('customers.'.$field, $sortDirection);
                        } else {
                            $tableName = 'jobcards';
                            $query->orderBy($tableName.'.'.$sortBy, $sortDirection);
                        }
                    } else {
                        // Fallback to direct column
                        $query->orderBy($sortBy, $sortDirection);
                    }
                } else {
                    // Direct column sorting - use table prefix
                    $sortByColumn = $this->mapReportColumnToDatabaseColumn($entityType, $sortBy);
                    $tableName = match ($entityType) {
                        'invoice' => 'invoices',
                        'quote' => 'quotes',
                        'jobcard' => 'jobcards',
                        default => null,
                    };
                    if ($tableName) {
                        $query->orderBy($tableName.'.'.$sortByColumn, $sortDirection);
                    } else {
                        $query->orderBy($sortByColumn, $sortDirection);
                    }
                }
            } else {
                $tableName = match ($entityType) {
                    'invoice' => 'invoices',
                    'quote' => 'quotes',
                    'jobcard' => 'jobcards',
                    default => null,
                };
                if ($tableName) {
                    $query->orderBy($tableName.'.created_at', 'desc');
                } else {
                    $query->orderBy('created_at', 'desc');
                }
            }
        }

        // Handle grouping differently - get grouped summaries and individual records
        if ($groupBy) {
            // Get grouped summaries
            $groupedData = $query->get();

            // Fetch all matching records once, then bucket in memory by group key.
            // This avoids one query per group and significantly reduces timeouts.
            $tableName = $this->getTableName($entityType);
            $columnsForGroupedRecords = $columns;
            if (! in_array($groupBy, $columnsForGroupedRecords, true)) {
                $columnsForGroupedRecords[] = $groupBy;
            }
            $allGroupedRecords = $this->getBaseQuery($entityType, $filters, null, null, $columnsForGroupedRecords)
                ->select($tableName.'.*')
                ->get();

            $recordsByGroupKey = [];
            foreach ($allGroupedRecords as $record) {
                $recordGroupValue = $this->getColumnValue($record, $groupBy, $entityType);
                $recordGroupKey = $this->normalizeGroupKey($recordGroupValue);
                $recordsByGroupKey[$recordGroupKey][] = $record;
            }

            // Get individual records for each group
            $groupedRecords = [];
            $grandTotals = [
                'subtotal' => 0.0,
                'tax_amount' => 0.0,
                'discount_amount' => 0.0,
                'total' => 0.0,
                'count' => 0,
            ];

            foreach ($groupedData as $group) {
                $groupValue = $group->group_value;
                $groupKey = $this->normalizeGroupKey($groupValue);
                $individualRecords = collect($recordsByGroupKey[$groupKey] ?? []);

                // Transform individual records
                $transformedRecords = $individualRecords->map(function ($item) use ($columns, $entityType) {
                    return $this->transformRow($item, $columns, $entityType, null);
                });

                // Calculate group totals - ensure numeric values
                $groupTotals = [
                    'subtotal' => (float) ($group->subtotal ?? 0),
                    'tax_amount' => (float) ($group->tax_amount ?? 0),
                    'discount_amount' => (float) ($group->discount_amount ?? 0),
                    'total' => (float) ($group->total ?? 0),
                    'count' => (int) ($group->count ?? 0),
                ];

                // Add to grand totals - ensure numeric values
                $grandTotals['subtotal'] += (float) $groupTotals['subtotal'];
                $grandTotals['tax_amount'] += (float) $groupTotals['tax_amount'];
                $grandTotals['discount_amount'] += (float) $groupTotals['discount_amount'];
                $grandTotals['total'] += (float) $groupTotals['total'];
                $grandTotals['count'] += (int) $groupTotals['count'];

                $groupedRecords[] = [
                    'group_value' => $groupValue,
                    'group_totals' => $groupTotals,
                    'records' => $transformedRecords,
                ];
            }

            // Transform grouped summaries
            $transformedGroupedData = $groupedData->map(function ($item) use ($columns, $entityType, $groupBy) {
                return $this->transformRow($item, $columns, $entityType, $groupBy);
            });

            return [
                'data' => $transformedGroupedData,
                'grouped_records' => $groupedRecords,
                'totals' => $grandTotals,
                'grand_totals' => $grandTotals,
                'count' => $groupedData->count(),
                'total_records' => $grandTotals['count'],
            ];
        } else {
            // Not grouped - paginate normally
            $paginatedData = $query->paginate($perPage, ['*'], 'page', $page);

            // Transform data based on selected columns
            $transformedData = $paginatedData->map(function ($item) use ($columns, $entityType) {
                return $this->transformRow($item, $columns, $entityType, null);
            });

            // Calculate totals if needed
            $totals = [];
            $grandTotals = [];
            if ($config['show_totals'] ?? false) {
                // Get all data for totals calculation (not paginated)
                $allDataQuery = $this->getBaseQuery($entityType, $filters, null, null, []);
                $allData = $allDataQuery->get();
                $totals = $this->calculateTotals($allData, $columns, $entityType, null);
                $grandTotals = $totals;
            }

            return [
                'data' => $transformedData,
                'totals' => $totals,
                'grand_totals' => $grandTotals,
                'count' => $paginatedData->total(),
                'current_page' => $paginatedData->currentPage(),
                'last_page' => $paginatedData->lastPage(),
                'per_page' => $paginatedData->perPage(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
            ];
        }
    }

    /**
     * Get table name for entity type
     */
    private function getTableName(string $entityType): string
    {
        return match ($entityType) {
            'invoice' => 'invoices',
            'quote' => 'quotes',
            'jobcard' => 'jobcards',
            default => throw new \InvalidArgumentException("Invalid entity type: {$entityType}"),
        };
    }

    /**
     * Get group by column name
     */
    private function getGroupByColumn(string $entityType, string $groupBy): ?string
    {
        if (str_contains($groupBy, '.')) {
            [$relation, $field] = explode('.', $groupBy, 2);
            if (! $this->isAllowedRelationField($entityType, $relation, $field)) {
                return null;
            }

            if ($entityType === 'invoice') {
                if ($relation === 'salesperson') {
                    return 'salesperson_users.'.$field;
                } elseif ($relation === 'customer') {
                    return 'customers.'.$field;
                }
            } elseif ($entityType === 'quote' || $entityType === 'jobcard') {
                if ($relation === 'customer') {
                    return 'customers.'.$field;
                }
            }
        } else {
            $groupByColumn = $this->mapReportColumnToDatabaseColumn($entityType, $groupBy);

            return $this->getTableName($entityType).'.'.$groupByColumn;
        }

        return null;
    }

    /**
     * Get base query based on entity type and filters
     */
    private function getBaseQuery(string $entityType, array $filters, ?string $groupBy = null, ?string $sortBy = null, array $columns = [])
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        // Determine which relations need joins
        $needsSalespersonJoin = false;
        $needsCustomerJoin = false;

        foreach ([$groupBy, $sortBy] as $field) {
            if ($field && str_contains($field, '.')) {
                [$relation] = explode('.', $field, 2);
                if ($relation === 'salesperson') {
                    $needsSalespersonJoin = true;
                }
                if ($relation === 'customer') {
                    $needsCustomerJoin = true;
                }
            }
        }

        // Determine relationships actually needed for selected columns.
        $needsCustomerRelation = false;
        $needsSalespersonRelation = false;
        $needsPaymentsRelation = false;
        $needsCreditNotesRelation = false;

        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                continue;
            }

            if (str_starts_with($column, 'customer.')) {
                $needsCustomerRelation = true;
            }

            if (str_starts_with($column, 'salesperson.')) {
                $needsSalespersonRelation = true;
            }

            if (in_array($column, ['payment_count', 'last_payment_date', 'payments_summary', 'total_paid', 'remaining_balance'], true)) {
                $needsPaymentsRelation = true;
            }

            if (in_array($column, ['total_credited', 'remaining_balance'], true)) {
                $needsCreditNotesRelation = true;
            }
        }

        // Only eager load relationships if not grouping (grouping uses custom SELECT)
        $shouldEagerLoad = ! $groupBy;

        switch ($entityType) {
            case 'invoice':
                $query = Invoice::where('invoices.company_id', $currentCompany->id);

                // Join related tables if needed for grouping/sorting
                if ($needsSalespersonJoin) {
                    $query->leftJoin('users as salesperson_users', 'invoices.salesperson_id', '=', 'salesperson_users.id');
                }
                if ($needsCustomerJoin) {
                    $query->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id');
                }

                // Eager load relationships for data transformation (only if not grouping)
                if ($shouldEagerLoad) {
                    $relations = [];
                    if ($needsCustomerRelation) {
                        $relations[] = 'customer';
                    }
                    if ($needsSalespersonRelation) {
                        $relations[] = 'salesperson';
                    }
                    if ($needsPaymentsRelation) {
                        $relations[] = 'payments';
                    }
                    if ($needsCreditNotesRelation) {
                        $relations[] = 'creditNotes';
                    }

                    if (! empty($relations)) {
                        $query->with($relations);
                    }
                }
                break;
            case 'quote':
                $query = Quote::where('quotes.company_id', $currentCompany->id);

                // Join related tables if needed for grouping/sorting
                if ($needsCustomerJoin) {
                    $query->leftJoin('customers', 'quotes.customer_id', '=', 'customers.id');
                }

                if ($shouldEagerLoad) {
                    if ($needsCustomerRelation) {
                        $query->with(['customer']);
                    }
                }
                break;
            case 'jobcard':
                $query = Jobcard::where('jobcards.company_id', $currentCompany->id);

                // Join related tables if needed for grouping/sorting
                if ($needsCustomerJoin) {
                    $query->leftJoin('customers', 'jobcards.customer_id', '=', 'customers.id');
                }

                if ($shouldEagerLoad) {
                    if ($needsCustomerRelation) {
                        $query->with(['customer']);
                    }
                }
                break;
            default:
                throw new \InvalidArgumentException("Invalid entity type: {$entityType}");
        }

        // Apply filters
        if (isset($filters['date_from'])) {
            $dateField = $this->getDocumentDateField($entityType);
            $query->whereDate($dateField, '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $dateField = $this->getDocumentDateField($entityType);
            $query->whereDate($dateField, '<=', $filters['date_to']);
        }

        if (isset($filters['status']) && ! empty($filters['status'])) {
            $query->whereIn('status', (array) $filters['status']);
        }

        if (isset($filters['customer_id']) && ! empty($filters['customer_id'])) {
            $customerIds = (array) $filters['customer_id'];
            // Filter out null/empty values
            $customerIds = array_filter($customerIds, fn ($id) => ! empty($id));
            if (! empty($customerIds)) {
                $query->whereIn('customer_id', $customerIds);
            }
        }

        // Add product filter - filter by line items
        if (isset($filters['product_id']) && ! empty($filters['product_id'])) {
            $productIds = (array) $filters['product_id'];
            // Filter out null/empty values
            $productIds = array_filter($productIds, fn ($id) => ! empty($id));

            if (! empty($productIds)) {
                $tableName = $this->getTableName($entityType);

                // Use whereExists with subquery to avoid GROUP BY issues
                $lineItemsTable = match ($entityType) {
                    'invoice' => 'invoice_line_items',
                    'quote' => 'quote_line_items',
                    'jobcard' => 'jobcard_line_items',
                    default => null,
                };

                if ($lineItemsTable) {
                    // Map entity type to correct foreign key column name
                    $foreignKeyColumn = match ($entityType) {
                        'invoice' => 'invoice_id',
                        'quote' => 'quote_id',
                        'jobcard' => 'jobcard_id',
                        default => $entityType.'_id',
                    };

                    $query->whereExists(function ($subquery) use ($lineItemsTable, $tableName, $productIds, $foreignKeyColumn) {
                        $subquery->select(DB::raw(1))
                            ->from($lineItemsTable)
                            ->whereColumn($lineItemsTable.'.'.$foreignKeyColumn, $tableName.'.id')
                            ->whereIn($lineItemsTable.'.product_id', $productIds)
                            ->whereNotNull($lineItemsTable.'.product_id'); // Exclude custom items without products
                    });
                }
            }
        }

        return $query;
    }

    /**
     * Transform a row based on selected columns
     */
    private function transformRow($item, array $columns, string $entityType, ?string $groupBy = null): array
    {
        $row = [];

        // Always include IDs for linking purposes
        if (! $groupBy) {
            $row['_id'] = $item->id ?? null;
            $row['_customer_id'] = $item->customer_id ?? null;
        }

        // If grouping is enabled, the data structure is different (aggregated)
        if ($groupBy) {
            // For grouped data, map the group_value to the grouping column
            foreach ($columns as $column) {
                if ($column === $groupBy) {
                    // This is the grouping column
                    $row[$column] = $item->group_value ?? null;
                } elseif (in_array($column, ['subtotal', 'tax_amount', 'discount_amount', 'total'])) {
                    // These are aggregated values
                    $row[$column] = $item->{$column} ?? 0;
                } elseif ($column === 'count' || $column === 'formatted_count') {
                    // Count of records in group
                    $row[$column] = $item->count ?? 0;
                } else {
                    // For other columns in grouped data, we can't show individual values
                    // Show the grouped value or null
                    $row[$column] = null;
                }
            }
        } else {
            // Normal row transformation
            foreach ($columns as $column) {
                $row[$column] = $this->getColumnValue($item, $column, $entityType);
            }
        }

        return $row;
    }

    /**
     * Get value for a specific column
     */
    private function getColumnValue($item, string $column, string $entityType)
    {
        // Handle nested relationships
        if (str_contains($column, '.')) {
            [$relation, $field] = explode('.', $column, 2);

            return data_get($item, "{$relation}.{$field}");
        }

        // Handle formatted fields
        if ($column === 'formatted_total') {
            return 'R'.number_format($item->total ?? 0, 2);
        }

        if ($column === 'formatted_date') {
            $dateField = $this->getDocumentDateField($entityType);

            return $item->{$dateField}?->format('Y-m-d') ?? '';
        }

        // Invoice related-record derived fields
        if ($entityType === 'invoice' && $column === 'payment_count') {
            if ($item->relationLoaded('payments')) {
                return $item->payments->count();
            }

            return $item->payments()->count();
        }

        if ($entityType === 'invoice' && $column === 'last_payment_date') {
            if ($item->relationLoaded('payments')) {
                $latest = $item->payments
                    ->sortByDesc(fn ($payment) => $payment->payment_date?->timestamp ?? 0)
                    ->first();

                return $latest?->payment_date?->format('Y-m-d');
            }

            return $item->payments()
                ->latest('payment_date')
                ->first()
                ?->payment_date
                ?->format('Y-m-d');
        }

        if ($entityType === 'invoice' && $column === 'payments_summary') {
            $payments = $item->relationLoaded('payments')
                ? $item->payments
                : $item->payments()->orderBy('payment_date', 'asc')->get();

            if ($payments->isEmpty()) {
                return '';
            }

            return $payments
                ->map(function ($payment) {
                    $method = ucfirst((string) ($payment->payment_method ?? 'unknown'));
                    $amount = (float) ($payment->amount ?? 0);

                    return $method.': R'.number_format($amount, 2);
                })
                ->implode(', ');
        }

        if ($entityType === 'invoice' && in_array($column, ['total_paid', 'total_credited', 'remaining_balance'], true)) {
            return (float) ($item->{$column} ?? 0);
        }

        // Default to direct property access
        return $item->{$column} ?? null;
    }

    /**
     * Calculate totals for numeric columns
     */
    private function calculateTotals($data, array $columns, string $entityType, ?string $groupBy = null): array
    {
        $totals = [];

        $numericColumns = ['subtotal', 'tax_amount', 'total', 'discount_amount', 'total_paid', 'total_credited', 'remaining_balance'];

        foreach ($numericColumns as $column) {
            if (in_array($column, $columns)) {
                // If grouped, the data already has aggregated values
                if ($groupBy) {
                    $totals[$column] = $data->sum($column);
                } else {
                    $totals[$column] = $data->sum($column);
                }
            }
        }

        return $totals;
    }

    /**
     * Export report data to CSV/Excel
     */
    public function export(Request $request, Report $report, ReportFiltersService $reportFiltersService)
    {
        $this->authorize('export', $report);

        $currentCompany = auth()->user()->getCurrentCompany();

        $report->load(['template']);
        $mergedFilters = $reportFiltersService->mergeTemplateAndRequestFilters($report, $request, true);

        // Get all report data for export (not paginated)
        // For grouped reports, getReportData already returns all data
        // For non-grouped, we'll modify the query to get all records
        $entityType = $report->entity_type;
        $config = $report->config ?? [];
        $groupBy = $this->sanitizeGroupOrSortField($entityType, $config['group_by'] ?? null);

        if ($groupBy) {
            // Grouped reports already return all data
            $reportData = $this->getReportData($report, $mergedFilters, 1, 100000);
        } else {
            // For non-grouped, get all data without pagination
            $config = $this->sanitizeReportConfig($entityType, $config);
            $columns = $config['columns'] ?? [];
            $query = $this->getBaseQuery($entityType, $mergedFilters, null, $config['sort_by'] ?? null, $columns);

            // Apply sorting
            if ($config['sort_by'] ?? null) {
                $sortBy = $config['sort_by'];
                $sortDirection = $config['sort_direction'] ?? 'asc';

                if (str_contains($sortBy, '.')) {
                    [$relation, $field] = explode('.', $sortBy, 2);
                    if ($entityType === 'invoice' && $relation === 'salesperson' && $this->isAllowedRelationField($entityType, $relation, $field)) {
                        $query->orderBy('salesperson_users.'.$field, $sortDirection);
                    } elseif ($relation === 'customer' && $this->isAllowedRelationField($entityType, $relation, $field)) {
                        $query->orderBy('customers.'.$field, $sortDirection);
                    }
                } else {
                    $sortByColumn = $this->mapReportColumnToDatabaseColumn($entityType, $sortBy);
                    $tableName = $this->getTableName($entityType);
                    $query->orderBy($tableName.'.'.$sortByColumn, $sortDirection);
                }
            } else {
                $tableName = $this->getTableName($entityType);
                if ($tableName) {
                    $query->orderBy($tableName.'.created_at', 'desc');
                } else {
                    $query->orderBy('created_at', 'desc');
                }
            }

            $tableName = $this->getTableName($entityType);
            $query->select($tableName.'.*');

            // Get all records
            $allData = $query->get();

            // Transform data
            $transformedData = $allData->map(function ($item) use ($columns, $entityType) {
                return $this->transformRow($item, $columns, $entityType, null);
            });

            // Calculate totals
            $grandTotals = [];
            if ($config['show_totals'] ?? false) {
                $grandTotals = $this->calculateTotals($allData, $columns, $entityType, null);
            }

            $reportData = [
                'data' => $transformedData,
                'grand_totals' => $grandTotals,
                'count' => $allData->count(),
            ];
        }

        $entityType = $report->entity_type;
        $config = $this->sanitizeReportConfig($entityType, $report->config ?? []);
        $columns = $config['columns'] ?? [];
        $groupBy = $config['group_by'] ?? null;

        // Generate filename
        $filename = str_replace(' ', '_', $report->name).'_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($reportData, $columns, $groupBy) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8 Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Format column headers
            $headers = array_map(function ($col) {
                return ucwords(str_replace(['_', '.'], ' ', $col));
            }, $columns);

            // Write headers
            fputcsv($file, $headers);

            if ($groupBy && isset($reportData['grouped_records'])) {
                // Export grouped data
                foreach ($reportData['grouped_records'] as $group) {
                    // Write group header row
                    $groupHeader = array_fill(0, count($columns), '');
                    $groupHeader[0] = 'GROUP: '.$this->formatGroupValue($group['group_value']);
                    fputcsv($file, $groupHeader);

                    // Write group totals row
                    $totalsRow = array_fill(0, count($columns), '');
                    foreach ($columns as $index => $column) {
                        if (isset($group['group_totals'][$column])) {
                            $value = $group['group_totals'][$column];
                            if (is_numeric($value)) {
                                $totalsRow[$index] = 'R'.number_format((float) $value, 2);
                            } else {
                                $totalsRow[$index] = $value;
                            }
                        } elseif ($column === 'count') {
                            $totalsRow[$index] = count($group['records']);
                        }
                    }
                    // Mark as totals row
                    $totalsRow[0] = 'TOTALS: '.($totalsRow[0] ?: '');
                    fputcsv($file, $totalsRow);

                    // Write individual records
                    foreach ($group['records'] as $record) {
                        $row = [];
                        foreach ($columns as $column) {
                            $value = $record[$column] ?? '';
                            // Format numeric values
                            if (is_numeric($value) && in_array($column, ['subtotal', 'tax_amount', 'total', 'discount_amount', 'unit_price', 'quantity', 'total_paid', 'total_credited', 'remaining_balance'])) {
                                $row[] = 'R'.number_format((float) $value, 2);
                            } else {
                                $row[] = $this->formatCellValue($value);
                            }
                        }
                        fputcsv($file, $row);
                    }

                    // Add empty row between groups
                    fputcsv($file, []);
                }

                // Write grand totals if available
                if (isset($reportData['grand_totals']) && ! empty($reportData['grand_totals'])) {
                    fputcsv($file, []);
                    $grandTotalsRow = array_fill(0, count($columns), '');
                    $grandTotalsRow[0] = 'GRAND TOTALS';
                    foreach ($columns as $index => $column) {
                        if (isset($reportData['grand_totals'][$column])) {
                            $value = $reportData['grand_totals'][$column];
                            if (is_numeric($value)) {
                                $grandTotalsRow[$index] = 'R'.number_format((float) $value, 2);
                            } else {
                                $grandTotalsRow[$index] = $value;
                            }
                        } elseif ($column === 'count') {
                            $grandTotalsRow[$index] = $reportData['grand_totals']['count'] ?? 0;
                        }
                    }
                    fputcsv($file, $grandTotalsRow);
                }
            } else {
                // Export non-grouped data
                foreach ($reportData['data'] as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $value = $record[$column] ?? '';
                        // Format numeric values
                        if (is_numeric($value) && in_array($column, ['subtotal', 'tax_amount', 'total', 'discount_amount', 'unit_price', 'quantity', 'total_paid', 'total_credited', 'remaining_balance'])) {
                            $row[] = 'R'.number_format((float) $value, 2);
                        } else {
                            $row[] = $this->formatCellValue($value);
                        }
                    }
                    fputcsv($file, $row);
                }

                // Write totals if available
                if (isset($reportData['grand_totals']) && ! empty($reportData['grand_totals'])) {
                    fputcsv($file, []);
                    $totalsRow = array_fill(0, count($columns), '');
                    $totalsRow[0] = 'TOTALS';
                    foreach ($columns as $index => $column) {
                        if (isset($reportData['grand_totals'][$column])) {
                            $value = $reportData['grand_totals'][$column];
                            if (is_numeric($value)) {
                                $totalsRow[$index] = 'R'.number_format((float) $value, 2);
                            } else {
                                $totalsRow[$index] = $value;
                            }
                        } elseif ($column === 'count') {
                            $totalsRow[$index] = $reportData['grand_totals']['count'] ?? 0;
                        }
                    }
                    fputcsv($file, $totalsRow);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Format cell value for CSV export
     */
    private function formatCellValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Format group value for display
     */
    private function formatGroupValue($value): string
    {
        if ($value === null) {
            return 'N/A';
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Resolve the primary document date field used by report filters.
     */
    private function getDocumentDateField(string $entityType): string
    {
        return match ($entityType) {
            'invoice' => 'invoice_date',
            'quote' => 'created_at',
            'jobcard' => 'start_date',
            default => 'created_at',
        };
    }

    /**
     * Map report virtual columns to concrete DB columns.
     */
    private function mapReportColumnToDatabaseColumn(string $entityType, string $column): string
    {
        if (! $this->isAllowedColumn($entityType, $column) && ! in_array($column, ['formatted_date', 'formatted_total'], true)) {
            throw new \InvalidArgumentException("Invalid report column: {$column}");
        }

        return match ($column) {
            'formatted_date' => $this->getDocumentDateField($entityType),
            'formatted_total' => 'total',
            default => $column,
        };
    }

    private function sanitizeReportConfig(string $entityType, array $config): array
    {
        $allowedColumns = $this->getAllowedColumns($entityType);
        $columns = array_values(array_unique(array_filter((array) ($config['columns'] ?? []), function ($column) use ($allowedColumns) {
            return is_string($column) && in_array($column, $allowedColumns, true);
        })));

        if (empty($columns)) {
            $columns = ['number', 'customer.name', 'formatted_date', 'status', 'formatted_total'];
        }

        $groupBy = $this->sanitizeGroupOrSortField($entityType, $config['group_by'] ?? null);
        $sortBy = $this->sanitizeGroupOrSortField($entityType, $config['sort_by'] ?? null);
        $sortDirection = strtolower((string) ($config['sort_direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return array_merge($config, [
            'columns' => $columns,
            'group_by' => $groupBy,
            'sort_by' => $sortBy,
            'sort_direction' => $sortDirection,
        ]);
    }

    private function sanitizeGroupOrSortField(string $entityType, mixed $field): ?string
    {
        if (! is_string($field) || $field === '') {
            return null;
        }

        return $this->isAllowedColumn($entityType, $field) ? $field : null;
    }

    private function isAllowedColumn(string $entityType, string $column): bool
    {
        return in_array($column, $this->getAllowedColumns($entityType), true);
    }

    private function isAllowedRelationField(string $entityType, string $relation, string $field): bool
    {
        $allowed = match ($entityType) {
            'invoice' => [
                'customer' => ['name', 'account_code'],
                'salesperson' => ['name'],
            ],
            'quote', 'jobcard' => [
                'customer' => ['name', 'account_code'],
            ],
            default => [],
        };

        return isset($allowed[$relation]) && in_array($field, $allowed[$relation], true);
    }

    private function getAllowedColumns(string $entityType): array
    {
        return match ($entityType) {
            'invoice' => [
                'number', 'customer.name', 'salesperson.name', 'formatted_date', 'status',
                'subtotal', 'tax_amount', 'discount_amount', 'formatted_total', 'total',
                'payment_count', 'last_payment_date', 'payments_summary', 'total_paid',
                'total_credited', 'remaining_balance', 'count', 'created_at',
            ],
            'quote' => [
                'number', 'customer.name', 'formatted_date', 'status', 'subtotal',
                'tax_amount', 'discount_amount', 'formatted_total', 'total', 'count', 'created_at',
            ],
            'jobcard' => [
                'number', 'customer.name', 'formatted_date', 'status', 'subtotal',
                'tax_amount', 'discount_amount', 'formatted_total', 'total', 'count', 'created_at',
            ],
            default => [],
        };
    }

    /**
     * Normalize mixed group values for stable array keys.
     */
    private function normalizeGroupKey($value): string
    {
        if ($value === null) {
            return '__NULL__';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return json_encode($value) ?: '__JSON__';
    }
}
