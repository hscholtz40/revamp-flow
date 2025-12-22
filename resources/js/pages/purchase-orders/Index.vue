<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Search, FileText, Building2, Eye } from 'lucide-vue-next';
import purchaseOrders from '@/routes/purchase-orders';

interface Supplier {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
}

interface PurchaseOrderItem {
    id: number;
    product: Product;
    quantity: number;
    unit_cost: number;
    total: number;
    quantity_received: number;
}

interface PurchaseOrder {
    id: number;
    po_number: string;
    supplier: Supplier;
    order_date: string;
    expected_delivery_date: string | null;
    received_date: string | null;
    status: 'draft' | 'sent' | 'received' | 'cancelled';
    total: number;
    items: PurchaseOrderItem[];
    created_at: string;
}

interface Props {
    purchaseOrders?: {
        data: PurchaseOrder[];
        links: any[];
        meta: any;
    };
    filters?: {
        supplier_id?: number;
        status?: string;
        search?: string;
    };
    suppliers?: Supplier[];
}

const props = withDefaults(defineProps<Props>(), {
    purchaseOrders: () => ({ data: [], links: [], meta: {} }),
    filters: () => ({}),
    suppliers: () => [],
});

const search = ref(props.filters?.search || '');
const supplierFilter = ref(props.filters?.supplier_id?.toString() || '');
const statusFilter = ref(props.filters?.status || '');

// Alias props.purchaseOrders to avoid conflict with imported purchaseOrders route
const purchaseOrdersData = computed(() => props.purchaseOrders);

// Computed to check if purchase orders exist
const hasPurchaseOrders = computed(() => {
    return props.purchaseOrders?.data && props.purchaseOrders.data.length > 0;
});

function applyFilters() {
    const params: Record<string, string | number> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (supplierFilter.value) params.supplier_id = parseInt(supplierFilter.value);
    if (statusFilter.value) params.status = statusFilter.value;
    
    router.get(purchaseOrders.index().url, params, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    search.value = '';
    supplierFilter.value = '';
    statusFilter.value = '';
    applyFilters();
}

function getStatusColor(status: string) {
    return {
        'draft': 'bg-gray-100 text-gray-800',
        'sent': 'bg-blue-100 text-blue-800',
        'received': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
    }[status] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head title="Purchase Orders" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Purchase Orders</h1>
                    <p class="text-gray-600">Manage purchase orders and track deliveries</p>
                </div>
                <Link
                    :href="purchaseOrders.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Create Purchase Order
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Search PO Number</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search PO numbers..."
                                class="w-full rounded border border-gray-300 pl-10 pr-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div class="min-w-[200px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Supplier</label>
                        <select
                            v-model="supplierFilter"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Suppliers</option>
                            <option v-for="supplier in (suppliers || [])" :key="supplier.id" :value="supplier.id.toString()">
                                {{ supplier.name }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="statusFilter"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="received">Received</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="applyFilters"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Filter
                        </button>
                        <button
                            @click="clearFilters"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Purchase Orders Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expected Delivery</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template v-if="hasPurchaseOrders">
                            <tr v-for="po in purchaseOrdersData.data" :key="po.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <FileText class="h-4 w-4 text-gray-400" />
                                    <span class="font-medium text-gray-900">{{ po.po_number }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Building2 class="h-4 w-4 text-gray-400" />
                                    <span class="text-gray-900">{{ po.supplier.name }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ new Date(po.order_date).toLocaleDateString() }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ po.expected_delivery_date ? new Date(po.expected_delivery_date).toLocaleDateString() : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900">
                                R{{ Number(po.total).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span :class="getStatusColor(po.status)" class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize">
                                    {{ po.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="purchaseOrders.show(po.id).url"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                        </template>
                        <tr v-else>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">
                                No purchase orders found. <Link :href="purchaseOrders.create().url" class="text-blue-600 hover:text-blue-900">Create your first purchase order</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="purchaseOrdersData?.links && purchaseOrdersData.links.length > 3" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ purchaseOrdersData.meta?.from }} to {{ purchaseOrdersData.meta?.to }} of {{ purchaseOrdersData.meta?.total }} purchase orders
                </div>
                <div class="flex gap-2">
                    <Link
                        v-for="link in purchaseOrdersData.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                            !link.url ? 'cursor-not-allowed opacity-50' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

