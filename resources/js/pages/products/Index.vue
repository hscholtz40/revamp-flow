<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';
import { Plus, Search, Filter, Package, Wrench, Edit, Trash2, Grid3x3, List } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
    description: string;
    type: 'product' | 'service';
    sku: string;
    price: number;
    cost: number;
    unit: string;
    stock_quantity: number;
    min_stock_level: number;
    track_stock: boolean;
    is_active: boolean;
    category: string;
    tags: string[];
    image_path: string;
    notes: string;
    created_at: string;
    updated_at: string;
}

interface Company {
    id: number;
    name: string;
}

interface Totals {
    total_products: number;
    total_products_type: number;
    total_services_type: number;
    total_active: number;
    total_inactive: number;
    total_stock_value: number;
    total_selling_value: number;
}

interface Props {
    products: {
        data: Product[];
        links: any[];
        meta: any;
    };
    filters: {
        type?: string;
        category?: string;
        search?: string;
        active_only?: boolean;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    categories: string[];
    currentCompany: Company;
    totals: Totals;
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const categoryFilter = ref(props.filters.category || '');
const activeOnly = ref(props.filters.active_only || false);
const sortBy = ref(props.filters.sort_by || 'name');
const sortDir = ref(props.filters.sort_dir || 'asc');

// Load view mode from localStorage or default to grid
const STORAGE_KEY = 'products_view_mode';
const getStoredViewMode = (): 'grid' | 'list' => {
    if (typeof window !== 'undefined') {
        const stored = localStorage.getItem(STORAGE_KEY);
        return (stored === 'grid' || stored === 'list') ? stored : 'grid';
    }
    return 'grid';
};

const viewMode = ref<'grid' | 'list'>(getStoredViewMode());

// Save view mode to localStorage when it changes
watch(viewMode, (newMode) => {
    if (typeof window !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, newMode);
    }
});

const filteredProducts = computed(() => props.products.data);

function applyFilters() {
    const params: Record<string, string | boolean> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (typeFilter.value && typeFilter.value.trim()) params.type = typeFilter.value.trim();
    if (categoryFilter.value && categoryFilter.value.trim()) params.category = categoryFilter.value.trim();
    if (activeOnly.value) params.active_only = activeOnly.value;
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    
    router.get(products.index().url, params, {
        preserveState: true,
        replace: true,
    });
}

function toggleSort(field: string) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
}

function sortIndicator(field: string) {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

function clearFilters() {
    search.value = '';
    typeFilter.value = '';
    categoryFilter.value = '';
    activeOnly.value = false;
    applyFilters();
}

function deleteProduct(product: Product) {
    if (confirm(`Are you sure you want to delete "${product.name}"?`)) {
        router.delete(products.destroy(product.id).url);
    }
}

function getTypeIcon(type: string) {
    return type === 'product' ? Package : Wrench;
}

function getTypeColor(type: string) {
    return type === 'product' ? 'text-blue-600' : 'text-green-600';
}

function getStockStatus(product: Product) {
    if (!product.track_stock) return { text: 'N/A', color: 'text-gray-500' };
    if (product.stock_quantity <= product.min_stock_level) {
        return { text: 'Low Stock', color: 'text-red-600' };
    }
    return { text: 'In Stock', color: 'text-green-600' };
}
</script>

<template>
    <Head title="Products & Services" />

    <AppLayout :breadcrumbs="[
        { title: 'Products & Services', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing products for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Products & Services</h1>
                    <p class="text-gray-600">Manage your product catalog and service offerings</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- View Toggle -->
                    <div class="flex items-center gap-1 rounded-md border border-gray-300 bg-white p-1">
                        <button
                            @click="viewMode = 'grid'"
                            :class="[
                                'flex items-center gap-1 rounded px-3 py-1.5 text-sm transition-colors',
                                viewMode === 'grid'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <Grid3x3 class="h-4 w-4" />
                            Grid
                        </button>
                        <button
                            @click="viewMode = 'list'"
                            :class="[
                                'flex items-center gap-1 rounded px-3 py-1.5 text-sm transition-colors',
                                viewMode === 'list'
                                    ? 'bg-blue-600 text-white'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <List class="h-4 w-4" />
                            List
                        </button>
                    </div>
                    <Link
                        v-if="$page.props.auth?.abilities?.products?.create"
                        :href="products.create().url"
                        class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Product/Service
                    </Link>
                </div>
            </div>

            <!-- Totals -->
            <div class="mb-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-gray-600">Total Products</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">{{ props.totals.total_products }}</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ props.totals.total_products_type }} products, {{ props.totals.total_services_type }} services
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-gray-600">Active Products</div>
                    <div class="mt-1 text-2xl font-semibold text-green-600">{{ props.totals.total_active }}</div>
                    <div class="mt-1 text-xs text-gray-500">
                        {{ props.totals.total_inactive }} inactive
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-gray-600">Stock Value</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">R{{ Number(props.totals.total_stock_value).toFixed(2) }}</div>
                    <div class="mt-1 text-xs text-gray-500">At cost price</div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="text-sm text-gray-600">Selling Value</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-900">R{{ Number(props.totals.total_selling_value).toFixed(2) }}</div>
                    <div class="mt-1 text-xs text-gray-500">At selling price</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border bg-white p-4">
                <div class="mb-4 flex items-center gap-2">
                    <Filter class="h-4 w-4 text-gray-500" />
                    <span class="font-medium text-gray-700">Filters</span>
                </div>
                
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search products..."
                            class="w-full rounded-md border border-gray-300 pl-10 pr-4 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <!-- Type Filter -->
                    <select
                        v-model="typeFilter"
                        class="rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        @change="applyFilters"
                    >
                        <option value="">All Types</option>
                        <option value="product">Products</option>
                        <option value="service">Services</option>
                    </select>

                    <!-- Category Filter -->
                    <select
                        v-model="categoryFilter"
                        class="rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        @change="applyFilters"
                    >
                        <option value="">All Categories</option>
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ category }}
                        </option>
                    </select>

                    <!-- Active Only -->
                    <label class="flex items-center gap-2">
                        <input
                            v-model="activeOnly"
                            type="checkbox"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            @change="applyFilters"
                        />
                        <span class="text-sm text-gray-700">Active Only</span>
                    </label>

                    <!-- Clear Filters -->
                    <button
                        @click="clearFilters"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>

            <!-- Products Grid -->
            <div v-if="filteredProducts.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Package class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No products found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first product or service.</p>
                <div class="mt-6">
                    <Link
                        v-if="$page.props.auth?.abilities?.products?.create"
                        :href="products.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Product/Service
                    </Link>
                </div>
            </div>

            <!-- Grid View -->
            <div v-else-if="viewMode === 'grid'" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="product in filteredProducts"
                    :key="product.id"
                    :class="[
                        'rounded-lg border bg-white p-6 shadow-sm transition-shadow hover:shadow-md',
                        $page.props.auth?.abilities?.products?.view ? 'cursor-pointer focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500' : '',
                    ]"
                    :tabindex="$page.props.auth?.abilities?.products?.view ? 0 : undefined"
                    :role="$page.props.auth?.abilities?.products?.view ? 'link' : undefined"
                    @click="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                    @keydown.enter.prevent="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                    @keydown.space.prevent="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                >
                    <!-- Product Header -->
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <component
                                :is="getTypeIcon(product.type)"
                                :class="['h-6 w-6', getTypeColor(product.type)]"
                            />
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ product.name }}</h3>
                                <p class="text-sm text-gray-500">{{ product.type.charAt(0).toUpperCase() + product.type.slice(1) }}</p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-2 py-1 text-xs font-medium',
                                product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ product.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Product Details -->
                    <div class="mb-4 space-y-2">
                        <div v-if="product.sku" class="text-sm text-gray-600">
                            <span class="font-medium">SKU:</span> {{ product.sku }}
                        </div>
                        <div v-if="product.category" class="text-sm text-gray-600">
                            <span class="font-medium">Category:</span> {{ product.category }}
                        </div>
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Price:</span> R{{ Number(product.price).toFixed(2) }} / {{ product.unit }}
                        </div>
                        <div v-if="product.track_stock" class="text-sm text-gray-600">
                            <span class="font-medium">Stock:</span>
                            <span :class="getStockStatus(product).color">
                                {{ product.stock_quantity }} ({{ getStockStatus(product).text }})
                            </span>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="product.description" class="mb-4 text-sm text-gray-600 line-clamp-2">
                        {{ product.description }}
                    </p>

                    <!-- Tags -->
                    <div v-if="product.tags && product.tags.length > 0" class="mb-4">
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="tag in product.tags.slice(0, 3)"
                                :key="tag"
                                class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600"
                            >
                                {{ tag }}
                            </span>
                            <span
                                v-if="product.tags.length > 3"
                                class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600"
                            >
                                +{{ product.tags.length - 3 }} more
                            </span>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2" @click.stop>
                        <Link
                            v-if="$page.props.auth?.abilities?.products?.edit"
                            :href="products.edit(product.id).url"
                            class="inline-flex items-center justify-center rounded border px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 md:px-3 md:py-1"
                        >
                            <ListTableActionLabel label="Edit">
                                <Edit class="h-4 w-4" />
                            </ListTableActionLabel>
                        </Link>
                        <button
                            v-if="$page.props.auth?.abilities?.products?.delete"
                            @click="deleteProduct(product)"
                            class="inline-flex items-center justify-center rounded border border-red-300 px-2 py-1.5 text-sm text-red-700 hover:bg-red-50 md:px-3 md:py-1"
                        >
                            <ListTableActionLabel label="Delete">
                                <Trash2 class="h-4 w-4" />
                            </ListTableActionLabel>
                        </button>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div v-else class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700">Name {{ sortIndicator('name') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('type')" class="inline-flex items-center gap-1 hover:text-gray-700">Type {{ sortIndicator('type') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('sku')" class="inline-flex items-center gap-1 hover:text-gray-700">SKU {{ sortIndicator('sku') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('category')" class="inline-flex items-center gap-1 hover:text-gray-700">Category {{ sortIndicator('category') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('price')" class="inline-flex items-center gap-1 hover:text-gray-700">Price {{ sortIndicator('price') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('stock_quantity')" class="inline-flex items-center gap-1 hover:text-gray-700">Stock {{ sortIndicator('stock_quantity') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('is_active')" class="inline-flex items-center gap-1 hover:text-gray-700">Status {{ sortIndicator('is_active') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="product in filteredProducts"
                                :key="product.id"
                                :class="[
                                    $page.props.auth?.abilities?.products?.view
                                        ? 'cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500'
                                        : '',
                                ]"
                                :tabindex="$page.props.auth?.abilities?.products?.view ? 0 : undefined"
                                :role="$page.props.auth?.abilities?.products?.view ? 'link' : undefined"
                                @click="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                                @keydown.enter.prevent="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                                @keydown.space.prevent="$page.props.auth?.abilities?.products?.view && router.visit(products.show(product.id).url)"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-start gap-3">
                                        <component
                                            :is="getTypeIcon(product.type)"
                                            :class="['h-5 w-5 mt-0.5 flex-shrink-0', getTypeColor(product.type)]"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div class="text-sm font-medium text-gray-900">{{ product.name }}</div>
                                            <div v-if="product.description" class="text-sm text-gray-500 line-clamp-2 max-w-md">
                                                {{ product.description }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm text-gray-900">
                                        {{ product.type.charAt(0).toUpperCase() + product.type.slice(1) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ product.sku || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ product.category || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        R{{ Number(product.price).toFixed(2) }}
                                    </div>
                                    <div class="text-xs text-gray-500">/ {{ product.unit }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="product.track_stock" class="text-sm">
                                        <span :class="getStockStatus(product).color">
                                            {{ product.stock_quantity }}
                                        </span>
                                        <div class="text-xs text-gray-500">{{ getStockStatus(product).text }}</div>
                                    </div>
                                    <div v-else class="text-sm text-gray-500">N/A</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                                            product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        ]"
                                    >
                                        {{ product.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="$page.props.auth?.abilities?.products?.edit"
                                            :href="products.edit(product.id).url"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="$page.props.auth?.abilities?.products?.delete"
                                            @click="deleteProduct(product)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-red-300 text-xs font-medium rounded-md text-red-700 hover:bg-red-50"
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
            </div>

            <!-- Pagination -->
            <div v-if="props.products.links" class="mt-6 bg-white rounded-lg border p-4">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="text-sm text-gray-700">
                        Showing {{ props.products.meta?.from || 0 }} to {{ props.products.meta?.to || 0 }} of {{ props.products.meta?.total || 0 }} results
                    </div>
                    <div v-if="props.products.links.length > 0" class="flex space-x-1">
                        <Link
                            v-for="link in props.products.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            v-html="link.label"
                            :class="[
                                'px-3 py-2 text-sm border rounded-md',
                                link.active
                                    ? 'bg-blue-50 border-blue-500 text-blue-600'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                                !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                            ]"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
