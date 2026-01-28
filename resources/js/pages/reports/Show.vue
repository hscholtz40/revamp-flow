<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Edit, Download, Printer, Filter, X } from 'lucide-vue-next';

interface Report {
    id: number;
    name: string;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    config: {
        columns: string[];
        group_by?: string;
        sort_by?: string;
        sort_direction?: 'asc' | 'desc';
        show_totals?: boolean;
    };
    created_at: string;
    template?: {
        id: number;
        name: string;
        filters?: {
            date_from?: string;
            date_to?: string;
            status?: string[];
            customer_id?: number[];
            product_id?: number[];
        };
    } | null;
    creator?: {
        id: number;
        name: string;
    } | null;
}

interface ReportData {
    data: any[];
    totals?: Record<string, number>;
    grand_totals?: Record<string, number>;
    count: number;
    grouped_records?: Array<{
        group_value: any;
        group_totals: Record<string, number>;
        records: any[];
    }>;
    current_page?: number;
    last_page?: number;
    per_page?: number;
    from?: number;
    to?: number;
    total_records?: number;
}

interface Customer {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    sku?: string | null;
}

interface Company {
    id: number;
    name: string;
}

interface Props {
    report: Report;
    data: ReportData;
    currentCompany: Company;
    customers?: Customer[];
    products?: Product[];
    filters?: {
        customer_id?: number | number[];
        product_id?: number | number[];
        date_from?: string;
        date_to?: string;
        status?: string | string[];
    };
}

const props = withDefaults(defineProps<Props>(), {
    customers: () => [],
    products: () => [],
    filters: () => ({}),
});

// Filter state - initialize from template filters, then override with request filters
const templateFilters = (props as any).templateFilters || {};
const showFilters = ref(false);
const filterCustomerId = ref<number | null>(
    props.filters?.customer_id 
        ? (Array.isArray(props.filters.customer_id) ? props.filters.customer_id[0] : props.filters.customer_id)
        : (templateFilters.customer_id ? (Array.isArray(templateFilters.customer_id) ? templateFilters.customer_id[0] : templateFilters.customer_id) : null)
);
const filterProductId = ref<number | null>(
    props.filters?.product_id 
        ? (Array.isArray(props.filters.product_id) ? props.filters.product_id[0] : props.filters.product_id)
        : (templateFilters.product_id ? (Array.isArray(templateFilters.product_id) ? templateFilters.product_id[0] : templateFilters.product_id) : null)
);
const filterDateFrom = ref<string>(props.filters?.date_from || templateFilters.date_from || '');
const filterDateTo = ref<string>(props.filters?.date_to || templateFilters.date_to || '');
const filterStatus = ref<string | string[]>(props.filters?.status || templateFilters.status || '');

// Customer search
const customerSearchQuery = ref('');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<Customer[]>([]);
const selectedCustomer = ref<Customer | null>(null);

// Product search
const productSearchQuery = ref('');
const productSearchFocused = ref(false);
const filteredProducts = ref<Product[]>([]);
const selectedProduct = ref<Product | null>(null);

// Initialize selected customer/product from template filters or request filters
if (filterCustomerId.value) {
    const customer = props.customers.find(c => c.id === filterCustomerId.value);
    if (customer) {
        selectedCustomer.value = customer;
        customerSearchQuery.value = customer.name;
    }
}

if (filterProductId.value) {
    const product = props.products.find(p => p.id === filterProductId.value);
    if (product) {
        selectedProduct.value = product;
        productSearchQuery.value = product.sku ? `${product.name} (${product.sku})` : product.name;
    }
}

const formatValue = (value: any): string => {
    if (value === null || value === undefined) return '-';
    if (typeof value === 'number') {
        if (value.toString().includes('.')) {
            return 'R' + value.toFixed(2);
        }
        return value.toString();
    }
    if (typeof value === 'object' && value !== null) {
        return JSON.stringify(value);
    }
    return String(value);
};

const getCellContent = (row: any, column: string, entityType: string) => {
    const value = row[column];
    
    // Customer name link
    if (column === 'customer.name' && row._customer_id) {
        return {
            type: 'link',
            href: `/customers/${row._customer_id}`,
            text: formatValue(value),
        };
    }
    
    // Invoice number link
    if (column === 'invoice_number' && row._id && entityType === 'invoice') {
        return {
            type: 'link',
            href: `/invoices/${row._id}`,
            text: formatValue(value),
        };
    }
    
    // Quote number link
    if (column === 'quote_number' && row._id && entityType === 'quote') {
        return {
            type: 'link',
            href: `/quotes/${row._id}`,
            text: formatValue(value),
        };
    }
    
    // Job number link
    if (column === 'job_number' && row._id && entityType === 'jobcard') {
        return {
            type: 'link',
            href: `/jobcards/${row._id}`,
            text: formatValue(value),
        };
    }
    
    // Default: just return formatted value
    return {
        type: 'text',
        text: formatValue(value),
    };
};

const isGrouped = computed(() => !!props.report.config.group_by);

const hasTemplateFilters = computed(() => {
    const filters = props.report.template?.filters;
    if (!filters) return false;
    return !!(filters.date_from || filters.date_to || filters.customer_id || filters.product_id || filters.status);
});

const hasActiveFilters = computed(() => {
    return !!(filterCustomerId.value || filterProductId.value || filterDateFrom.value || filterDateTo.value || filterStatus.value);
});

const shouldShowFilters = computed(() => {
    return hasTemplateFilters.value || hasActiveFilters.value;
});

const applyFilters = () => {
    const params: Record<string, any> = {};
    
    if (filterCustomerId.value) {
        params.customer_id = filterCustomerId.value;
    }
    if (filterProductId.value) {
        params.product_id = filterProductId.value;
    }
    if (filterDateFrom.value) {
        params.date_from = filterDateFrom.value;
    }
    if (filterDateTo.value) {
        params.date_to = filterDateTo.value;
    }
    if (filterStatus.value) {
        params.status = filterStatus.value;
    }
    
    router.get(`/reports/${props.report.id}`, params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filterCustomerId.value = null;
    filterProductId.value = null;
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterStatus.value = '';
    selectedCustomer.value = null;
    selectedProduct.value = null;
    customerSearchQuery.value = '';
    productSearchQuery.value = '';
    applyFilters();
};

const goToPage = (page: number) => {
    const params: Record<string, any> = { page };
    
    if (filterCustomerId.value) params.customer_id = filterCustomerId.value;
    if (filterProductId.value) params.product_id = filterProductId.value;
    if (filterDateFrom.value) params.date_from = filterDateFrom.value;
    if (filterDateTo.value) params.date_to = filterDateTo.value;
    if (filterStatus.value) params.status = filterStatus.value;
    
    router.get(`/reports/${props.report.id}`, params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Customer search functions
const handleCustomerSearch = async () => {
    if (!customerSearchQuery.value.trim()) {
        filteredCustomers.value = [];
        return;
    }
    
    try {
        const response = await fetch(`/customers/search?q=${encodeURIComponent(customerSearchQuery.value)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (response.ok) {
            filteredCustomers.value = await response.json();
        } else {
            filteredCustomers.value = [];
        }
    } catch (error) {
        console.error('Error searching customers:', error);
        filteredCustomers.value = [];
    }
};

const selectCustomer = (customer: Customer) => {
    selectedCustomer.value = customer;
    filterCustomerId.value = customer.id;
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    applyFilters();
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    filterCustomerId.value = null;
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
    applyFilters();
};

// Product search functions
const handleProductSearch = () => {
    if (!productSearchQuery.value.trim()) {
        filteredProducts.value = [];
        return;
    }
    
    const query = productSearchQuery.value.toLowerCase();
    filteredProducts.value = props.products.filter(product => {
        const nameMatch = product.name?.toLowerCase().includes(query);
        const skuMatch = product.sku?.toLowerCase().includes(query);
        return nameMatch || skuMatch;
    }).slice(0, 10);
};

const selectProduct = (product: Product) => {
    selectedProduct.value = product;
    filterProductId.value = product.id;
    productSearchQuery.value = product.sku ? `${product.name} (${product.sku})` : product.name;
    productSearchFocused.value = false;
    applyFilters();
};

const clearProduct = () => {
    selectedProduct.value = null;
    filterProductId.value = null;
    productSearchQuery.value = '';
    filteredProducts.value = [];
    applyFilters();
};

const exportReport = () => {
    // Build query parameters from current filters
    const params: Record<string, any> = {};
    
    if (filterCustomerId.value) {
        params.customer_id = filterCustomerId.value;
    }
    if (filterProductId.value) {
        params.product_id = filterProductId.value;
    }
    if (filterDateFrom.value) {
        params.date_from = filterDateFrom.value;
    }
    if (filterDateTo.value) {
        params.date_to = filterDateTo.value;
    }
    if (filterStatus.value) {
        params.status = filterStatus.value;
    }
    
    // Build URL with query parameters
    const queryString = new URLSearchParams(params).toString();
    const url = `/reports/${props.report.id}/export${queryString ? '?' + queryString : ''}`;
    
    // Open in new window to trigger download
    window.location.href = url;
};

const printReport = () => {
    window.print();
};
</script>

<template>
    <Head :title="props.report.name" />

    <AppLayout :breadcrumbs="[
        { title: 'Reports', href: '/reports' },
        { title: props.report.name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ props.report.name }}</h1>
                    <p class="text-sm text-gray-600 mt-1">
                        {{ props.report.entity_type }} report
                        <span v-if="props.report.template"> • Template: {{ props.report.template.name }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="printReport"
                        class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 flex items-center gap-2"
                    >
                        <Printer class="w-4 h-4" />
                        Print
                    </button>
                    <button
                        @click="exportReport"
                        class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 flex items-center gap-2"
                    >
                        <Download class="w-4 h-4" />
                        Export
                    </button>
                    <Link
                        :href="`/reports/${props.report.id}/edit`"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 flex items-center gap-2"
                    >
                        <Edit class="w-4 h-4" />
                        Edit
                    </Link>
                </div>
            </div>

            <!-- Grand Totals (at top) -->
            <div v-if="props.data.grand_totals && Object.keys(props.data.grand_totals).length > 0" class="bg-blue-50 rounded-lg border border-blue-200 p-4 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Grand Totals</h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div v-if="props.data.grand_totals.count !== undefined">
                        <span class="text-sm text-gray-600">Total Records:</span>
                        <div class="text-xl font-bold text-gray-900">{{ props.data.grand_totals.count }}</div>
                    </div>
                    <div v-if="props.data.grand_totals.subtotal !== undefined && props.data.grand_totals.subtotal !== null">
                        <span class="text-sm text-gray-600">Subtotal:</span>
                        <div class="text-xl font-bold text-gray-900">R{{ (typeof props.data.grand_totals.subtotal === 'number' ? props.data.grand_totals.subtotal : Number(props.data.grand_totals.subtotal || 0)).toFixed(2) }}</div>
                    </div>
                    <div v-if="props.data.grand_totals.discount_amount !== undefined && props.data.grand_totals.discount_amount !== null">
                        <span class="text-sm text-gray-600">Discount:</span>
                        <div class="text-xl font-bold text-red-600">-R{{ (typeof props.data.grand_totals.discount_amount === 'number' ? props.data.grand_totals.discount_amount : Number(props.data.grand_totals.discount_amount || 0)).toFixed(2) }}</div>
                    </div>
                    <div v-if="props.data.grand_totals.tax_amount !== undefined && props.data.grand_totals.tax_amount !== null">
                        <span class="text-sm text-gray-600">Tax:</span>
                        <div class="text-xl font-bold text-gray-900">R{{ (typeof props.data.grand_totals.tax_amount === 'number' ? props.data.grand_totals.tax_amount : Number(props.data.grand_totals.tax_amount || 0)).toFixed(2) }}</div>
                    </div>
                    <div v-if="props.data.grand_totals.total !== undefined && props.data.grand_totals.total !== null">
                        <span class="text-sm text-gray-600">Total:</span>
                        <div class="text-xl font-bold text-blue-600">R{{ (typeof props.data.grand_totals.total === 'number' ? props.data.grand_totals.total : Number(props.data.grand_totals.total || 0)).toFixed(2) }}</div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div v-if="shouldShowFilters" class="bg-white rounded-lg border p-4 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <Filter class="w-5 h-5" />
                        Filters
                    </h3>
                    <button
                        @click="showFilters = !showFilters"
                        class="text-sm text-blue-600 hover:text-blue-700"
                    >
                        {{ showFilters ? 'Hide' : 'Show' }} Filters
                    </button>
                </div>
                
                <div v-if="showFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Customer Filter -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <div class="relative">
                            <input
                                v-model="customerSearchQuery"
                                @input="handleCustomerSearch"
                                @focus="customerSearchFocused = true"
                                @blur="() => setTimeout(() => customerSearchFocused = false, 200)"
                                type="text"
                                placeholder="Search customer..."
                                class="w-full rounded border px-3 py-2"
                            />
                            <button
                                v-if="selectedCustomer"
                                @click.prevent="clearCustomer"
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                        <div
                            v-if="customerSearchFocused && filteredCustomers.length > 0"
                            class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                        >
                            <div
                                v-for="customer in filteredCustomers"
                                :key="customer.id"
                                @mousedown.prevent="selectCustomer(customer)"
                                class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                            >
                                <div class="font-medium">{{ customer.name }}</div>
                                <div class="text-xs text-gray-500" v-if="customer.account_code">{{ customer.account_code }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Filter -->
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                        <div class="relative">
                            <input
                                v-model="productSearchQuery"
                                @input="handleProductSearch"
                                @focus="productSearchFocused = true"
                                @blur="() => setTimeout(() => productSearchFocused = false, 200)"
                                type="text"
                                placeholder="Search product..."
                                class="w-full rounded border px-3 py-2"
                            />
                            <button
                                v-if="selectedProduct"
                                @click.prevent="clearProduct"
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                            >
                                <X class="w-4 h-4" />
                            </button>
                        </div>
                        <div
                            v-if="productSearchFocused && filteredProducts.length > 0"
                            class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                        >
                            <div
                                v-for="product in filteredProducts"
                                :key="product.id"
                                @mousedown.prevent="selectProduct(product)"
                                class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                            >
                                <div class="font-medium">{{ product.name }}</div>
                                <div class="text-xs text-gray-500" v-if="product.sku">SKU: {{ product.sku }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Date From -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input
                            v-model="filterDateFrom"
                            type="date"
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input
                            v-model="filterDateTo"
                            type="date"
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                </div>

                <div v-if="showFilters" class="flex items-center gap-2 mt-4">
                    <button
                        @click="applyFilters"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        Apply Filters
                    </button>
                    <button
                        @click="clearFilters"
                        class="rounded border px-4 py-2 hover:bg-gray-50"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <!-- Report Info -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Total Records:</span>
                        <span class="font-semibold ml-2">{{ props.data.total_records ?? props.data.count }}</span>
                    </div>
                    <div v-if="filterDateFrom || filterDateTo || (props.report.template?.filters && (props.report.template.filters.date_from || props.report.template.filters.date_to))">
                        <span class="text-gray-600">Date Range:</span>
                        <span class="font-semibold ml-2">
                            {{ filterDateFrom || props.report.template?.filters?.date_from || 'Any' }} to {{ filterDateTo || props.report.template?.filters?.date_to || 'Any' }}
                        </span>
                    </div>
                    <div v-if="props.report.creator">
                        <span class="text-gray-600">Created By:</span>
                        <span class="font-semibold ml-2">{{ props.report.creator.name }}</span>
                    </div>
                </div>
            </div>

            <!-- Grouped Report Display -->
            <div v-if="isGrouped && props.data.grouped_records" class="space-y-6">
                <div
                    v-for="(group, groupIndex) in props.data.grouped_records"
                    :key="groupIndex"
                    class="bg-white rounded-lg border overflow-hidden"
                >
                    <!-- Group Header -->
                    <div class="bg-gray-100 border-b px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ props.report.config.group_by?.replace('_', ' ').replace('.', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}: 
                                    {{ formatValue(group.group_value) }}
                                </h3>
                                <p class="text-sm text-gray-600 mt-1">{{ group.records.length }} record(s)</p>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-600">Group Totals:</div>
                                <div class="font-semibold text-gray-900">
                                    <span v-if="group.group_totals.total !== undefined && group.group_totals.total !== null" class="ml-2">
                                        Total: R{{ typeof group.group_totals.total === 'number' ? group.group_totals.total.toFixed(2) : Number(group.group_totals.total || 0).toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group Records Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th
                                        v-for="column in props.report.config.columns"
                                        :key="column"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{ column.replace('_', ' ').replace('.', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(row, index) in group.records" :key="index" class="hover:bg-gray-50">
                                    <td
                                        v-for="column in props.report.config.columns"
                                        :key="column"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        <template v-if="getCellContent(row, column, props.report.entity_type).type === 'link'">
                                            <Link
                                                :href="getCellContent(row, column, props.report.entity_type).href"
                                                class="text-blue-600 hover:text-blue-800 hover:underline"
                                            >
                                                {{ getCellContent(row, column, props.report.entity_type).text }}
                                            </Link>
                                        </template>
                                        <template v-else>
                                            {{ getCellContent(row, column, props.report.entity_type).text }}
                                        </template>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="group.group_totals && Object.keys(group.group_totals).length > 0" class="bg-gray-50 border-t">
                                <tr>
                                    <td
                                        :colspan="props.report.config.columns.length"
                                        class="px-6 py-4 text-sm font-semibold text-gray-900"
                                    >
                                        Group Totals:
                                        <span
                                            v-for="(value, key) in group.group_totals"
                                            :key="key"
                                            class="ml-4"
                                        >
                                            {{ key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}: 
                                            <span v-if="typeof value === 'number' || (typeof value === 'string' && !isNaN(parseFloat(value)))">
                                                R{{ (typeof value === 'number' ? value : parseFloat(value)).toFixed(2) }}
                                            </span>
                                            <span v-else>{{ value }}</span>
                                        </span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Non-Grouped Report Display -->
            <div v-else class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th
                                    v-for="column in props.report.config.columns"
                                    :key="column"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                >
                                    {{ column.replace('_', ' ').replace('.', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(row, index) in props.data.data" :key="index" class="hover:bg-gray-50">
                                <td
                                    v-for="column in props.report.config.columns"
                                    :key="column"
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                >
                                    <template v-if="getCellContent(row, column, props.report.entity_type).type === 'link'">
                                        <Link
                                            :href="getCellContent(row, column, props.report.entity_type).href"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ getCellContent(row, column, props.report.entity_type).text }}
                                        </Link>
                                    </template>
                                    <template v-else>
                                        {{ getCellContent(row, column, props.report.entity_type).text }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="props.data.totals && Object.keys(props.data.totals).length > 0" class="bg-gray-50 border-t">
                            <tr>
                                <td
                                    :colspan="props.report.config.columns.length"
                                    class="px-6 py-4 text-sm font-semibold text-gray-900"
                                >
                                    Totals:
                                    <span
                                        v-for="(value, key) in props.data.totals"
                                        :key="key"
                                        class="ml-4"
                                    >
                                        {{ key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}: R{{ value.toFixed(2) }}
                                    </span>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.data.current_page && props.data.last_page && props.data.last_page > 1" class="mt-6 flex items-center justify-between bg-white rounded-lg border p-4">
                <div class="text-sm text-gray-700">
                    Showing {{ props.data.from }} to {{ props.data.to }} of {{ props.data.count }} results
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="goToPage(props.data.current_page - 1)"
                        :disabled="props.data.current_page === 1"
                        class="rounded border px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <span class="text-sm text-gray-700">
                        Page {{ props.data.current_page }} of {{ props.data.last_page }}
                    </span>
                    <button
                        @click="goToPage(props.data.current_page + 1)"
                        :disabled="props.data.current_page === props.data.last_page"
                        class="rounded border px-3 py-2 text-sm hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="props.data.count === 0" class="bg-white rounded-lg border p-8 text-center">
                <p class="text-gray-600">No data found matching the report criteria.</p>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .no-print {
        display: none;
    }
}
</style>
