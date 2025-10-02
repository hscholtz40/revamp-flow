<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Search, Filter, Package, Wrench, Eye, Edit, Trash2 } from 'lucide-vue-next';
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
    };
    categories: string[];
    currentCompany: Company;
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const typeFilter = ref(props.filters.type || '');
const categoryFilter = ref(props.filters.category || '');
const activeOnly = ref(props.filters.active_only || false);

const filteredProducts = computed(() => props.products.data);

function applyFilters() {
    const params: Record<string, string | boolean> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (typeFilter.value && typeFilter.value.trim()) params.type = typeFilter.value.trim();
    if (categoryFilter.value && categoryFilter.value.trim()) params.category = categoryFilter.value.trim();
    if (activeOnly.value) params.active_only = activeOnly.value;
    
    router.get(products.index().url, params, {
        preserveState: true,
        replace: true,
    });
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
                <Link
                    v-if="$page.props.auth?.abilities?.products?.create"
                    :href="products.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Product/Service
                </Link>
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

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="product in filteredProducts"
                    :key="product.id"
                    class="rounded-lg border bg-white p-6 shadow-sm hover:shadow-md transition-shadow"
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
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="$page.props.auth?.abilities?.products?.view"
                            :href="products.show(product.id).url"
                            class="flex items-center gap-1 rounded border px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <Eye class="h-3 w-3" />
                            View
                        </Link>
                        <Link
                            v-if="$page.props.auth?.abilities?.products?.edit"
                            :href="products.edit(product.id).url"
                            class="flex items-center gap-1 rounded border px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <Edit class="h-3 w-3" />
                            Edit
                        </Link>
                        <button
                            v-if="$page.props.auth?.abilities?.products?.delete"
                            @click="deleteProduct(product)"
                            class="flex items-center gap-1 rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="products.links && products.links.length > 3" class="mt-6">
                <nav class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Showing {{ products.meta.from }} to {{ products.meta.to }} of {{ products.meta.total }} results
                    </div>
                    <div class="flex gap-1">
                        <Link
                            v-for="link in products.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-3 py-2 text-sm rounded-md',
                                link.active
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50',
                                !link.url ? 'opacity-50 cursor-not-allowed' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>
