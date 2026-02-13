<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import jobcards from '@/routes/jobcards';
import products from '@/routes/products';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    FileText, 
    Receipt, 
    Wrench, 
    UserPlus,
    TrendingUp,
    CheckCircle2,
    Package,
    AlertTriangle
} from 'lucide-vue-next';

interface Props {
    stats: {
        invoices_count: number;
        contacts_count: number;
    };
    revenueStats: {
        current_month_revenue: number;
    };
    userMonthlyRevenue: {
        month: string;
        revenue: number;
    }[];
    userMonthlyJobcards: {
        month: string;
        count: number;
    }[];
    jobcardsPerUser: {
        name: string;
        count: number;
    }[];
    currentMonthCompletedJobcards: number;
    recentActivity: {
        recent_quotes: any[];
        recent_invoices: any[];
        recent_jobcards: any[];
    };
    overdueItems: {
        overdue_invoices: any[];
        expiring_quotes: any[];
    };
    lowStockProducts: any[];
    topCustomers: any[];
    monthlyRevenue: Array<{ month: string; revenue: number }>;
    statusCharts: {
        quote_status: { labels: string[]; data: number[]; colors: string[] };
        invoice_status: { labels: string[]; data: number[]; colors: string[] };
        jobcard_status: { labels: string[]; data: number[]; colors: string[] };
    };
    currentCompany: any;
}

const props = defineProps<Props>();

const page = usePage();
const isLimitedUser = computed(() => (page.props.auth as any)?.user?.user_type === 'limited');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: 'ZAR',
    }).format(amount);
};

// Bar chart height calculation
const getBarHeight = (revenue: number) => {
    if (!props.userMonthlyRevenue || props.userMonthlyRevenue.length === 0) return 8;
    
    const maxRevenue = Math.max(...props.userMonthlyRevenue.map(item => item.revenue));
    
    // If all values are zero, show minimum height for all bars
    if (maxRevenue === 0) return 8;
    
    // If this specific bar has zero revenue, show minimum height
    if (revenue === 0) return 8;
    
    // Calculate pixel height based on the maximum revenue
    // Use 100px for the tallest bar, scale others proportionally
    const maxHeight = 100; // pixels
    const height = (revenue / maxRevenue) * maxHeight;
    return Math.max(height, 8);
};

// Jobcard bar chart height calculation
const getJobcardBarHeight = (count: number) => {
    if (!props.userMonthlyJobcards || props.userMonthlyJobcards.length === 0) return 8;
    
    const maxCount = Math.max(...props.userMonthlyJobcards.map(item => item.count));
    
    if (maxCount === 0) return 8;
    if (count === 0) return 8;
    
    const maxHeight = 100;
    const height = (count / maxCount) * maxHeight;
    return Math.max(height, 8);
};

// Per-user jobcard bar chart height calculation
const getPerUserBarHeight = (count: number) => {
    if (!props.jobcardsPerUser || props.jobcardsPerUser.length === 0) return 8;
    
    const maxCount = Math.max(...props.jobcardsPerUser.map(item => item.count));
    
    if (maxCount === 0) return 8;
    if (count === 0) return 8;
    
    const maxHeight = 100;
    const height = (count / maxCount) * maxHeight;
    return Math.max(height, 8);
};

const getStatusColor = (status: string) => {
    switch (status?.toLowerCase()) {
        case 'paid':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
        case 'overdue':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300';
        case 'draft':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
        default:
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300';
    }
};

</script>

<style scoped>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

.animate-shimmer {
    animation: shimmer 2s infinite;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.group\/item {
    animation: slideIn 0.4s ease-out forwards;
    opacity: 0;
}

.group\/item:nth-child(1) { animation-delay: 0ms; }
.group\/item:nth-child(2) { animation-delay: 100ms; }
.group\/item:nth-child(3) { animation-delay: 200ms; }
.group\/item:nth-child(4) { animation-delay: 300ms; }
.group\/item:nth-child(5) { animation-delay: 400ms; }
</style>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
            <!-- Welcome Section -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
                    <p class="text-muted-foreground">
                        Welcome back! Here's what's happening with your business.
                    </p>
                </div>
            </div>

            <!-- Quick Actions -->
            <Card v-if="!isLimitedUser">
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>Create new items quickly</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-2 md:grid-cols-4">
                        <Button as-child class="w-full justify-start">
                            <Link :href="quotes.create().url">
                                <FileText class="mr-2 h-4 w-4" />
                                New Quote
                            </Link>
                        </Button>
                        <Button as-child class="w-full justify-start" variant="outline">
                            <Link :href="invoices.create().url">
                                <Receipt class="mr-2 h-4 w-4" />
                                New Invoice
                            </Link>
                        </Button>
                        <Button as-child class="w-full justify-start" variant="outline">
                            <Link :href="jobcards.create().url">
                                <Wrench class="mr-2 h-4 w-4" />
                                New Jobcard
                            </Link>
                        </Button>
                        <Button as-child class="w-full justify-start" variant="outline">
                            <Link :href="customers.create().url">
                                <UserPlus class="mr-2 h-4 w-4" />
                                New Customer
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Current Month Revenue with 12-Month Chart -->
            <Card v-if="!isLimitedUser">
                <CardHeader>
                    <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="text-lg font-semibold">Your Sales Performance</CardTitle>
                        <CardDescription>Current month revenue and 12-month trend</CardDescription>
                    </div>
                        <TrendingUp class="h-5 w-5 text-muted-foreground" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-2 md:grid-cols-4">
                        <!-- Current Month Stats -->
                        <div class="space-y-1">
                            <div>
                                <div class="text-3xl font-bold">{{ formatCurrency(revenueStats.current_month_revenue) }}</div>
                                <p class="text-sm text-muted-foreground">Current Month Revenue</p>
                            </div>
                        </div>
                        
                        <!-- 12-Month Chart -->
                        <div class="md:col-span-3">
                            <h4 class="text-sm font-medium mb-2">12-Month Revenue Trend</h4>
                            <div v-if="userMonthlyRevenue && userMonthlyRevenue.length > 0" class="h-28 flex items-end justify-between gap-2">
                                <div v-for="(data, index) in userMonthlyRevenue" :key="index" class="flex flex-col items-center flex-1">
                                    <div class="text-xs text-muted-foreground mb-1">{{ data.month }}</div>
                                    <div 
                                        class="w-full bg-blue-500 rounded-t transition-all duration-300 hover:bg-blue-600 cursor-pointer"
                                        :style="{ height: getBarHeight(data.revenue) + 'px', minHeight: '8px' }"
                                        :title="formatCurrency(data.revenue)"
                                    ></div>
                                    <div class="text-xs text-muted-foreground mt-1 text-center">
                                        {{ formatCurrency(data.revenue) }}
                                    </div>
                                </div>
                            </div>
                            <div v-else class="h-28 flex items-center justify-center text-muted-foreground border-2 border-dashed border-gray-300 rounded-lg">
                                <div class="text-center">
                                    <div class="text-sm font-medium">No revenue data</div>
                                    <div class="text-xs">Create and mark invoices as paid to see trends</div>
                                </div>
                            </div>
                            <div class="mt-2 text-xs text-muted-foreground text-center">
                                Hover over bars to see exact amounts
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Jobcard Completions Chart -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle class="text-lg font-semibold">
                                {{ isLimitedUser ? 'Your Jobcard Completions' : 'Jobcard Completions by User' }}
                            </CardTitle>
                            <CardDescription>
                                {{ isLimitedUser ? 'Completed jobcards per month over the last 12 months' : 'All completed jobcards broken down by user' }}
                            </CardDescription>
                        </div>
                        <CheckCircle2 class="h-5 w-5 text-muted-foreground" />
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Standard users: per-user breakdown -->
                    <div v-if="!isLimitedUser">
                        <div class="grid gap-2 md:grid-cols-4">
                            <div class="space-y-1">
                                <div>
                                    <div class="text-3xl font-bold">{{ currentMonthCompletedJobcards }}</div>
                                    <p class="text-sm text-muted-foreground">Completed This Month</p>
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <h4 class="text-sm font-medium mb-2">Completed Jobcards per User</h4>
                                <div v-if="jobcardsPerUser && jobcardsPerUser.length > 0" class="h-28 flex items-end justify-between gap-2">
                                    <div v-for="(data, index) in jobcardsPerUser" :key="index" class="flex flex-col items-center flex-1">
                                        <div class="text-xs text-muted-foreground mb-1 truncate max-w-full" :title="data.name">{{ data.name.split(' ')[0] }}</div>
                                        <div 
                                            class="w-full bg-emerald-500 rounded-t transition-all duration-300 hover:bg-emerald-600 cursor-pointer"
                                            :style="{ height: getPerUserBarHeight(data.count) + 'px', minHeight: '8px' }"
                                            :title="`${data.name}: ${data.count} completed`"
                                        ></div>
                                        <div class="text-xs text-muted-foreground mt-1 text-center">
                                            {{ data.count }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="h-28 flex items-center justify-center text-muted-foreground border-2 border-dashed border-gray-300 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-sm font-medium">No completion data</div>
                                        <div class="text-xs">Complete jobcards to see user stats</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground text-center">
                                    Hover over bars to see full name and count
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Limited users: own 12-month trend -->
                    <div v-else>
                        <div class="grid gap-2 md:grid-cols-4">
                            <div class="space-y-1">
                                <div>
                                    <div class="text-3xl font-bold">{{ currentMonthCompletedJobcards }}</div>
                                    <p class="text-sm text-muted-foreground">Completed This Month</p>
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <h4 class="text-sm font-medium mb-2">12-Month Completion Trend</h4>
                                <div v-if="userMonthlyJobcards && userMonthlyJobcards.length > 0" class="h-28 flex items-end justify-between gap-2">
                                    <div v-for="(data, index) in userMonthlyJobcards" :key="index" class="flex flex-col items-center flex-1">
                                        <div class="text-xs text-muted-foreground mb-1">{{ data.month }}</div>
                                        <div 
                                            class="w-full bg-emerald-500 rounded-t transition-all duration-300 hover:bg-emerald-600 cursor-pointer"
                                            :style="{ height: getJobcardBarHeight(data.count) + 'px', minHeight: '8px' }"
                                            :title="`${data.count} completed`"
                                        ></div>
                                        <div class="text-xs text-muted-foreground mt-1 text-center">
                                            {{ data.count }}
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="h-28 flex items-center justify-center text-muted-foreground border-2 border-dashed border-gray-300 rounded-lg">
                                    <div class="text-center">
                                        <div class="text-sm font-medium">No completion data</div>
                                        <div class="text-xs">Complete jobcards to see your trends</div>
                                    </div>
                                </div>
                                <div class="mt-2 text-xs text-muted-foreground text-center">
                                    Hover over bars to see exact counts
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Recent Activity -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                    <CardDescription>Latest quotes, invoices, and jobcards</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <!-- Recent Quotes -->
                        <div v-if="!isLimitedUser && recentActivity.recent_quotes.length > 0">
                            <h4 class="text-sm font-medium mb-2">Recent Quotes</h4>
                            <div class="space-y-2">
                                <Link 
                                    v-for="quote in recentActivity.recent_quotes" 
                                    :key="quote.id"
                                    :href="quotes.show(quote.id).url"
                                    class="flex items-center justify-between p-2 rounded-lg border hover:bg-muted/50 transition-colors duration-200 cursor-pointer"
                                >
                                    <div class="flex items-center space-x-3">
                                        <FileText class="h-4 w-4 text-blue-500" />
                                        <div>
                                            <p class="text-sm font-medium">{{ quote.quote_number }}</p>
                                            <p class="text-xs text-muted-foreground">{{ quote.customer?.name }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ formatCurrency(quote.total) }}</p>
                                        <Badge :class="getStatusColor(quote.status)">{{ quote.status }}</Badge>
                                    </div>
                                </Link>
                            </div>
                </div>
                        
                        <!-- Recent Invoices -->
                        <div v-if="!isLimitedUser && recentActivity.recent_invoices.length > 0">
                            <h4 class="text-sm font-medium mb-2">Recent Invoices</h4>
                            <div class="space-y-2">
                                <Link 
                                    v-for="invoice in recentActivity.recent_invoices" 
                                    :key="invoice.id"
                                    :href="invoices.show(invoice.id).url"
                                    class="flex items-center justify-between p-2 rounded-lg border hover:bg-muted/50 transition-colors duration-200 cursor-pointer"
                                >
                                    <div class="flex items-center space-x-3">
                                        <Receipt class="h-4 w-4 text-green-500" />
                                        <div>
                                            <p class="text-sm font-medium">{{ invoice.invoice_number }}</p>
                                            <p class="text-xs text-muted-foreground">{{ invoice.customer?.name }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ formatCurrency(invoice.total) }}</p>
                                        <Badge :class="getStatusColor(invoice.status)">{{ invoice.status }}</Badge>
                                    </div>
                                </Link>
                </div>
            </div>

                        <!-- Recent Jobcards -->
                        <div v-if="recentActivity.recent_jobcards.length > 0">
                            <h4 class="text-sm font-medium mb-2">Recent Jobcards</h4>
                            <div class="space-y-2">
                                <Link 
                                    v-for="jobcard in recentActivity.recent_jobcards" 
                                    :key="jobcard.id"
                                    :href="jobcards.show(jobcard.id).url"
                                    class="flex items-center justify-between p-2 rounded-lg border hover:bg-muted/50 transition-colors duration-200 cursor-pointer"
                                >
                                    <div class="flex items-center space-x-3">
                                        <Wrench class="h-4 w-4 text-orange-500" />
                                        <div>
                                            <p class="text-sm font-medium">{{ jobcard.jobcard_number }}</p>
                                            <p class="text-xs text-muted-foreground">{{ jobcard.customer?.name }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium">{{ formatCurrency(jobcard.total) }}</p>
                                        <Badge :class="getStatusColor(jobcard.status)">{{ jobcard.status }}</Badge>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Low Stock Products Alert -->
            <Card v-if="lowStockProducts && lowStockProducts.length > 0" class="border-orange-200 bg-orange-50/50">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <AlertTriangle class="h-5 w-5 text-orange-600" />
                            <CardTitle class="text-lg font-semibold text-orange-900">Low Stock Alert</CardTitle>
                        </div>
                        <Badge variant="destructive" class="bg-orange-600">{{ lowStockProducts.length }} Product{{ lowStockProducts.length !== 1 ? 's' : '' }}</Badge>
                    </div>
                    <CardDescription class="text-orange-700">Products that need restocking</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <Link 
                            v-for="product in lowStockProducts" 
                            :key="product.id"
                            :href="products.show(product.id).url"
                            class="flex items-center justify-between p-3 rounded-lg border border-orange-200 bg-white hover:bg-orange-50 transition-colors duration-200 cursor-pointer"
                        >
                            <div class="flex items-center space-x-3">
                                <Package class="h-4 w-4 text-orange-600" />
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ product.name }}</p>
                                    <p v-if="product.sku" class="text-xs text-gray-500">SKU: {{ product.sku }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold text-orange-600">{{ product.stock_quantity }}</p>
                                <p class="text-xs text-gray-500">in stock</p>
                            </div>
                        </Link>
                    </div>
                    <div class="mt-4">
                        <Button as-child variant="outline" class="w-full border-orange-300 text-orange-700 hover:bg-orange-100">
                            <Link :href="products.index().url">
                                View All Products
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Status Charts Section -->
            <div class="grid gap-6" :class="isLimitedUser ? 'md:grid-cols-1' : 'md:grid-cols-3'">
                <!-- Quote Status Bar Chart -->
                <Card v-if="!isLimitedUser" class="relative overflow-hidden border-0 bg-white shadow-lg hover:shadow-xl transition-all duration-300">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-bold">Quote Status</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">Distribution of quote statuses</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0 pb-6">
                        <div v-if="statusCharts && statusCharts.quote_status && statusCharts.quote_status.data.reduce((a, b) => a + b, 0) > 0" class="space-y-4">
                            <div v-for="(label, index) in statusCharts.quote_status.labels" :key="index" 
                                 class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-700">{{ label }}</span>
                                    <span 
                                        class="text-base font-bold"
                                        :style="{ color: statusCharts.quote_status.colors[index] }"
                                    >
                                        {{ Math.round((statusCharts.quote_status.data[index] / statusCharts.quote_status.data.reduce((a, b) => a + b, 0)) * 100) }}%
                                    </span>
                                </div>
                                <!-- Fully Rounded Horizontal Bar -->
                                <div class="relative h-6 rounded-full shadow-md overflow-hidden"
                                     :style="{ 
                                         backgroundColor: `${statusCharts.quote_status.colors[index]}20`
                                     }">
                                    <div 
                                        class="absolute left-0 top-0 h-full rounded-full transition-all duration-700 ease-out"
                                        :style="{ 
                                            width: `${(statusCharts.quote_status.data[index] / statusCharts.quote_status.data.reduce((a, b) => a + b, 0)) * 100}%`,
                                            backgroundColor: statusCharts.quote_status.colors[index],
                                            boxShadow: `0 2px 4px ${statusCharts.quote_status.colors[index]}40`
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-muted-foreground">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 flex items-center justify-center">
                                <FileText class="h-8 w-8 text-gray-400" />
                            </div>
                            <p class="font-semibold">No quote data available</p>
                            <p class="text-xs mt-1 text-muted-foreground">Create some quotes to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Invoice Status Bar Chart -->
                <Card v-if="!isLimitedUser" class="relative overflow-hidden border-0 bg-white shadow-lg hover:shadow-xl transition-all duration-300">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-bold">Invoice Status</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">Distribution of invoice statuses</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0 pb-6">
                        <div v-if="statusCharts && statusCharts.invoice_status && statusCharts.invoice_status.data.reduce((a, b) => a + b, 0) > 0" class="space-y-4">
                            <div v-for="(label, index) in statusCharts.invoice_status.labels" :key="index" 
                                 class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-700">{{ label }}</span>
                                    <span 
                                        class="text-base font-bold"
                                        :style="{ color: statusCharts.invoice_status.colors[index] }"
                                    >
                                        {{ Math.round((statusCharts.invoice_status.data[index] / statusCharts.invoice_status.data.reduce((a, b) => a + b, 0)) * 100) }}%
                                    </span>
                                </div>
                                <!-- Fully Rounded Horizontal Bar -->
                                <div class="relative h-6 rounded-full shadow-md overflow-hidden"
                                     :style="{ 
                                         backgroundColor: `${statusCharts.invoice_status.colors[index]}20`
                                     }">
                                    <div 
                                        class="absolute left-0 top-0 h-full rounded-full transition-all duration-700 ease-out"
                                        :style="{ 
                                            width: `${(statusCharts.invoice_status.data[index] / statusCharts.invoice_status.data.reduce((a, b) => a + b, 0)) * 100}%`,
                                            backgroundColor: statusCharts.invoice_status.colors[index],
                                            boxShadow: `0 2px 4px ${statusCharts.invoice_status.colors[index]}40`
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-muted-foreground">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 flex items-center justify-center">
                                <Receipt class="h-8 w-8 text-gray-400" />
                            </div>
                            <p class="font-semibold">No invoice data available</p>
                            <p class="text-xs mt-1 text-muted-foreground">Create some invoices to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Jobcard Status Bar Chart -->
                <Card class="relative overflow-hidden border-0 bg-white shadow-lg hover:shadow-xl transition-all duration-300">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-bold">Jobcard Status</CardTitle>
                        <CardDescription class="text-xs text-muted-foreground">Distribution of jobcard statuses</CardDescription>
                    </CardHeader>
                    <CardContent class="pt-0 pb-6">
                        <div v-if="statusCharts && statusCharts.jobcard_status && statusCharts.jobcard_status.data.reduce((a, b) => a + b, 0) > 0" class="space-y-4">
                            <div v-for="(label, index) in statusCharts.jobcard_status.labels" :key="index" 
                                 class="relative">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-semibold text-gray-700">{{ label }}</span>
                                    <span 
                                        class="text-base font-bold"
                                        :style="{ color: statusCharts.jobcard_status.colors[index] }"
                                    >
                                        {{ Math.round((statusCharts.jobcard_status.data[index] / statusCharts.jobcard_status.data.reduce((a, b) => a + b, 0)) * 100) }}%
                                    </span>
                                </div>
                                <!-- Fully Rounded Horizontal Bar -->
                                <div class="relative h-6 rounded-full shadow-md overflow-hidden"
                                     :style="{ 
                                         backgroundColor: `${statusCharts.jobcard_status.colors[index]}20`
                                     }">
                                    <div 
                                        class="absolute left-0 top-0 h-full rounded-full transition-all duration-700 ease-out"
                                        :style="{ 
                                            width: `${(statusCharts.jobcard_status.data[index] / statusCharts.jobcard_status.data.reduce((a, b) => a + b, 0)) * 100}%`,
                                            backgroundColor: statusCharts.jobcard_status.colors[index],
                                            boxShadow: `0 2px 4px ${statusCharts.jobcard_status.colors[index]}40`
                                        }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-12 text-muted-foreground">
                            <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-gray-100 flex items-center justify-center">
                                <Wrench class="h-8 w-8 text-gray-400" />
                            </div>
                            <p class="font-semibold">No jobcard data available</p>
                            <p class="text-xs mt-1 text-muted-foreground">Create some jobcards to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

        </div>
    </AppLayout>
</template>
