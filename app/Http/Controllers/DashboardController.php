<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Jobcard;
use App\Models\Product;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function netSalesRevenueQuery(int $companyId, ?int $salespersonId = null): Builder
    {
        $creditNoteTotals = DB::table('credit_notes')
            ->select('invoice_id', DB::raw('SUM(total) as allocated_credit_total'))
            ->whereNotNull('invoice_id')
            ->where('status', '!=', 'voided')
            ->groupBy('invoice_id');

        $query = DB::table('invoices')
            ->leftJoinSub($creditNoteTotals, 'credit_note_totals', function ($join) {
                $join->on('invoices.id', '=', 'credit_note_totals.invoice_id');
            })
            ->where('invoices.company_id', $companyId)
            ->where('invoices.status', '!=', 'cancelled');

        if ($salespersonId !== null) {
            $query->where('invoices.salesperson_id', $salespersonId);
        }

        return $query;
    }

    private function getNetSalesRevenueForMonth(int $companyId, Carbon $date, ?int $salespersonId = null): float
    {
        return (float) $this->netSalesRevenueQuery($companyId, $salespersonId)
            ->whereMonth('invoices.invoice_date', $date->month)
            ->whereYear('invoices.invoice_date', $date->year)
            ->selectRaw('COALESCE(SUM(invoices.total - COALESCE(credit_note_totals.allocated_credit_total, 0)), 0) as revenue')
            ->value('revenue');
    }

    public function index(Request $request): Response|RedirectResponse
    {
        if (auth()->user()?->isClientUser()) {
            return redirect()->route('client-zone.dashboard');
        }

        $currentCompany = auth()->user()?->getCurrentCompany();

        // If the user has no accessible/active company, return a safe dashboard with zeroed metrics
        if (!$currentCompany) {
            $stats = [
                'invoices_count' => 0,
                'contacts_count' => 0,
            ];

            $revenueStats = [
                'current_month_revenue' => 0,
            ];

            $userMonthlyRevenue = [];
            $userMonthlyJobcards = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = \Carbon\Carbon::now()->subMonths($i);
                $userMonthlyRevenue[] = [
                    'month' => $date->format('M Y'),
                    'revenue' => 0,
                ];
                $userMonthlyJobcards[] = [
                    'month' => $date->format('M Y'),
                    'count' => 0,
                ];
            }

            $recentActivity = [
                'recent_quotes' => [],
                'recent_invoices' => [],
                'recent_jobcards' => [],
            ];

            $overdueItems = [
                'overdue_invoices' => [],
                'expiring_quotes' => [],
            ];

            $lowStockProducts = [];
            $topCustomers = [];

            $monthlyRevenue = [];
            for ($i = 5; $i >= 0; $i--) {
                $date = \Carbon\Carbon::now()->subMonths($i);
                $monthlyRevenue[] = [
                    'month' => $date->format('M Y'),
                    'revenue' => 0,
                ];
            }

            $statusCharts = [
                'quote_status' => [
                    'labels' => ['Draft', 'Pending', 'Accepted', 'Rejected', 'Expired'],
                    'data' => [0, 0, 0, 0, 0],
                    'colors' => ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#6b7280'],
                ],
                'invoice_status' => [
                    'labels' => ['Draft', 'Sent', 'Paid', 'Overdue'],
                    'data' => [0, 0, 0, 0],
                    'colors' => ['#6366f1', '#3b82f6', '#10b981', '#ef4444'],
                ],
                'jobcard_status' => [
                    'labels' => ['Draft', 'Pending', 'In Progress', 'Completed', 'Cancelled'],
                    'data' => [0, 0, 0, 0, 0],
                    'colors' => ['#6366f1', '#f59e0b', '#3b82f6', '#10b981', '#ef4444'],
                ],
            ];

            return Inertia::render('Dashboard', [
                'stats' => $stats,
                'revenueStats' => $revenueStats,
                'userMonthlyRevenue' => $userMonthlyRevenue,
            'userMonthlyJobcards' => $userMonthlyJobcards,
            'jobcardsPerUser' => [],
            'currentMonthCompletedJobcards' => 0,
            'recentActivity' => $recentActivity,
                'overdueItems' => $overdueItems,
                'lowStockProducts' => $lowStockProducts,
                'topCustomers' => $topCustomers,
                'monthlyRevenue' => $monthlyRevenue,
                'statusCharts' => $statusCharts,
                'currentCompany' => null,
                'canCreateInvoices' => false,
                'isPosEnabled' => false,
                'warning' => 'No active company access is configured for your user. Please contact an administrator.',
            ]);
        }
        
        // Basic counts
        $stats = [
            'invoices_count' => Invoice::where('company_id', $currentCompany->id)->count(),
            'contacts_count' => Contact::where('company_id', $currentCompany->id)->count(),
        ];

        // User-specific revenue statistics
        $revenueStats = [
            'current_month_revenue' => $this->getNetSalesRevenueForMonth(
                $currentCompany->id,
                Carbon::now(),
                auth()->id()
            ),
        ];

        // User's 12-month revenue history
        $userMonthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M Y');
            $revenue = $this->getNetSalesRevenueForMonth(
                $currentCompany->id,
                $date,
                auth()->id()
            );
            
            $userMonthlyRevenue[] = [
                'month' => $monthName,
                'revenue' => $revenue
            ];
        }

        // Jobcard completion data
        $jobcardsPerUser = [];

        if (auth()->user()->isLimitedUser()) {
            // Limited users: show their own 12-month trend
            $userMonthlyJobcards = [];
            for ($i = 11; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $monthName = $date->format('M Y');
                $count = Jobcard::where('company_id', $currentCompany->id)
                    ->where('assigned_to_user_id', auth()->id())
                    ->where('status', 'completed')
                    ->whereMonth('completed_date', $date->month)
                    ->whereYear('completed_date', $date->year)
                    ->count();
                
                $userMonthlyJobcards[] = [
                    'month' => $monthName,
                    'count' => $count,
                ];
            }

            $currentMonthCompletedJobcards = Jobcard::where('company_id', $currentCompany->id)
                ->where('assigned_to_user_id', auth()->id())
                ->where('status', 'completed')
                ->whereMonth('completed_date', Carbon::now()->month)
                ->whereYear('completed_date', Carbon::now()->year)
                ->count();
        } else {
            // Standard users: show completed jobcards per user
            $userMonthlyJobcards = [];

            $companyUserIds = User::whereHas('companies', function ($query) use ($currentCompany) {
                $query->where('companies.id', $currentCompany->id);
            })->pluck('id', 'name');

            foreach ($companyUserIds as $userName => $userId) {
                $count = Jobcard::where('company_id', $currentCompany->id)
                    ->where('assigned_to_user_id', $userId)
                    ->where('status', 'completed')
                    ->count();

                $jobcardsPerUser[] = [
                    'name' => $userName,
                    'count' => $count,
                ];
            }

            // Sort descending by count
            usort($jobcardsPerUser, fn($a, $b) => $b['count'] <=> $a['count']);

            $currentMonthCompletedJobcards = Jobcard::where('company_id', $currentCompany->id)
                ->where('status', 'completed')
                ->whereMonth('completed_date', Carbon::now()->month)
                ->whereYear('completed_date', Carbon::now()->year)
                ->count();
        }

        // Recent activity
        $recentActivity = [
            'recent_quotes' => Quote::where('company_id', $currentCompany->id)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'recent_invoices' => Invoice::where('company_id', $currentCompany->id)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            'recent_jobcards' => Jobcard::where('company_id', $currentCompany->id)
                ->with('customer')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        // Overdue items
        $overdueItems = [
            'overdue_invoices' => Invoice::where('company_id', $currentCompany->id)
                ->where('status', '!=', 'paid')
                ->where('due_date', '<', Carbon::now())
                ->with('customer')
                ->orderBy('due_date', 'asc')
                ->limit(5)
                ->get(),
            'expiring_quotes' => Quote::where('company_id', $currentCompany->id)
                ->where('status', 'pending')
                ->where('expiry_date', '<=', Carbon::now()->addDays(7))
                ->where('expiry_date', '>=', Carbon::now())
                ->with('customer')
                ->orderBy('expiry_date', 'asc')
                ->limit(5)
                ->get(),
        ];

        // Low stock products
        $lowStockProducts = Product::where('company_id', $currentCompany->id)
            ->where('track_stock', true)
            ->where('is_active', true)
            ->get()
            ->filter(function ($product) {
                $threshold = $product->low_stock_threshold ?? $product->min_stock_level ?? 10;
                return $product->stock_quantity <= $threshold;
            })
            ->sortBy('stock_quantity')
            ->take(10)
            ->values();

        // Top customers by revenue
        $topCustomers = Customer::where('company_id', $currentCompany->id)
            ->withSum('invoices', 'total')
            ->orderBy('invoices_sum_total', 'desc')
            ->limit(5)
            ->get();

        // Monthly revenue for chart (last 6 months)
        $monthlyRevenue = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $revenue = Invoice::where('company_id', $currentCompany->id)
                ->where('status', 'paid')
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->sum('total');
            
            $monthlyRevenue[] = [
                'month' => $date->format('M Y'),
                'revenue' => $revenue,
            ];
        }

        // Chart data for status distributions with modern brand colors
        $statusCharts = [
            'quote_status' => [
                'labels' => ['Draft', 'Pending', 'Accepted', 'Rejected', 'Expired'],
                'data' => [
                    Quote::where('company_id', $currentCompany->id)->where('status', 'draft')->count(),
                    Quote::where('company_id', $currentCompany->id)->where('status', 'pending')->count(),
                    Quote::where('company_id', $currentCompany->id)->where('status', 'accepted')->count(),
                    Quote::where('company_id', $currentCompany->id)->where('status', 'rejected')->count(),
                    Quote::where('company_id', $currentCompany->id)->where('status', 'expired')->count(),
                ],
                // Brand colors: Dark Blue (#2B5DA7), Teal (#2CBDB9), Lighter Blue (#3F72B5), Red, Gray
                'colors' => ['#2B5DA7', '#2CBDB9', '#3F72B5', '#ef4444', '#6b7280'],
            ],
            'invoice_status' => [
                'labels' => ['Draft', 'Sent', 'Paid', 'Overdue'],
                'data' => [
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'draft')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'sent')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'paid')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'overdue')->count(),
                ],
                // Brand colors: Dark Blue, Teal, Green (success), Red (danger)
                'colors' => ['#2B5DA7', '#2CBDB9', '#10b981', '#ef4444'],
            ],
            'jobcard_status' => [
                'labels' => ['Draft', 'Pending', 'In Progress', 'Completed', 'Cancelled'],
                'data' => [
                    Jobcard::where('company_id', $currentCompany->id)->where('status', 'draft')->count(),
                    Jobcard::where('company_id', $currentCompany->id)->where('status', 'pending')->count(),
                    Jobcard::where('company_id', $currentCompany->id)->where('status', 'in_progress')->count(),
                    Jobcard::where('company_id', $currentCompany->id)->where('status', 'completed')->count(),
                    Jobcard::where('company_id', $currentCompany->id)->where('status', 'cancelled')->count(),
                ],
                // Brand colors: Dark Blue, Teal, Lighter Blue, Green (success), Red (danger)
                'colors' => ['#2B5DA7', '#2CBDB9', '#3F72B5', '#10b981', '#ef4444'],
            ],
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'revenueStats' => $revenueStats,
            'userMonthlyRevenue' => $userMonthlyRevenue,
            'userMonthlyJobcards' => $userMonthlyJobcards,
            'jobcardsPerUser' => $jobcardsPerUser,
            'currentMonthCompletedJobcards' => $currentMonthCompletedJobcards,
            'recentActivity' => $recentActivity,
            'overdueItems' => $overdueItems,
            'lowStockProducts' => $lowStockProducts,
            'topCustomers' => $topCustomers,
            'monthlyRevenue' => $monthlyRevenue,
            'statusCharts' => $statusCharts,
            'currentCompany' => $currentCompany,
            'canCreateInvoices' => auth()->user()->hasModulePermission('invoices', 'create'),
            'isPosEnabled' => (bool) ($currentCompany->enable_pos ?? false),
        ]);
    }
}
