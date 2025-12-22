<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import customers from '@/routes/customers';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import jobcards from '@/routes/jobcards';
import products from '@/routes/products';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    FileText, 
    Receipt, 
    Wrench, 
    UserPlus,
    TrendingUp,
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

// Pie chart calculation functions
const getPieChartPath = (value: number, data: number[]) => {
    const total = data.reduce((sum, item) => sum + item, 0);
    if (total === 0) return '0 251.2'; // Full circle if no data
    const percentage = value / total;
    const circumference = 2 * Math.PI * 40; // radius = 40
    return `${percentage * circumference} ${circumference}`;
};

const getPieChartOffset = (index: number, data: number[]) => {
    const total = data.reduce((sum, item) => sum + item, 0);
    if (total === 0) return 0;
    
    let offset = 0;
    for (let i = 0; i < index; i++) {
        const percentage = data[i] / total;
        const circumference = 2 * Math.PI * 40;
        offset += percentage * circumference;
    }
    return offset;
};

// Modern pie chart with enhanced styling and animations
const createPieChartPath = (data: number[], colors: string[], labels: string[]) => {
    const total = data.reduce((sum, item) => sum + item, 0);
    if (total === 0) return [];
    
    const radius = 45;
    const centerX = 50;
    const centerY = 50;
    const innerRadius = 15; // For donut effect
    let currentAngle = -90; // Start from top
    
    return data.map((value, index) => {
        if (value === 0) return null;
        
        const percentage = value / total;
        const angle = percentage * 360;
        const startAngle = currentAngle;
        const endAngle = currentAngle + angle;
        
        // Handle full circle case (100% single status)
        if (angle >= 360) {
            const pathData = [
                `M ${centerX} ${centerY - radius}`,
                `A ${radius} ${radius} 0 1 1 ${centerX} ${centerY + radius}`,
                `A ${radius} ${radius} 0 1 1 ${centerX} ${centerY - radius}`,
                `M ${centerX} ${centerY - innerRadius}`,
                `A ${innerRadius} ${innerRadius} 0 1 0 ${centerX} ${centerY + innerRadius}`,
                `A ${innerRadius} ${innerRadius} 0 1 0 ${centerX} ${centerY - innerRadius}`,
                'Z'
            ].join(' ');
            
            return {
                path: pathData,
                color: colors[index],
                value: value,
                label: labels[index],
                percentage: Math.round(percentage * 100)
            };
        }
        
        // Outer arc
        const x1 = centerX + radius * Math.cos((startAngle * Math.PI) / 180);
        const y1 = centerY + radius * Math.sin((startAngle * Math.PI) / 180);
        const x2 = centerX + radius * Math.cos((endAngle * Math.PI) / 180);
        const y2 = centerY + radius * Math.sin((endAngle * Math.PI) / 180);
        
        // Inner arc
        const x3 = centerX + innerRadius * Math.cos((endAngle * Math.PI) / 180);
        const y3 = centerY + innerRadius * Math.sin((endAngle * Math.PI) / 180);
        const x4 = centerX + innerRadius * Math.cos((startAngle * Math.PI) / 180);
        const y4 = centerY + innerRadius * Math.sin((startAngle * Math.PI) / 180);
        
        const largeArcFlag = angle > 180 ? 1 : 0;
        
        const pathData = [
            `M ${x1} ${y1}`,
            `A ${radius} ${radius} 0 ${largeArcFlag} 1 ${x2} ${y2}`,
            `L ${x3} ${y3}`,
            `A ${innerRadius} ${innerRadius} 0 ${largeArcFlag} 0 ${x4} ${y4}`,
            'Z'
        ].join(' ');
        
        currentAngle += angle;
        
        return {
            path: pathData,
            color: colors[index],
            value: value,
            label: labels[index],
            percentage: Math.round(percentage * 100)
        };
    }).filter(Boolean);
};
</script>

<style scoped>
@keyframes chartSegment {
    0% {
        opacity: 0;
        transform: scale(0.8);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}

.chart-segment {
    animation: chartSegment 0.6s ease-out forwards;
    opacity: 0;
    stroke: rgba(255, 255, 255, 0.1);
    stroke-width: 0.5;
}

.chart-segment:hover {
    filter: brightness(1.05);
    transform: scale(1.02);
    stroke: rgba(255, 255, 255, 0.3);
    stroke-width: 1;
}

.legend-item {
    transition: all 0.2s ease-in-out;
}

.legend-item:hover {
    transform: translateX(4px);
}
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
            <Card>
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
            <Card>
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

            <!-- Recent Activity -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Activity</CardTitle>
                    <CardDescription>Latest quotes, invoices, and jobcards</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-6 md:grid-cols-3">
                        <!-- Recent Quotes -->
                        <div v-if="recentActivity.recent_quotes.length > 0">
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
                        <div v-if="recentActivity.recent_invoices.length > 0">
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
            <div class="grid gap-6 md:grid-cols-3">
                <!-- Quote Status Pie Chart -->
                <Card>
                    <CardHeader>
                        <CardTitle>Quote Status</CardTitle>
                        <CardDescription>Distribution of quote statuses</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="statusCharts && statusCharts.quote_status" class="space-y-6">
                            <!-- Modern Donut Chart -->
                            <div class="flex justify-center">
                                <div class="relative w-40 h-40 group">
                                    <svg class="w-40 h-40" viewBox="0 0 100 100">
                                        <path
                                            v-for="(segment, index) in createPieChartPath(statusCharts.quote_status.data, statusCharts.quote_status.colors, statusCharts.quote_status.labels)"
                                            :key="index"
                                            :d="segment.path"
                                            :fill="segment.color"
                                            class="chart-segment cursor-pointer"
                                            :style="{ 
                                                transformOrigin: '50% 50%',
                                                animationDelay: `${index * 100}ms`
                                            }"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-lg font-bold text-foreground">
                                            {{ statusCharts.quote_status.data.reduce((a, b) => a + b, 0) }}
                                        </span>
                                        <span class="text-xs text-muted-foreground font-medium">Total</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Modern Legend -->
                            <div class="space-y-3">
                                <div v-for="(label, index) in statusCharts.quote_status.labels" :key="index" 
                                     class="legend-item flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors duration-200">
                                    <div class="flex items-center space-x-3">
                                        <div 
                                            class="w-3 h-3 rounded-full" 
                                            :style="{ backgroundColor: statusCharts.quote_status.colors[index] }"
                                        ></div>
                                        <span class="text-sm font-medium text-foreground">{{ label }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-bold text-foreground">{{ statusCharts.quote_status.data[index] }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            ({{ Math.round((statusCharts.quote_status.data[index] / statusCharts.quote_status.data.reduce((a, b) => a + b, 0)) * 100) }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p>No quote data available</p>
                            <p class="text-xs mt-1">Create some quotes to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Invoice Status Pie Chart -->
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Status</CardTitle>
                        <CardDescription>Distribution of invoice statuses</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="statusCharts && statusCharts.invoice_status" class="space-y-6">
                            <!-- Modern Donut Chart -->
                            <div class="flex justify-center">
                                <div class="relative w-40 h-40 group">
                                    <svg class="w-40 h-40" viewBox="0 0 100 100">
                                        <path
                                            v-for="(segment, index) in createPieChartPath(statusCharts.invoice_status.data, statusCharts.invoice_status.colors, statusCharts.invoice_status.labels)"
                                            :key="index"
                                            :d="segment.path"
                                            :fill="segment.color"
                                            class="chart-segment cursor-pointer"
                                            :style="{ 
                                                transformOrigin: '50% 50%',
                                                animationDelay: `${index * 100}ms`
                                            }"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-lg font-bold text-foreground">
                                            {{ statusCharts.invoice_status.data.reduce((a, b) => a + b, 0) }}
                                        </span>
                                        <span class="text-xs text-muted-foreground font-medium">Total</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Modern Legend -->
                            <div class="space-y-3">
                                <div v-for="(label, index) in statusCharts.invoice_status.labels" :key="index" 
                                     class="legend-item flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors duration-200">
                                    <div class="flex items-center space-x-3">
                                        <div 
                                            class="w-3 h-3 rounded-full" 
                                            :style="{ backgroundColor: statusCharts.invoice_status.colors[index] }"
                                        ></div>
                                        <span class="text-sm font-medium text-foreground">{{ label }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-bold text-foreground">{{ statusCharts.invoice_status.data[index] }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            ({{ Math.round((statusCharts.invoice_status.data[index] / statusCharts.invoice_status.data.reduce((a, b) => a + b, 0)) * 100) }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p>No invoice data available</p>
                            <p class="text-xs mt-1">Create some invoices to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Jobcard Status Pie Chart -->
                <Card>
                    <CardHeader>
                        <CardTitle>Jobcard Status</CardTitle>
                        <CardDescription>Distribution of jobcard statuses</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="statusCharts && statusCharts.jobcard_status" class="space-y-6">
                            <!-- Modern Donut Chart -->
                            <div class="flex justify-center">
                                <div class="relative w-40 h-40 group">
                                    <svg class="w-40 h-40" viewBox="0 0 100 100">
                                        <path
                                            v-for="(segment, index) in createPieChartPath(statusCharts.jobcard_status.data, statusCharts.jobcard_status.colors, statusCharts.jobcard_status.labels)"
                                            :key="index"
                                            :d="segment.path"
                                            :fill="segment.color"
                                            class="chart-segment cursor-pointer"
                                            :style="{ 
                                                transformOrigin: '50% 50%',
                                                animationDelay: `${index * 100}ms`
                                            }"
                                        />
                                    </svg>
                                    <div class="absolute inset-0 flex flex-col items-center justify-center">
                                        <span class="text-lg font-bold text-foreground">
                                            {{ statusCharts.jobcard_status.data.reduce((a, b) => a + b, 0) }}
                                        </span>
                                        <span class="text-xs text-muted-foreground font-medium">Total</span>
                                    </div>
                                </div>
                            </div>
                            <!-- Modern Legend -->
                            <div class="space-y-3">
                                <div v-for="(label, index) in statusCharts.jobcard_status.labels" :key="index" 
                                     class="legend-item flex items-center justify-between p-2 rounded-lg hover:bg-muted/50 transition-colors duration-200">
                                    <div class="flex items-center space-x-3">
                                        <div 
                                            class="w-3 h-3 rounded-full" 
                                            :style="{ backgroundColor: statusCharts.jobcard_status.colors[index] }"
                                        ></div>
                                        <span class="text-sm font-medium text-foreground">{{ label }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-bold text-foreground">{{ statusCharts.jobcard_status.data[index] }}</span>
                                        <span class="text-xs text-muted-foreground">
                                            ({{ Math.round((statusCharts.jobcard_status.data[index] / statusCharts.jobcard_status.data.reduce((a, b) => a + b, 0)) * 100) }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-muted-foreground">
                            <p>No jobcard data available</p>
                            <p class="text-xs mt-1">Create some jobcards to see status distribution</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

        </div>
    </AppLayout>
</template>
