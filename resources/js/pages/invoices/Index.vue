<template>
    <Head title="Invoices" />

    <AppLayout :breadcrumbs="[{ title: 'Invoices', href: invoices.index().url }]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing invoices for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Invoices</h1>
                <Link :href="invoices.create().url" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    New Invoice
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search invoices..."
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="status" class="w-full rounded border px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <select v-model="customerId" class="w-full rounded border px-3 py-2">
                            <option value="">All Customers</option>
                            <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="showPaid"
                            type="checkbox"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm font-medium text-gray-700">Show paid invoices</span>
                    </label>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('invoice_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Invoice
                                        <span>{{ sortIndicator('invoice_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Customer
                                        <span>{{ sortIndicator('customer_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('salesperson_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Salesperson
                                        <span>{{ sortIndicator('salesperson_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('invoice_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Date
                                        <span>{{ sortIndicator('invoice_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('due_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Due Date
                                        <span>{{ sortIndicator('due_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Status
                                        <span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Total
                                        <span>{{ sortIndicator('total') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="invoice in (props.invoices?.data || [])" :key="invoice.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ invoice.invoice_number }}</div>
                                        <div class="text-sm text-gray-500">{{ invoice.title }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ invoice.customer?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ invoice.salesperson?.name || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatDate(invoice.invoice_date) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatDate(invoice.due_date) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusBadgeClass(invoice.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ invoice.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ formatCurrency(invoice.total) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="invoices.show(invoice.id).url"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            v-if="canEditInvoice(invoice)"
                                            :href="invoices.edit(invoice.id).url"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            Edit
                                        </Link>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-gray-500 bg-gray-100 cursor-not-allowed"
                                            title="Cannot edit paid invoices without permission"
                                        >
                                            Edit
                                        </span>
                                        <button
                                            v-if="canDeleteInvoice(invoice)"
                                            @click="deleteInvoice(invoice.id)"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            Delete
                                        </button>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-gray-500 bg-gray-100 cursor-not-allowed"
                                            title="Cannot delete paid invoices without permission"
                                        >
                                            Delete
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.invoices?.links && props.invoices.links.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="props.invoices?.prev_page_url"
                                :href="props.invoices.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="props.invoices?.next_page_url"
                                :href="props.invoices.next_page_url"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ props.invoices.from ?? 0 }}</span>
                                    to
                                    <span class="font-medium">{{ props.invoices.to ?? 0 }}</span>
                                    of
                                    <span class="font-medium">{{ props.invoices.total ?? 0 }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <template v-for="(link, index) in (props.invoices?.links || [])" :key="index">
                                        <Link
                                            v-if="link.url"
                                            :href="link.url"
                                            v-html="link.label"
                                            :class="[
                                                'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                                link.active
                                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                            ]"
                                        />
                                        <span
                                            v-else
                                            v-html="link.label"
                                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed"
                                        />
                                    </template>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import invoices from '@/routes/invoices';

interface Invoice {
    id: number;
    invoice_number: string;
    title: string;
    customer?: {
        name: string;
    };
    salesperson?: {
        name: string;
    };
    invoice_date: string;
    due_date: string;
    status: string;
    total: number;
}

interface Customer {
    id: number;
    name: string;
}

interface Company {
    id: number;
    name: string;
}

interface PaginatedInvoices {
    data: Invoice[];
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    from?: number;
    to?: number;
    total?: number;
}

interface Props {
    invoices: PaginatedInvoices;
    customers: Customer[];
    currentCompany: Company;
    filters: {
        status?: string;
        customer_id?: string;
        search?: string;
        show_paid?: boolean;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    canEditCompleted: boolean;
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const customerId = ref(props.filters.customer_id || '');
const showPaid = ref(props.filters.show_paid || false);
const sortBy = ref(props.filters.sort_by || 'invoice_number');
const sortDir = ref<'asc' | 'desc'>(props.filters.sort_dir || 'asc');

// Helper functions for edit/delete permissions
const canEditInvoice = (invoice: Invoice) => {
    if (invoice.status !== 'paid') {
        return true;
    }
    return props.canEditCompleted;
};

const canDeleteInvoice = (invoice: Invoice) => {
    if (invoice.status !== 'paid') {
        return true;
    }
    return props.canEditCompleted;
};

// Watch for filter changes and update URL
watch([search, status, customerId, showPaid], () => {
    router.get(invoices.index().url, {
        search: search.value,
        status: status.value,
        customer_id: customerId.value,
        show_paid: showPaid.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
});

const clearFilters = () => {
    search.value = '';
    status.value = '';
    customerId.value = '';
    showPaid.value = false;
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }

    router.get(invoices.index().url, {
        search: search.value,
        status: status.value,
        customer_id: customerId.value,
        show_paid: showPaid.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: 'ZAR',
    }).format(amount);
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-800',
    };
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800';
};

const deleteInvoice = (id: number) => {
    if (confirm('Are you sure you want to delete this invoice?')) {
        router.delete(invoices.destroy(id).url);
    }
};
</script>
