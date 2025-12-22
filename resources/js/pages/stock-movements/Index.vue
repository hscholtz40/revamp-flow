<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Search, Filter, ArrowUp, ArrowDown, Settings, Eye } from 'lucide-vue-next';
import stockMovements from '@/routes/stock-movements';

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface User {
    id: number;
    name: string;
}

interface StockMovement {
    id: number;
    product: Product;
    type: 'in' | 'out' | 'adjustment' | 'transfer';
    quantity: number;
    unit_cost: number | null;
    stock_before: number;
    stock_after: number;
    reference: string | null;
    notes: string | null;
    user: User | null;
    created_at: string;
}

interface Props {
    movements?: {
        data: StockMovement[];
        links?: any[];
        from?: number;
        to?: number;
        total?: number;
        meta?: {
            from?: number;
            to?: number;
            total?: number;
        };
    };
    filters?: {
        product_id?: number;
        type?: string;
        date_from?: string;
        date_to?: string;
    };
    products?: Product[];
}

const props = withDefaults(defineProps<Props>(), {
    movements: () => ({ 
        data: [], 
        links: [], 
        from: 0,
        to: 0,
        total: 0,
        meta: { 
            from: 0, 
            to: 0, 
            total: 0 
        } 
    }),
    filters: () => ({}),
    products: () => [],
});

const searchProduct = ref(props.filters?.product_id?.toString() || '');
const typeFilter = ref(props.filters?.type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

function applyFilters() {
    const params: Record<string, string | number> = {};
    
    if (searchProduct.value) params.product_id = parseInt(searchProduct.value);
    if (typeFilter.value) params.type = typeFilter.value;
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;
    
    router.get(stockMovements.index().url, params, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    searchProduct.value = '';
    typeFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilters();
}

function getTypeIcon(type: string) {
    return type === 'in' ? ArrowUp : type === 'out' ? ArrowDown : Settings;
}

function getTypeColor(type: string) {
    return {
        'in': 'text-green-600 bg-green-50',
        'out': 'text-red-600 bg-red-50',
        'adjustment': 'text-blue-600 bg-blue-50',
        'transfer': 'text-yellow-600 bg-yellow-50',
    }[type] || 'text-gray-600 bg-gray-50';
}
</script>

<template>
    <Head title="Stock Movements" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Stock Movements</h1>
                    <p class="text-gray-600">Track all stock movements and inventory changes</p>
                </div>
                <Link
                    :href="stockMovements.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Record Movement
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Product</label>
                        <select
                            v-model="searchProduct"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Products</option>
                            <option v-for="product in (products || [])" :key="product.id" :value="product.id.toString()">
                                {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Type</label>
                        <select
                            v-model="typeFilter"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">All Types</option>
                            <option value="in">In</option>
                            <option value="out">Out</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Date From</label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Date To</label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        />
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

            <!-- Movements Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock Before</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock After</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="movement in (movements?.data || [])" :key="movement.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                {{ new Date(movement.created_at).toLocaleDateString() }}
                                <div class="text-xs text-gray-500">{{ new Date(movement.created_at).toLocaleTimeString() }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="font-medium text-gray-900">{{ movement.product.name }}</div>
                                <div v-if="movement.product.sku" class="text-xs text-gray-500">{{ movement.product.sku }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span :class="getTypeColor(movement.type)" class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold capitalize">
                                    <component :is="getTypeIcon(movement.type)" class="h-3 w-3" />
                                    {{ movement.type }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" :class="movement.type === 'in' ? 'text-green-600' : movement.type === 'out' ? 'text-red-600' : 'text-gray-900'">
                                {{ movement.type === 'in' ? '+' : movement.type === 'out' ? '-' : '' }}{{ Math.abs(movement.quantity) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500">
                                {{ movement.stock_before }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium text-gray-900">
                                {{ movement.stock_after }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ movement.reference || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ movement.user?.name || 'System' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <Link
                                    :href="stockMovements.show(movement.id).url"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!movements?.data || movements.data.length === 0">
                            <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500">
                                No stock movements found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="movements?.links && Array.isArray(movements.links) && movements.links.length > 3" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ movements?.from ?? movements?.meta?.from ?? 0 }} to {{ movements?.to ?? movements?.meta?.to ?? 0 }} of {{ movements?.total ?? movements?.meta?.total ?? 0 }} movements
                </div>
                <div class="flex gap-2">
                    <Link
                        v-for="link in movements.links"
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

