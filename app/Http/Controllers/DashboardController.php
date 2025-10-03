<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Jobcard;
use App\Models\Product;
use App\Models\Contact;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
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
            for ($i = 11; $i >= 0; $i--) {
                $date = \Carbon\Carbon::now()->subMonths($i);
                $userMonthlyRevenue[] = [
                    'month' => $date->format('M Y'),
                    'revenue' => 0,
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
                'recentActivity' => $recentActivity,
                'overdueItems' => $overdueItems,
                'lowStockProducts' => $lowStockProducts,
                'topCustomers' => $topCustomers,
                'monthlyRevenue' => $monthlyRevenue,
                'statusCharts' => $statusCharts,
                'currentCompany' => null,
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
            'current_month_revenue' => Invoice::where('company_id', $currentCompany->id)
                ->where('salesperson_id', auth()->id())
                ->where('status', '!=', 'cancelled')
                ->whereMonth('invoice_date', Carbon::now()->month)
                ->whereYear('invoice_date', Carbon::now()->year)
                ->sum('total'),
        ];

        // User's 12-month revenue history
        $userMonthlyRevenue = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->format('M Y');
            $revenue = Invoice::where('company_id', $currentCompany->id)
                ->where('salesperson_id', auth()->id())
                ->where('status', '!=', 'cancelled')
                ->whereMonth('invoice_date', $date->month)
                ->whereYear('invoice_date', $date->year)
                ->sum('total');
            
            $userMonthlyRevenue[] = [
                'month' => $monthName,
                'revenue' => $revenue
            ];
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
            ->whereRaw('stock_quantity <= min_stock_level')
            ->orderBy('stock_quantity', 'asc')
            ->limit(5)
            ->get();

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

        // Chart data for status distributions
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
                'colors' => ['#6366f1', '#f59e0b', '#10b981', '#ef4444', '#6b7280'], // Indigo, amber, emerald, red, gray
            ],
            'invoice_status' => [
                'labels' => ['Draft', 'Sent', 'Paid', 'Overdue'],
                'data' => [
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'draft')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'sent')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'paid')->count(),
                    Invoice::where('company_id', $currentCompany->id)->where('status', 'overdue')->count(),
                ],
                'colors' => ['#6366f1', '#3b82f6', '#10b981', '#ef4444'], // Indigo, blue, emerald, red
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
                'colors' => ['#6366f1', '#f59e0b', '#3b82f6', '#10b981', '#ef4444'], // Indigo, amber, blue, emerald, red
            ],
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'revenueStats' => $revenueStats,
            'userMonthlyRevenue' => $userMonthlyRevenue,
            'recentActivity' => $recentActivity,
            'overdueItems' => $overdueItems,
            'lowStockProducts' => $lowStockProducts,
            'topCustomers' => $topCustomers,
            'monthlyRevenue' => $monthlyRevenue,
            'statusCharts' => $statusCharts,
            'currentCompany' => $currentCompany,
        ]);
    }
}
