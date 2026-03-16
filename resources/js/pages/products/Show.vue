<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Edit, Trash2, Package, Wrench, DollarSign, Hash, Tag, Calendar, FileText } from 'lucide-vue-next';
import products from '@/routes/products';
import invoices from '@/routes/invoices';

interface Product {
    id: number;
    name: string;
    description: string;
    type: 'product' | 'service';
    sku: string;
    barcode?: string;
    price: number;
    cost: number;
    unit: string;
    stock_quantity: number;
    min_stock_level: number;
    track_stock: boolean;
    track_batches?: boolean;
    track_serial_numbers?: boolean;
    valuation_method?: 'fifo' | 'lifo' | 'average_cost';
    is_active: boolean;
    category: string;
    tags: string[];
    image_path: string;
    notes: string;
    created_at: string;
    updated_at: string;
}

interface InvoiceLineItemWithInvoice {
    id: number;
    invoice_id: number;
    product_id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
    invoice?: {
        id: number;
        invoice_number: string;
        customer_id: number;
        customer?: { id: number; name: string };
    };
}

interface Props {
    product: Product;
    recentInvoiceLineItems?: InvoiceLineItemWithInvoice[];
}

const props = defineProps<Props>();

function getTypeIcon(type: string) {
    return type === 'product' ? Package : Wrench;
}

function getTypeColor(type: string) {
    return type === 'product' ? 'text-blue-600' : 'text-green-600';
}

function getStockStatus(product: Product) {
    if (!product.track_stock) return { text: 'Not Tracked', color: 'text-gray-500', bg: 'bg-gray-100' };
    if (product.stock_quantity <= product.min_stock_level) {
        return { text: 'Low Stock', color: 'text-red-600', bg: 'bg-red-100' };
    }
    return { text: 'In Stock', color: 'text-green-600', bg: 'bg-green-100' };
}

const profitMargin = computed(() => {
    const cost = Number(props.product.cost);
    const price = Number(props.product.price);
    if (!cost || cost === 0) return null;
    return ((price - cost) / cost) * 100;
});

function deleteProduct() {
    if (confirm(`Are you sure you want to delete "${props.product.name}"?`)) {
        // This would need to be implemented with a form or router.delete
    }
}
</script>

<template>
    <Head :title="props.product.name" />

    <AppLayout :breadcrumbs="[
        { title: 'Products & Services', href: products.index().url },
        { title: props.product.name, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.product.name }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Product & Service Details</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="products.index().url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Back
                        </Link>
                        <Link
                            v-if="$page.props.auth?.abilities?.products?.edit"
                            :href="products.edit(props.product.id).url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Edit
                        </Link>
                        <button
                            v-if="$page.props.auth?.abilities?.products?.delete"
                            @click="deleteProduct"
                            class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <!-- Product Information Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Product Information</h2>
                    <p class="text-sm text-gray-600">Basic details and specifications</p>
                </div>
                <div class="p-6">
                    <div class="flex items-start gap-6">
                        <!-- Product Icon -->
                        <div class="flex-shrink-0">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-gray-100">
                                <component
                                    :is="getTypeIcon(props.product.type)"
                                    :class="['h-8 w-8', getTypeColor(props.product.type)]"
                                />
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="flex-1">
                            <div class="mb-4 flex items-center gap-3">
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                                        props.product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                                        props.product.type === 'product' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'
                                    ]"
                                >
                                    {{ props.product.type.charAt(0).toUpperCase() + props.product.type.slice(1) }}
                                </span>
                            </div>
                            
                            <p v-if="props.product.description" class="text-gray-700 bg-gray-50 p-4 rounded-md">
                                {{ props.product.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Details Grid -->
            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Main Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Basic Information</h2>
                            <p class="text-sm text-gray-600">Product specifications and pricing</p>
                        </div>
                        <div class="p-6">
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div v-if="props.product.sku" class="flex items-center gap-3">
                                    <Hash class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">SKU</div>
                                        <div class="text-gray-900">{{ props.product.sku }}</div>
                                    </div>
                                </div>

                                <div v-if="props.product.category" class="flex items-center gap-3">
                                    <Tag class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Category</div>
                                        <div class="text-gray-900">{{ props.product.category }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <DollarSign class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Price</div>
                                        <div class="text-gray-900">R{{ Number(props.product.price).toFixed(2) }} / {{ props.product.unit }}</div>
                                    </div>
                                </div>

                                <div v-if="props.product.cost" class="flex items-center gap-3">
                                    <DollarSign class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Cost</div>
                                        <div class="text-gray-900">R{{ Number(props.product.cost).toFixed(2) }}</div>
                                    </div>
                                </div>

                                <div v-if="profitMargin" class="flex items-center gap-3">
                                    <DollarSign class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Profit Margin</div>
                                        <div class="text-gray-900">{{ profitMargin.toFixed(1) }}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory Information (Products Only) -->
                    <div v-if="props.product.type === 'product'" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Inventory Information</h2>
                            <p class="text-sm text-gray-600">Stock tracking and inventory details</p>
                        </div>
                        <div class="p-6">
                            
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="flex items-center gap-3">
                                    <Package class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Stock Tracking</div>
                                        <div class="text-gray-900">{{ props.product.track_stock ? 'Enabled' : 'Disabled' }}</div>
                                    </div>
                                </div>

                                <div v-if="props.product.track_stock" class="flex items-center gap-3">
                                    <Hash class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Current Stock</div>
                                        <div class="text-gray-900">{{ props.product.stock_quantity }}</div>
                                    </div>
                                </div>

                                <div v-if="props.product.track_stock" class="flex items-center gap-3">
                                    <Hash class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Min Stock Level</div>
                                        <div class="text-gray-900">{{ props.product.min_stock_level }}</div>
                                    </div>
                                </div>
                            </div>

                            <div v-if="props.product.track_stock" class="mt-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-medium text-gray-500">Stock Status:</span>
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                            getStockStatus(props.product).bg,
                                            getStockStatus(props.product).color
                                        ]"
                                    >
                                        {{ getStockStatus(props.product).text }}
                                    </span>
                                </div>
                            </div>

                            <!-- Advanced Inventory Features -->
                            <div v-if="props.product.track_stock" class="mt-6 space-y-4">
                                <div v-if="(props.product as any).track_batches" class="flex items-center justify-between p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Batch/Lot Tracking</div>
                                        <div class="text-xs text-gray-600">Track batches with expiry dates</div>
                                    </div>
                                    <Link
                                        :href="`/products/${props.product.id}/batches`"
                                        class="text-sm text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        Manage Batches →
                                    </Link>
                                </div>

                                <div v-if="(props.product as any).track_serial_numbers" class="flex items-center justify-between p-4 bg-green-50 rounded-lg border border-green-200">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">Serial Number Tracking</div>
                                        <div class="text-xs text-gray-600">Track individual serial numbers</div>
                                    </div>
                                    <Link
                                        :href="`/products/${props.product.id}/serial-numbers`"
                                        class="text-sm text-green-600 hover:text-green-800 font-medium"
                                    >
                                        Manage Serial Numbers →
                                    </Link>
                                </div>

                                <div v-if="(props.product as any).valuation_method" class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="text-sm font-medium text-gray-900">Valuation Method</div>
                                    <div class="text-xs text-gray-600 mt-1">
                                        {{ (props.product as any).valuation_method === 'fifo' ? 'FIFO (First In, First Out)' : 
                                            (props.product as any).valuation_method === 'lifo' ? 'LIFO (Last In, First Out)' : 
                                            'Average Cost' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div v-if="props.product.tags && props.product.tags.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Tags</h2>
                            <p class="text-sm text-gray-600">Product categorization tags</p>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="tag in props.product.tags"
                                    :key="tag"
                                    class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800"
                                >
                                    {{ tag }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="props.product.notes" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                            <p class="text-sm text-gray-600">Additional product information</p>
                        </div>
                        <div class="p-6">
                            <div class="text-gray-700 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.product.notes }}</div>
                        </div>
                    </div>
                    </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quick Stats -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Quick Stats</h3>
                            <p class="text-sm text-gray-600">Product overview and status</p>
                        </div>
                        <div class="p-6">
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Type</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.product.type.charAt(0).toUpperCase() + props.product.type.slice(1) }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.product.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.product.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                <div v-if="props.product.track_stock" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Stock Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            getStockStatus(props.product).color
                                        ]"
                                    >
                                        {{ getStockStatus(props.product).text }}
                                    </span>
                                </div>

                                <div v-if="profitMargin" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Profit Margin</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ profitMargin.toFixed(1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timestamps -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Timestamps</h3>
                            <p class="text-sm text-gray-600">Creation and modification dates</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Created</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.product.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.product.updated_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Invoice Line Items -->
            <div v-if="props.recentInvoiceLineItems && props.recentInvoiceLineItems.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent Invoice Usage</h2>
                    <p class="text-sm text-gray-600">Latest invoice line items where this product was used</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Price</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="item in props.recentInvoiceLineItems" :key="item.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <Link
                                        :href="invoices.show(item.invoice_id).url"
                                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ item.invoice?.invoice_number || '—' }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ item.invoice?.customer?.name || '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                    R{{ Number(item.unit_price).toFixed(2) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
