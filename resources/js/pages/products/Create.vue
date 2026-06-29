<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, Package, Wrench } from 'lucide-vue-next';
import products from '@/routes/products';

interface Category {
    id: number;
    name: string;
    color: string;
}

interface Company {
    id: number;
    name: string;
}

interface ChartOfAccount {
    id: number;
    account_code: string;
    account_name: string;
    account_type: string;
}

interface SupplierOption {
    id: number;
    name: string;
}

const props = defineProps<{
    categories: Category[];
    currentCompany: Company;
    chartOfAccounts: ChartOfAccount[];
    suppliers: SupplierOption[];
}>();

const form = useForm({
    name: '',
    description: '',
    type: 'product' as 'product' | 'service',
    sku: '',
    barcode: '',
    price: 0,
    cost: null as number | null,
    unit: 'piece',
    stock_quantity: 0,
    min_stock_level: 0,
    track_stock: true,
    track_batches: false,
    track_serial_numbers: false,
    valuation_method: 'fifo' as 'fifo' | 'lifo' | 'average_cost',
    is_active: true,
    category: '',
    supplier_id: null as number | null,
    tags: [] as string[],
    image_path: '',
    notes: '',
    purchase_account_code: '',
    sales_account_code: '',
});

const newTag = ref('');

const isService = computed(() => form.type === 'service');

const commonUnits = [
    'piece', 'kg', 'lb', 'g', 'oz', 'liter', 'gallon', 'meter', 'foot', 'hour', 'day', 'month', 'year'
];

function submit() {
    form.post(products.store().url);
}

function addTag() {
    if (newTag.value.trim() && !form.tags.includes(newTag.value.trim())) {
        form.tags.push(newTag.value.trim());
        newTag.value = '';
    }
}

function removeTag(tag: string) {
    form.tags = form.tags.filter(t => t !== tag);
}

function getTypeIcon(type: string) {
    return type === 'product' ? Package : Wrench;
}

function getTypeColor(type: string) {
    return type === 'product' ? 'text-blue-600' : 'text-green-600';
}
</script>

<template>
    <Head title="New Product/Service" />

    <AppLayout :breadcrumbs="[
        { title: 'Products & Services', href: products.index().url },
        { title: 'Create', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Creating product for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="products.index().url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Products
                </Link>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Create New Product/Service</h1>
                    <p class="text-gray-600">Add a new product or service to your catalog</p>
                </div>

                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Basic Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Name -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Name *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Type *
                                </label>
                                <div class="mt-2 grid grid-cols-2 gap-3">
                                    <label class="relative cursor-pointer">
                                        <input
                                            v-model="form.type"
                                            type="radio"
                                            value="product"
                                            class="sr-only"
                                        />
                                        <div
                                            :class="[
                                                'flex items-center gap-3 rounded-lg border p-4',
                                                form.type === 'product'
                                                    ? 'border-blue-500 bg-blue-50'
                                                    : 'border-gray-300 hover:border-gray-400'
                                            ]"
                                        >
                                            <Package class="h-5 w-5 text-blue-600" />
                                            <div>
                                                <div class="font-medium text-gray-900">Product</div>
                                                <div class="text-sm text-gray-500">Physical items with inventory</div>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="relative cursor-pointer">
                                        <input
                                            v-model="form.type"
                                            type="radio"
                                            value="service"
                                            class="sr-only"
                                        />
                                        <div
                                            :class="[
                                                'flex items-center gap-3 rounded-lg border p-4',
                                                form.type === 'service'
                                                    ? 'border-green-500 bg-green-50'
                                                    : 'border-gray-300 hover:border-gray-400'
                                            ]"
                                        >
                                            <Wrench class="h-5 w-5 text-green-600" />
                                            <div>
                                                <div class="font-medium text-gray-900">Service</div>
                                                <div class="text-sm text-gray-500">Intangible offerings</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.type }}
                                </div>
                            </div>

                            <!-- Category -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Category
                                </label>
                                <select
                                    v-model="form.category"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">Select a category</option>
                                    <option
                                        v-for="category in props.categories"
                                        :key="category.id"
                                        :value="category.name"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.category" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.category }}
                                </div>
                            </div>

                            <!-- Supplier -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Supplier
                                </label>
                                <select
                                    v-model="form.supplier_id"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option :value="null">No supplier</option>
                                    <option
                                        v-for="supplier in props.suppliers"
                                        :key="supplier.id"
                                        :value="supplier.id"
                                    >
                                        {{ supplier.name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.supplier_id }}
                                </div>
                            </div>

                            <!-- SKU -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    SKU (Stock Keeping Unit)
                                </label>
                                <input
                                    v-model="form.sku"
                                    type="text"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.sku" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.sku }}
                                </div>
                            </div>

                            <!-- Barcode -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Barcode
                                </label>
                                <input
                                    v-model="form.barcode"
                                    type="text"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    placeholder="Scan or enter barcode"
                                />
                                <div v-if="form.errors.barcode" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.barcode }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">For barcode scanning and quick lookup</p>
                            </div>

                            <!-- Unit -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Unit *
                                </label>
                                <div class="mt-1 flex gap-2">
                                    <select
                                        v-model="form.unit"
                                        class="flex-1 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option v-for="unit in commonUnits" :key="unit" :value="unit">
                                            {{ unit }}
                                        </option>
                                    </select>
                                    <input
                                        v-model="form.unit"
                                        type="text"
                                        placeholder="Custom unit"
                                        class="flex-1 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                                <div v-if="form.errors.unit" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.unit }}
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Pricing</h2>
                        
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Price -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Price *
                                </label>
                                <div class="mt-1 relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">R</span>
                                    </div>
                                    <input
                                        v-model="form.price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        class="w-full pl-7 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                                <div v-if="form.errors.price" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.price }}
                                </div>
                            </div>

                            <!-- Cost -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Cost Price
                                </label>
                                <div class="mt-1 relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">R</span>
                                    </div>
                                    <input
                                        v-model="form.cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full pl-7 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                                <div v-if="form.errors.cost" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.cost }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Used for profit margin calculation</p>
                            </div>
                        </div>
                    </div>

                    <!-- Accounting -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Accounting</h2>
                        
                        <div class="grid gap-6 md:grid-cols-2">
                            <!-- Purchase Account -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Purchase Account
                                </label>
                                <select
                                    v-model="form.purchase_account_code"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">-- Select Purchase Account --</option>
                                    <option
                                        v-for="account in props.chartOfAccounts"
                                        :key="account.id"
                                        :value="account.account_code"
                                    >
                                        {{ account.account_code }} - {{ account.account_name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Account used for purchase transactions</p>
                                <div v-if="form.errors.purchase_account_code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.purchase_account_code }}
                                </div>
                            </div>

                            <!-- Sales Account -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Sales Account
                                </label>
                                <select
                                    v-model="form.sales_account_code"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="">-- Select Sales Account --</option>
                                    <option
                                        v-for="account in props.chartOfAccounts"
                                        :key="account.id"
                                        :value="account.account_code"
                                    >
                                        {{ account.account_code }} - {{ account.account_name }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500">Account used for sales transactions</p>
                                <div v-if="form.errors.sales_account_code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.sales_account_code }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory (Products Only) -->
                    <div v-if="!isService" class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Inventory Management</h2>
                        
                        <div class="grid gap-6 md:grid-cols-3">
                            <!-- Track Stock -->
                            <div class="md:col-span-3">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.track_stock"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Track inventory for this product</span>
                                </label>
                            </div>

                            <!-- Stock Quantity -->
                            <div v-if="form.track_stock">
                                <label class="block text-sm font-medium text-gray-700">
                                    Current Stock
                                </label>
                                <input
                                    v-model="form.stock_quantity"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.stock_quantity" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.stock_quantity }}
                                </div>
                            </div>

                            <!-- Min Stock Level -->
                            <div v-if="form.track_stock">
                                <label class="block text-sm font-medium text-gray-700">
                                    Minimum Stock Level
                                </label>
                                <input
                                    v-model="form.min_stock_level"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.min_stock_level" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.min_stock_level }}
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Alert when stock falls below this level</p>
                            </div>

                            <!-- Valuation Method -->
                            <div v-if="form.track_stock">
                                <label class="block text-sm font-medium text-gray-700">
                                    Inventory Valuation Method
                                </label>
                                <select
                                    v-model="form.valuation_method"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option value="fifo">FIFO (First In, First Out)</option>
                                    <option value="lifo">LIFO (Last In, First Out)</option>
                                    <option value="average_cost">Average Cost</option>
                                </select>
                                <div v-if="form.errors.valuation_method" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.valuation_method }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Method used to calculate cost of goods sold</p>
                            </div>

                            <!-- Track Batches -->
                            <div v-if="form.track_stock" class="md:col-span-3">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.track_batches"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Track batches/lots for this product</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500 ml-6">Enable batch tracking for expiry dates and lot numbers</p>
                            </div>

                            <!-- Track Serial Numbers -->
                            <div v-if="form.track_stock" class="md:col-span-3">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.track_serial_numbers"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Track serial numbers for this product</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500 ml-6">Enable individual serial number tracking</p>
                            </div>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Tags</h2>
                        
                        <div class="space-y-4">
                            <!-- Add Tag -->
                            <div class="flex gap-2">
                                <input
                                    v-model="newTag"
                                    type="text"
                                    placeholder="Add a tag"
                                    class="flex-1 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    @keyup.enter="addTag"
                                />
                                <button
                                    type="button"
                                    @click="addTag"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                >
                                    Add
                                </button>
                            </div>

                            <!-- Tags List -->
                            <div v-if="form.tags.length > 0" class="flex flex-wrap gap-2">
                                <span
                                    v-for="tag in form.tags"
                                    :key="tag"
                                    class="flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800"
                                >
                                    {{ tag }}
                                    <button
                                        type="button"
                                        @click="removeTag(tag)"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        ×
                                    </button>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Additional Information</h2>
                        
                        <div class="space-y-6">
                            <!-- Image Path -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Image Path
                                </label>
                                <input
                                    v-model="form.image_path"
                                    type="text"
                                    placeholder="/images/products/example.jpg"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.image_path" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.image_path }}
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Notes
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.notes }}
                                </div>
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for sale)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="products.index().url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Product/Service' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
