<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Save, Eye, Plus, X } from 'lucide-vue-next';
import { JOBCARD_STATUS_KEYS } from '@/lib/jobcardStatuses';

interface ReportTemplate {
    id: number;
    name: string;
    description: string | null;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    config: any;
    filters?: {
        date_from?: string;
        date_to?: string;
        status?: string[];
        customer_id?: number[];
        product_id?: number[];
    };
    is_default: boolean;
}

interface Company {
    id: number;
    name: string;
}

interface Customer {
    id: number;
    name: string;
    account_code?: string | null;
}

interface Product {
    id: number;
    name: string;
    sku?: string | null;
}

interface ReportColumn {
    value: string;
    label: string;
    groupable?: boolean;
    sortable?: boolean;
}

interface Props {
    templates: ReportTemplate[];
    currentCompany: Company;
    entityType?: 'invoice' | 'quote' | 'jobcard';
    template?: ReportTemplate | null;
    customers?: Customer[];
    products?: Product[];
}

const props = withDefaults(defineProps<Props>(), {
    entityType: 'invoice',
    template: null,
    customers: () => [],
    products: () => [],
});

// Available columns for each entity type
const availableColumns: Record<'invoice' | 'quote' | 'jobcard', ReportColumn[]> = {
    invoice: [
        { value: 'invoice_number', label: 'Invoice Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'salesperson.name', label: 'Salesperson' },
        { value: 'formatted_date', label: 'Invoice Date' },
        { value: 'due_date', label: 'Due Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
        { value: 'payment_count', label: 'Payments Count', groupable: false, sortable: false },
        { value: 'last_payment_date', label: 'Last Payment Date', groupable: false, sortable: false },
        { value: 'payments_summary', label: 'Payments (Type + Amount)', groupable: false, sortable: false },
        { value: 'total_paid', label: 'Total Paid', groupable: false, sortable: false },
        { value: 'remaining_balance', label: 'Balance Due', groupable: false, sortable: false },
    ],
    quote: [
        { value: 'quote_number', label: 'Quote Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'formatted_date', label: 'Date' },
        { value: 'expiry_date', label: 'Expiry Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
    ],
    jobcard: [
        { value: 'job_number', label: 'Job Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'formatted_date', label: 'Start Date' },
        { value: 'due_date', label: 'Due Date' },
        { value: 'completed_date', label: 'Completed Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
    ],
};

const entityType = ref(props.entityType);
const selectedColumns = ref<string[]>(props.template?.config?.columns || availableColumns[entityType.value].slice(0, 5).map(c => c.value));
const groupBy = ref(props.template?.config?.group_by || '');
const sortBy = ref(props.template?.config?.sort_by || 'created_at');
const sortDirection = ref<'asc' | 'desc'>(props.template?.config?.sort_direction || 'desc');
const showTotals = ref(props.template?.config?.show_totals ?? true);
const groupableColumns = computed(() => availableColumns[entityType.value].filter(column => column.groupable !== false));
const sortableColumns = computed(() => availableColumns[entityType.value].filter(column => column.sortable !== false));

// Filters - these will be saved to template when "Save as Template" is clicked
const dateFrom = ref(props.template?.filters?.date_from || '');
const dateTo = ref(props.template?.filters?.date_to || '');
const statusFilter = ref<string[]>(props.template?.filters?.status || []);
const customerFilter = ref<number[]>(props.template?.filters?.customer_id || []);
const productFilter = ref<number[]>(props.template?.filters?.product_id || []);

// Customer search
const customerSearchQuery = ref('');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<Customer[]>([]);
const selectedCustomers = ref<Customer[]>([]);

// Product search
const productSearchQuery = ref('');
const productSearchFocused = ref(false);
const filteredProducts = ref<Product[]>([]);
const selectedProducts = ref<Product[]>([]);

// Initialize selected customers/products from template filters
if (customerFilter.value.length > 0) {
    selectedCustomers.value = props.customers.filter(c => customerFilter.value.includes(c.id));
}

if (productFilter.value.length > 0) {
    selectedProducts.value = props.products.filter(p => productFilter.value.includes(p.id));
}

const form = useForm({
    name: props.template?.name || '',
    entity_type: entityType.value,
    config: {
        columns: selectedColumns.value,
        group_by: groupBy.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
        show_totals: showTotals.value,
    },
    filters: {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        status: statusFilter.value,
        customer_id: customerFilter.value,
        product_id: productFilter.value,
    },
    report_template_id: props.template?.id || null,
});

// Watch for entity type changes
watch(entityType, (newType) => {
    form.entity_type = newType;
    selectedColumns.value = availableColumns[newType].slice(0, 5).map(c => c.value);
    form.config.columns = selectedColumns.value;
    groupBy.value = '';
    form.config.group_by = '';
});

// Watch for config changes
watch([selectedColumns, groupBy, sortBy, sortDirection, showTotals], () => {
    form.config = {
        columns: selectedColumns.value,
        group_by: groupBy.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
        show_totals: showTotals.value,
    };
});

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

const toggleCustomer = (customer: Customer) => {
    const index = selectedCustomers.value.findIndex(c => c.id === customer.id);
    if (index > -1) {
        selectedCustomers.value.splice(index, 1);
    } else {
        selectedCustomers.value.push(customer);
    }
    customerFilter.value = selectedCustomers.value.map(c => c.id);
    customerSearchQuery.value = '';
    customerSearchFocused.value = false;
};

const removeCustomer = (customerId: number) => {
    selectedCustomers.value = selectedCustomers.value.filter(c => c.id !== customerId);
    customerFilter.value = selectedCustomers.value.map(c => c.id);
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

const toggleProduct = (product: Product) => {
    const index = selectedProducts.value.findIndex(p => p.id === product.id);
    if (index > -1) {
        selectedProducts.value.splice(index, 1);
    } else {
        selectedProducts.value.push(product);
    }
    productFilter.value = selectedProducts.value.map(p => p.id);
    productSearchQuery.value = '';
    productSearchFocused.value = false;
};

const removeProduct = (productId: number) => {
    selectedProducts.value = selectedProducts.value.filter(p => p.id !== productId);
    productFilter.value = selectedProducts.value.map(p => p.id);
};

const hideCustomerSearch = () => {
    window.setTimeout(() => {
        customerSearchFocused.value = false;
    }, 200);
};

const hideProductSearch = () => {
    window.setTimeout(() => {
        productSearchFocused.value = false;
    }, 200);
};

// Watch for filter changes
watch([dateFrom, dateTo, statusFilter, customerFilter, productFilter], () => {
    form.filters = {
        date_from: dateFrom.value,
        date_to: dateTo.value,
        status: statusFilter.value,
        customer_id: customerFilter.value,
        product_id: productFilter.value,
    };
});

const toggleColumn = (columnValue: string) => {
    const index = selectedColumns.value.indexOf(columnValue);
    if (index > -1) {
        selectedColumns.value.splice(index, 1);
    } else {
        selectedColumns.value.push(columnValue);
    }
};

const getStatusOptions = (type: string): string[] => {
    switch (type) {
        case 'invoice':
            return ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        case 'quote':
            return ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        case 'jobcard':
            return [...JOBCARD_STATUS_KEYS];
        default:
            return [];
    }
};

const submit = () => {
    // Remove filters from form when creating report (reports don't have filters)
    const { filters: _filters, ...reportForm } = form.data();
    form.transform(() => reportForm).post('/reports');
};

const saveAsTemplate = () => {
    // Save as template includes filters
    form.post('/reports/save-template');
};
</script>

<template>
    <Head title="Run Report" />

    <AppLayout :breadcrumbs="[
        { title: 'Reports', href: '/reports' },
        { title: 'Create', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Run Report</h1>
                <div class="flex items-center gap-2">
                    <button
                        @click="saveAsTemplate"
                        class="rounded bg-gray-600 px-4 py-2 text-white hover:bg-gray-700 flex items-center gap-2"
                    >
                        <Save class="w-4 h-4" />
                        Save as Template
                    </button>
                    <button
                        @click="submit"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 flex items-center gap-2"
                    >
                        <Eye class="w-4 h-4" />
                        Run Report
                    </button>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Report Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.name }"
                                required
                            />
                            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entity Type *</label>
                            <select
                                v-model="entityType"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.entity_type }"
                                required
                            >
                                <option value="invoice">Invoice</option>
                                <option value="quote">Quote</option>
                                <option value="jobcard">Job Card</option>
                            </select>
                            <div v-if="form.errors.entity_type" class="text-red-500 text-sm mt-1">
                                {{ form.errors.entity_type }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column Selection -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Select Columns</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <label
                            v-for="column in availableColumns[entityType]"
                            :key="column.value"
                            class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50"
                            :class="{ 'bg-blue-50 border-blue-500': selectedColumns.includes(column.value) }"
                        >
                            <input
                                type="checkbox"
                                :value="column.value"
                                :checked="selectedColumns.includes(column.value)"
                                @change="toggleColumn(column.value)"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-700">{{ column.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Filters (for templates) -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Filters</h2>
                    <p class="text-sm text-gray-600 mb-4">These filters will be saved to the template when you click "Save as Template".</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full rounded border px-3 py-2"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full rounded border px-3 py-2"
                            />
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                            <div class="relative">
                                <input
                                    v-model="customerSearchQuery"
                                    @input="handleCustomerSearch"
                                    @focus="customerSearchFocused = true"
                                    @blur="hideCustomerSearch"
                                    type="text"
                                    placeholder="Search customer..."
                                    class="w-full rounded border px-3 py-2"
                                />
                            </div>
                            <div
                                v-if="customerSearchFocused && filteredCustomers.length > 0"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            >
                                <div
                                    v-for="customer in filteredCustomers"
                                    :key="customer.id"
                                    @mousedown.prevent="toggleCustomer(customer)"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                    :class="{ 'bg-blue-50': selectedCustomers.some(c => c.id === customer.id) }"
                                >
                                    <div class="font-medium">{{ customer.name }}</div>
                                    <div class="text-xs text-gray-500" v-if="customer.account_code">{{ customer.account_code }}</div>
                                </div>
                            </div>
                            <!-- Selected Customers -->
                            <div v-if="selectedCustomers.length > 0" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="customer in selectedCustomers"
                                    :key="customer.id"
                                    class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm"
                                >
                                    {{ customer.name }}
                                    <button
                                        @click.prevent="removeCustomer(customer.id)"
                                        type="button"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        <X class="w-3 h-3" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                            <div class="relative">
                                <input
                                    v-model="productSearchQuery"
                                    @input="handleProductSearch"
                                    @focus="productSearchFocused = true"
                                    @blur="hideProductSearch"
                                    type="text"
                                    placeholder="Search product..."
                                    class="w-full rounded border px-3 py-2"
                                />
                            </div>
                            <div
                                v-if="productSearchFocused && filteredProducts.length > 0"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            >
                                <div
                                    v-for="product in filteredProducts"
                                    :key="product.id"
                                    @mousedown.prevent="toggleProduct(product)"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                    :class="{ 'bg-blue-50': selectedProducts.some(p => p.id === product.id) }"
                                >
                                    <div class="font-medium">{{ product.name }}</div>
                                    <div class="text-xs text-gray-500" v-if="product.sku">SKU: {{ product.sku }}</div>
                                </div>
                            </div>
                            <!-- Selected Products -->
                            <div v-if="selectedProducts.length > 0" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="product in selectedProducts"
                                    :key="product.id"
                                    class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded text-sm"
                                >
                                    {{ product.name }}
                                    <button
                                        @click.prevent="removeProduct(product.id)"
                                        type="button"
                                        class="text-green-600 hover:text-green-800"
                                    >
                                        <X class="w-3 h-3" />
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <div class="space-y-2">
                                <label
                                    v-for="status in getStatusOptions(entityType)"
                                    :key="status"
                                    class="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        :value="status"
                                        v-model="statusFilter"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-700">{{ status }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grouping & Sorting -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Grouping & Sorting</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                            <select v-model="groupBy" class="w-full rounded border px-3 py-2">
                                <option value="">None</option>
                                <option
                                    v-for="column in groupableColumns"
                                    :key="column.value"
                                    :value="column.value"
                                >
                                    {{ column.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select v-model="sortBy" class="w-full rounded border px-3 py-2">
                                <option
                                    v-for="column in sortableColumns"
                                    :key="column.value"
                                    :value="column.value"
                                >
                                    {{ column.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Direction</label>
                            <select v-model="sortDirection" class="w-full rounded border px-3 py-2">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="showTotals"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm font-medium text-gray-700">Show Totals</span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
