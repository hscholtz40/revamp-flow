<template>
    <Head title="Purchase Orders" />

    <AppLayout :breadcrumbs="[{ title: 'Purchase Orders', href: purchaseOrders.index().url }]">
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing purchase orders for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Purchase Orders</h1>
                <Link :href="purchaseOrders.create().url" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    New Purchase Order
                </Link>
            </div>

            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search purchase orders..."
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <select v-model="supplierFilter" class="w-full rounded border px-3 py-2">
                            <option value="">All Suppliers</option>
                            <option v-for="supplier in (props.suppliers || [])" :key="supplier.id" :value="supplier.id.toString()">
                                {{ supplier.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="statusFilter" class="w-full rounded border px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
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
            </div>

            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('po_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Number <span>{{ sortIndicator('po_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('supplier_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Supplier <span>{{ sortIndicator('supplier_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('order_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Order Date <span>{{ sortIndicator('order_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('expected_delivery_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Expected Delivery <span>{{ sortIndicator('expected_delivery_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Status <span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Total <span>{{ sortIndicator('total') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="po in (props.purchaseOrders?.data || [])" :key="po.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ po.po_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ po.supplier?.name || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatDate(po.order_date) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ po.expected_delivery_date ? formatDate(po.expected_delivery_date) : '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusBadgeClass(po.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ po.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ formatCurrency(po.total) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="purchaseOrders.show(po.id).url"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200"
                                        >
                                            View
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.purchaseOrders?.links && props.purchaseOrders.links.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="props.purchaseOrders?.prev_page_url"
                                :href="props.purchaseOrders.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="props.purchaseOrders?.next_page_url"
                                :href="props.purchaseOrders.next_page_url"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ props.purchaseOrders.from ?? 0 }}</span>
                                    to
                                    <span class="font-medium">{{ props.purchaseOrders.to ?? 0 }}</span>
                                    of
                                    <span class="font-medium">{{ props.purchaseOrders.total ?? 0 }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <template v-for="(link, index) in (props.purchaseOrders?.links || [])" :key="index">
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
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import purchaseOrders from '@/routes/purchase-orders';

interface Supplier {
    id: number;
    name: string;
}

interface PurchaseOrder {
    id: number;
    po_number: string;
    supplier?: Supplier;
    order_date: string;
    expected_delivery_date: string | null;
    status: 'draft' | 'sent' | 'received' | 'cancelled';
    total: number;
}

interface Props {
    purchaseOrders: {
        data: PurchaseOrder[];
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
    };
    filters: {
        supplier_id?: number;
        status?: string;
        search?: string;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    suppliers: Supplier[];
    currentCompany: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters?.search || '');
const supplierFilter = ref(props.filters?.supplier_id?.toString() || '');
const statusFilter = ref(props.filters?.status || '');
const sortBy = ref(props.filters?.sort_by || 'po_number');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir || 'desc');

const applyFilters = () => {
    const params: Record<string, string | number> = {};

    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (supplierFilter.value) params.supplier_id = parseInt(supplierFilter.value, 10);
    if (statusFilter.value) params.status = statusFilter.value;
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;

    router.get(purchaseOrders.index().url, params, {
        preserveState: true,
        replace: true,
    });
};

watch([search, supplierFilter, statusFilter], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    supplierFilter.value = '';
    statusFilter.value = '';
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
};

const sortIndicator = (field: string): string => {
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
    const classes: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        received: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>

