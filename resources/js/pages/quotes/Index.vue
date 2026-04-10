<template>
    <Head title="Quotes" />

    <AppLayout :breadcrumbs="[{ title: 'Quotes', href: quotes.index().url }]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing quotes for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Quotes</h1>
                <Link
                    v-if="canQuotesCreate"
                    :href="quotes.create().url"
                    class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    New Quote
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search quotes..."
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="status" class="w-full rounded border px-3 py-2">
                            <option value="">All Statuses</option>
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
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
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="showClosed"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-700">Show closed</span>
                        </label>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quotes Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-list-view-table="true" class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('quote_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Quote Number
                                        <span>{{ sortIndicator('quote_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('title')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Title
                                        <span>{{ sortIndicator('title') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Customer
                                        <span>{{ sortIndicator('customer_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Status
                                        <span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('expiry_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Expiry Date
                                        <span>{{ sortIndicator('expiry_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Total
                                        <span>{{ sortIndicator('total') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Order Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Subtotal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Discount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Tax</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="quote in props.quotes.data"
                                :key="quote.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(quotes.show(quote.id).url)"
                                @keydown.enter.prevent="router.visit(quotes.show(quote.id).url)"
                                @keydown.space.prevent="router.visit(quotes.show(quote.id).url)"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ quote.quote_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ quote.title }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ quote.customer?.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusBadgeClass(quote.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ formatQuoteStatus(quote.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ quote.expiry_date ? formatDate(quote.expiry_date) : '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ quote.formatted_total }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ quote.order_number || '-' }}</div>
                                </td>
                                <td class="px-6 py-4" style="display: none;">
                                    <div class="text-sm text-gray-900 truncate max-w-xs">{{ quote.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ quote.email || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ quote.phone || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">R{{ Number(quote.subtotal || 0).toFixed(2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">R{{ Number(quote.discount_amount || 0).toFixed(2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">R{{ Number(quote.tax_amount || 0).toFixed(2) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ quote.created_at ? formatDate(quote.created_at) : '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ quote.updated_at ? formatDate(quote.updated_at) : '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="canEditQuote(quote)"
                                            :href="quotes.edit(quote.id).url"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="canDeleteQuote(quote)"
                                            @click="deleteQuote(quote)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.quotes.links" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="props.quotes.prev_page_url"
                                :href="props.quotes.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="props.quotes.next_page_url"
                                :href="props.quotes.next_page_url"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ props.quotes.from }}</span>
                                    to
                                    <span class="font-medium">{{ props.quotes.to }}</span>
                                    of
                                    <span class="font-medium">{{ props.quotes.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <Link
                                        v-for="link in props.quotes.links"
                                        :key="link.label"
                                        :href="link.url || '#'"
                                        v-html="link.label"
                                        :class="[
                                            'px-3 py-1 text-sm rounded-md',
                                            link.url
                                                ? 'bg-white text-gray-700 hover:bg-gray-50 border border-gray-300'
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                        ]"
                                    />
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
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import quotes from '@/routes/quotes';
import { computed, ref, watch } from 'vue';

interface Quote {
    id: number;
    quote_number: string;
    title: string;
    order_number?: string | null;
    email?: string | null;
    phone?: string | null;
    subtotal?: number;
    discount_amount?: number;
    tax_amount?: number;
    created_at?: string;
    updated_at?: string;
    customer?: {
        name: string;
    };
    status: string;
    expiry_date?: string;
    formatted_total: string;
}

interface Customer {
    id: number;
    name: string;
}

interface Company {
    id: number;
    name: string;
}

const props = defineProps<{
    quotes: {
        data: Quote[];
        links: any[];
        prev_page_url: string | null;
        next_page_url: string | null;
        from: number;
        to: number;
        total: number;
    };
    customers: Customer[];
    filters: {
        status: string;
        customer_id: string;
        search: string;
        show_closed: boolean;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    currentCompany: Company;
    canEditCompleted: boolean;
    statusOptions?: Array<{ value: string; label: string }>;
}>();

const statusOptions = computed(() => props.statusOptions ?? []);

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const customerId = ref(props.filters.customer_id);
const showClosed = ref(props.filters.show_closed ?? false);
const sortBy = ref(props.filters.sort_by || 'quote_number');
const sortDir = ref<'asc' | 'desc'>(props.filters.sort_dir || 'desc');

const canQuotesCreate = useAuthAbility('quotes', 'create');
const canQuotesEdit = useAuthAbility('quotes', 'edit');
const canQuotesDelete = useAuthAbility('quotes', 'delete');

// Helper functions for edit/delete permissions
const canEditQuote = (quote: Quote) => {
    if (!canQuotesEdit.value) {
        return false;
    }
    if (quote.status !== 'accepted') {
        return true;
    }
    return props.canEditCompleted;
};

const canDeleteQuote = (quote: Quote) => {
    if (!canQuotesDelete.value) {
        return false;
    }
    if (quote.status !== 'accepted') {
        return true;
    }
    return props.canEditCompleted;
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
    customerId.value = '';
    showClosed.value = false;
};

const deleteQuote = (quote: Quote) => {
    if (confirm(`Are you sure you want to delete quote ${quote.quote_number}?`)) {
        router.delete(quotes.destroy(quote.id).url);
    }
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        accepted: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        expired: 'bg-yellow-100 text-yellow-800',
    };
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800';
};

const formatQuoteStatus = (code: string) => {
    if (!code) return '';
    const opt = statusOptions.value.find((o) => o.value === code);
    if (opt) return opt.label;
    return code.replace(/_/g, ' ').toUpperCase();
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

// Watch for filter changes and update URL
watch([search, status, customerId, showClosed], () => {
    router.get(quotes.index().url, {
        search: search.value,
        status: status.value,
        customer_id: customerId.value,
        show_closed: showClosed.value ? '1' : '',
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
}, { debounce: 300 });

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }

    router.get(quotes.index().url, {
        search: search.value,
        status: status.value,
        customer_id: customerId.value,
        show_closed: showClosed.value ? '1' : '',
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
</script>
