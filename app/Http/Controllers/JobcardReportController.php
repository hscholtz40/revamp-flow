<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Jobcard;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobcardReportController extends Controller
{
    /**
     * Show the detailed jobcards report.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $includeStatusTrackers = $request->boolean('include_status_trackers');

        $eagerLoads = [
            'customer',
            'assignedUser',
            'assignedTeam',
            'lineItems.product',
            'timeEntries.user',
            'invoice',
        ];

        if ($includeStatusTrackers) {
            $eagerLoads[] = 'statusTransitions.user';
        }

        $query = Jobcard::with($eagerLoads)->where('company_id', $currentCompany->id);

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('start_date', '<=', $request->date_to);
        }
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }
        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
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

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['job_number', 'title', 'status', 'start_date', 'due_date', 'total', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderByDesc('created_at');
        }

        $jobcards = $query->get();

        // Transform jobcard data with computed aggregates
        $reportData = $jobcards->map(function (Jobcard $jc) use ($includeStatusTrackers) {
            $timeEntries = $jc->timeEntries;
            $totalMinutes = $timeEntries->sum('duration_minutes');
            $billableMinutes = $timeEntries->where('is_billable', true)->sum('duration_minutes');
            $billableAmount = $timeEntries->where('is_billable', true)->sum(function ($te) {
                return ($te->hourly_rate && $te->duration_minutes)
                    ? round(($te->duration_minutes / 60) * $te->hourly_rate, 2)
                    : 0;
            });

            return [
                'id' => $jc->id,
                'job_number' => $jc->job_number,
                'title' => $jc->title,
                'description' => $jc->description,
                'status' => $jc->status,
                'status_color' => $jc->status_color,
                'start_date' => $jc->start_date?->format('Y-m-d'),
                'due_date' => $jc->due_date?->format('Y-m-d'),
                'completed_date' => $jc->completed_date?->format('Y-m-d'),
                'created_at' => $jc->created_at?->format('Y-m-d'),
                'customer' => $jc->customer ? [
                    'id' => $jc->customer->id,
                    'name' => $jc->customer->name,
                ] : null,
                'assigned_user' => $jc->assignedUser ? [
                    'id' => $jc->assignedUser->id,
                    'name' => $jc->assignedUser->name,
                ] : null,
                'assigned_team' => $jc->assignedTeam ? [
                    'id' => $jc->assignedTeam->id,
                    'name' => $jc->assignedTeam->name,
                ] : null,
                'invoice' => $jc->invoice ? [
                    'id' => $jc->invoice->id,
                    'invoice_number' => $jc->invoice->invoice_number,
                ] : null,
                'subtotal' => (float) ($jc->subtotal ?? 0),
                'discount_amount' => (float) ($jc->discount_amount ?? 0),
                'tax_rate' => (float) ($jc->tax_rate ?? 0),
                'tax_amount' => (float) ($jc->tax_amount ?? 0),
                'total' => (float) ($jc->total ?? 0),
                'formatted_total' => $jc->formatted_total,
                'line_items_count' => $jc->lineItems->count(),
                'line_items' => $jc->lineItems->map(fn ($li) => [
                    'id' => $li->id,
                    'description' => $li->description,
                    'product_name' => $li->product?->name,
                    'quantity' => (int) ($li->quantity ?? 0),
                    'unit_price' => (float) ($li->unit_price ?? 0),
                    'discount_amount' => (float) ($li->discount_amount ?? 0),
                    'discount_percentage' => (float) ($li->discount_percentage ?? 0),
                    'total' => (float) ($li->total ?? 0),
                ]),
                'time_entries_count' => $timeEntries->count(),
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2),
                'formatted_total_time' => $this->formatMinutes($totalMinutes),
                'billable_minutes' => $billableMinutes,
                'billable_hours' => round($billableMinutes / 60, 2),
                'formatted_billable_time' => $this->formatMinutes($billableMinutes),
                'billable_amount' => $billableAmount,
                'time_entries' => $timeEntries->map(fn ($te) => [
                    'id' => $te->id,
                    'user_name' => $te->user?->name ?? 'Unknown',
                    'date' => $te->date?->format('Y-m-d'),
                    'description' => $te->description,
                    'duration_minutes' => $te->duration_minutes,
                    'formatted_duration' => $te->formatted_duration,
                    'hourly_rate' => (float) ($te->hourly_rate ?? 0),
                    'is_billable' => $te->is_billable,
                    'total_amount' => $te->total_amount,
                    'status' => $te->status,
                ]),
                // Status time tracker data (only when enabled)
                'status_durations' => $includeStatusTrackers
                    ? collect($jc->getStatusDurations())->map(fn ($minutes, $status) => [
                        'minutes' => $minutes,
                        'formatted' => $this->formatDuration($minutes),
                    ])->toArray()
                    : null,
                'status_transitions' => $includeStatusTrackers
                    ? $jc->statusTransitions->map(fn ($t) => [
                        'from_status' => $t->from_status,
                        'to_status' => $t->to_status,
                        'transitioned_at' => $t->transitioned_at->format('Y-m-d H:i'),
                        'user_name' => $t->user?->name ?? 'System',
                    ])->toArray()
                    : null,
            ];
        });

        // Summary stats
        $summary = [
            'total_jobcards' => $reportData->count(),
            'total_value' => $reportData->sum('total'),
            'total_subtotal' => $reportData->sum('subtotal'),
            'total_tax' => $reportData->sum('tax_amount'),
            'total_discount' => $reportData->sum('discount_amount'),
            'total_minutes' => $reportData->sum('total_minutes'),
            'total_hours' => round($reportData->sum('total_minutes') / 60, 2),
            'formatted_total_time' => $this->formatMinutes($reportData->sum('total_minutes')),
            'billable_minutes' => $reportData->sum('billable_minutes'),
            'billable_hours' => round($reportData->sum('billable_minutes') / 60, 2),
            'formatted_billable_time' => $this->formatMinutes($reportData->sum('billable_minutes')),
            'billable_amount' => $reportData->sum('billable_amount'),
            'total_line_items' => $reportData->sum('line_items_count'),
            'total_time_entries' => $reportData->sum('time_entries_count'),
            'status_breakdown' => [
                'draft' => $reportData->where('status', 'draft')->count(),
                'pending' => $reportData->where('status', 'pending')->count(),
                'in_progress' => $reportData->where('status', 'in_progress')->count(),
                'completed' => $reportData->where('status', 'completed')->count(),
                'cancelled' => $reportData->where('status', 'cancelled')->count(),
            ],
        ];

        // Lookup data for filters
        $customers = Customer::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);
        $users = User::query()
            ->staffSelectableForCompany($currentCompany->id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $teams = Team::where('company_id', $currentCompany->id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('reports/JobcardDetail', [
            'reportData' => $reportData,
            'summary' => $summary,
            'customers' => $customers,
            'users' => $users,
            'teams' => $teams,
            'filters' => [
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
                'due_date_from' => $request->input('due_date_from', ''),
                'due_date_to' => $request->input('due_date_to', ''),
                'status' => $request->input('status', ''),
                'customer_id' => $request->input('customer_id', ''),
                'assigned_to_user_id' => $request->input('assigned_to_user_id', ''),
                'assigned_to_team_id' => $request->input('assigned_to_team_id', ''),
                'include_status_trackers' => $includeStatusTrackers,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Export the detailed jobcards report as CSV.
     */
    public function export(Request $request)
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $includeStatusTrackers = $request->boolean('include_status_trackers');

        $eagerLoads = [
            'customer',
            'assignedUser',
            'assignedTeam',
            'lineItems.product',
            'timeEntries.user',
            'invoice',
        ];

        if ($includeStatusTrackers) {
            $eagerLoads[] = 'statusTransitions.user';
        }

        $query = Jobcard::with($eagerLoads)->where('company_id', $currentCompany->id);

        // Apply same filters
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('start_date', '<=', $request->date_to);
        }
        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->due_date_from);
        }
        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->due_date_to);
        }
        if ($request->filled('status')) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
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

        $jobcards = $query->orderByDesc('created_at')->get();

        $filename = 'Jobcards_Detailed_Report_'.date('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($jobcards, $includeStatusTrackers) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Jobcard header row
            $headerRow = [
                'Job Number', 'Title', 'Customer', 'Status',
                'Start Date', 'Due Date', 'Completed Date',
                'Assigned User', 'Assigned Team', 'Invoice #',
                'Subtotal', 'Discount', 'Tax Rate', 'Tax Amount', 'Total',
                'Line Items', 'Time Entries',
                'Total Hours', 'Billable Hours', 'Billable Amount',
            ];

            if ($includeStatusTrackers) {
                $headerRow = array_merge($headerRow, [
                    'Time in Draft', 'Time in Pending', 'Time in In Progress',
                    'Time in Completed', 'Time in Cancelled',
                ]);
            }

            fputcsv($file, $headerRow);

            foreach ($jobcards as $jc) {
                $totalMin = $jc->timeEntries->sum('duration_minutes');
                $billableMin = $jc->timeEntries->where('is_billable', true)->sum('duration_minutes');
                $billableAmt = $jc->timeEntries->where('is_billable', true)->sum(function ($te) {
                    return ($te->hourly_rate && $te->duration_minutes)
                        ? round(($te->duration_minutes / 60) * $te->hourly_rate, 2) : 0;
                });

                $row = [
                    $jc->job_number,
                    $jc->title,
                    $jc->customer?->name ?? '',
                    ucfirst(str_replace('_', ' ', $jc->status)),
                    $jc->start_date?->format('Y-m-d') ?? '',
                    $jc->due_date?->format('Y-m-d') ?? '',
                    $jc->completed_date?->format('Y-m-d') ?? '',
                    $jc->assignedUser?->name ?? '',
                    $jc->assignedTeam?->name ?? '',
                    $jc->invoice?->invoice_number ?? '',
                    'R'.number_format($jc->subtotal ?? 0, 2),
                    'R'.number_format($jc->discount_amount ?? 0, 2),
                    ($jc->tax_rate ?? 0).'%',
                    'R'.number_format($jc->tax_amount ?? 0, 2),
                    'R'.number_format($jc->total ?? 0, 2),
                    $jc->lineItems->count(),
                    $jc->timeEntries->count(),
                    round($totalMin / 60, 2),
                    round($billableMin / 60, 2),
                    'R'.number_format($billableAmt, 2),
                ];

                if ($includeStatusTrackers) {
                    $durations = $jc->getStatusDurations();
                    $row = array_merge($row, [
                        $this->formatDuration($durations['draft'] ?? 0),
                        $this->formatDuration($durations['pending'] ?? 0),
                        $this->formatDuration($durations['in_progress'] ?? 0),
                        $this->formatDuration($durations['completed'] ?? 0),
                        $this->formatDuration($durations['cancelled'] ?? 0),
                    ]);
                }

                fputcsv($file, $row);

                // Line items sub-rows
                if ($jc->lineItems->isNotEmpty()) {
                    fputcsv($file, ['', '  LINE ITEMS:', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
                    foreach ($jc->lineItems as $li) {
                        fputcsv($file, [
                            '', '    '.($li->product?->name ?? $li->description ?? 'Item'),
                            $li->description ?? '',
                            '', '', '', '', '', '', '',
                            'Qty: '.($li->quantity ?? 0),
                            'R'.number_format($li->unit_price ?? 0, 2),
                            '', '', 'R'.number_format($li->total ?? 0, 2),
                            '', '', '', '', '',
                        ]);
                    }
                }

                // Time entries sub-rows
                if ($jc->timeEntries->isNotEmpty()) {
                    fputcsv($file, ['', '  TIME ENTRIES:', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
                    foreach ($jc->timeEntries as $te) {
                        $teAmt = ($te->is_billable && $te->hourly_rate && $te->duration_minutes)
                            ? round(($te->duration_minutes / 60) * $te->hourly_rate, 2) : 0;
                        fputcsv($file, [
                            '', '    '.($te->user?->name ?? 'Unknown'),
                            $te->description ?? '',
                            $te->is_billable ? 'Billable' : 'Non-billable',
                            $te->date?->format('Y-m-d') ?? '',
                            '', '', '', '', '',
                            '', $te->hourly_rate ? 'R'.number_format($te->hourly_rate, 2).'/hr' : '',
                            '', '', $teAmt > 0 ? 'R'.number_format($teAmt, 2) : '',
                            '', '',
                            $te->formatted_duration, '', '',
                        ]);
                    }
                }

                // Separator
                fputcsv($file, []);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function formatMinutes(int $minutes): string
    {
        $h = floor(abs($minutes) / 60);
        $m = abs($minutes) % 60;
        if ($h > 0 && $m > 0) {
            return "{$h}h {$m}m";
        }
        if ($h > 0) {
            return "{$h}h";
        }

        return "{$m}m";
    }

    /**
     * Format minutes into a human-readable duration with days, hours, minutes.
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $d = floor($minutes / 1440);
        $h = floor(($minutes % 1440) / 60);
        $m = $minutes % 60;

        $parts = [];
        if ($d > 0) {
            $parts[] = "{$d}d";
        }
        if ($h > 0) {
            $parts[] = "{$h}h";
        }
        if ($m > 0) {
            $parts[] = "{$m}m";
        }

        return implode(' ', $parts);
    }
}
