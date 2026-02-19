<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import purchaseOrders from '@/routes/purchase-orders';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    cost_price: number;
    selling_price: number;
    stock_quantity: number;
}

interface Supplier {
    id: number;
    name: string;
}

interface Props {
    supplier_id?: number;
    suppliers?: Supplier[];
    products?: Product[];
    taxRates?: { id: number; name: string; rate: number; is_default_purchasing: boolean }[];
    defaultPurchasingTaxRateId?: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string }[];
    defaultPurchasingAccountId: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    suppliers: () => [],
    products: () => [],
    chartOfAccounts: () => [],
    defaultPurchasingAccountId: null,
});

// Track product search queries for each line item
const productSearchQueries = ref<Record<number, string>>({});
const productSearchFocused = ref<Record<number, boolean>>({});

interface LineItem {
    product_id: string | number;
    quantity: number;
    unit_cost: number;
    description: string;
    total: number;
    tax_rate_id?: number | null;
    account_id?: number | null;
}

const form = useForm({
    supplier_id: props.supplier_id || '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    terms: '',
    items: [] as LineItem[],
});

function addLineItem() {
    form.items.push({
        product_id: '',
        quantity: 1,
        unit_cost: 0,
        description: '',
        total: 0,
        tax_rate_id: props.defaultPurchasingTaxRateId || null,
        account_id: props.defaultPurchasingAccountId || null,
    });
}

function removeLineItem(index: number) {
    form.items.splice(index, 1);
    calculateTotals();
}

// Filter products based on search query
function filteredProducts(index: number) {
    const query = productSearchQueries.value[index]?.toLowerCase() || '';
    if (!query) return [];
    
    return props.products.filter(product => {
        const nameMatch = product.name?.toLowerCase().includes(query);
        const skuMatch = product.sku?.toLowerCase().includes(query);
        return nameMatch || skuMatch;
    }).slice(0, 10); // Limit to 10 results
}

// Get display name for selected product
function getProductDisplayName(productId: string | number | null | undefined) {
    if (!productId) return '';
    const product = props.products.find(p => p.id === parseInt(productId.toString()));
    if (!product) return '';
    return product.sku ? `${product.name} (${product.sku})` : product.name;
}

// Handle product search input
function handleProductSearch(index: number, event: Event) {
    const target = event.target as HTMLInputElement;
    productSearchQueries.value[index] = target.value;
    
    // If input is cleared, clear product selection
    if (!target.value) {
        clearProduct(index);
    }
}

// Handle product input focus
function handleProductFocus(index: number) {
    productSearchFocused.value[index] = true;
    const productId = form.items[index].product_id;
    if (productId) {
        // Show current product name/SKU in search
        const product = props.products.find(p => p.id === parseInt(productId.toString()));
        if (product) {
            productSearchQueries.value[index] = product.sku ? `${product.name} ${product.sku}` : product.name;
        }
    }
}

// Handle product input blur (with delay to allow click on dropdown)
function handleProductBlur(index: number) {
    setTimeout(() => {
        productSearchFocused.value[index] = false;
        productSearchQueries.value[index] = '';
    }, 200);
}

// Select product from search results
function selectProductFromSearch(index: number, product: Product) {
    selectProduct(index, { target: { value: product.id.toString() } } as Event);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Select custom item (no product)
function selectCustomItem(index: number) {
    selectProduct(index, { target: { value: '' } } as Event);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Clear product selection
function clearProduct(index: number) {
    selectProduct(index, { target: { value: '' } } as Event);
    productSearchQueries.value[index] = '';
}

function selectProduct(index: number, event: Event) {
    const select = event.target as HTMLSelectElement;
    const productId = parseInt(select.value);
    
    if (productId) {
        const product = props.products.find(p => p.id === productId);
        if (product) {
            form.items[index].product_id = productId;
            form.items[index].unit_cost = product.cost_price || 0;
            form.items[index].description = product.name;
            calculateItemTotal(index);
        }
    } else {
        form.items[index].product_id = '';
        form.items[index].unit_cost = 0;
        form.items[index].description = '';
        form.items[index].total = 0;
    }
}

function calculateItemTotal(index: number) {
    const item = form.items[index];
    item.total = item.quantity * item.unit_cost;
    calculateTotals();
}

function calculateTotals() {
    // Totals are calculated server-side, but we can show a preview
}

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity * item.unit_cost), 0);
});

const taxAmount = computed(() => {
    return form.items.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity) || 0;
        const cost = Number(item.unit_cost) || 0;
        const lineTotal = qty * cost;
        const taxRate = (props.taxRates || []).find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            return sum + Math.ceil(lineTotal * (taxRate.rate / 100) * 100) / 100;
        }
        return sum;
    }, 0);
});

const total = computed(() => subtotal.value + taxAmount.value);

function submit() {
    form.post(purchaseOrders.store().url);
}
</script>

<template>
    <Head title="Create Purchase Order" />
    <AppLayout :breadcrumbs="[
        { title: 'Purchase Orders', href: purchaseOrders.index().url },
        { title: 'Create Purchase Order', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="purchaseOrders.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Purchase Orders
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Create Purchase Order</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Supplier <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.supplier_id"
                                required
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.supplier_id }"
                            >
                                <option value="">Select a supplier</option>
                                <option v-for="supplier in props.suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.supplier_id }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Order Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.order_date"
                                type="date"
                                required
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.order_date }"
                            />
                            <div v-if="form.errors.order_date" class="mt-1 text-sm text-red-600">
                                {{ form.errors.order_date }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Expected Delivery Date
                            </label>
                            <input
                                v-model="form.expected_delivery_date"
                                type="date"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Items</h2>
                        <button
                            type="button"
                            @click="addLineItem"
                            class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Plus class="h-4 w-4" />
                            Add Item
                        </button>
                    </div>

                    <div v-if="form.errors.items" class="mb-4 text-sm text-red-600">
                        {{ form.errors.items }}
                    </div>

                    <div v-if="form.items.length === 0" class="py-8 text-center text-sm text-gray-500">
                        No items added yet. Click "Add Item" to start.
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="rounded-lg border border-gray-200 p-4"
                        >
                            <div class="grid gap-4 md:grid-cols-7">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        Product
                                    </label>
                                    <div class="relative">
                                        <input 
                                            type="text"
                                            :value="getProductDisplayName(item.product_id)"
                                            @input="handleProductSearch(index, $event)"
                                            @focus="handleProductFocus(index)"
                                            @blur="handleProductBlur(index)"
                                            placeholder="Search by name or SKU..."
                                            class="w-full rounded border px-3 py-2 pr-8 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                        />
                                        <svg v-if="item.product_id" @click="clearProduct(index)" class="absolute right-2 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400 hover:text-gray-600 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <!-- Dropdown with filtered products -->
                                        <div 
                                            v-if="productSearchFocused[index] && productSearchQueries[index] && (filteredProducts(index).length > 0 || !item.product_id)"
                                            class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                                        >
                                            <!-- Option to use custom item (shown first) -->
                                            <div 
                                                v-if="!item.product_id"
                                                @mousedown.prevent="selectCustomItem(index)"
                                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-gray-600 italic border-b border-gray-100"
                                            >
                                                Use custom item
                                            </div>
                                            <!-- Filtered products -->
                                            <div 
                                                v-for="product in filteredProducts(index)" 
                                                :key="product.id"
                                                @mousedown.prevent="selectProductFromSearch(index, product)"
                                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                            >
                                                <div class="font-medium text-gray-900">{{ product.name }}</div>
                                                <div v-if="product.sku" class="text-sm text-gray-500">SKU: {{ product.sku }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        Quantity <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.quantity"
                                        @input="calculateItemTotal(index)"
                                        type="number"
                                        min="1"
                                        required
                                        class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        Unit Cost <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        v-model.number="item.unit_cost"
                                        @input="calculateItemTotal(index)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                        class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1 block text-xs font-medium text-gray-500">Tax Rate</label>
                                    <select
                                        v-model="item.tax_rate_id"
                                        class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option :value="null">None</option>
                                        <option v-for="tr in (props.taxRates || [])" :key="tr.id" :value="tr.id">
                                            {{ tr.name }} ({{ tr.rate }}%)
                                        </option>
                                    </select>
                                </div>

                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        @click="removeLineItem(index)"
                                        class="w-full rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                                    >
                                        <Trash2 class="mx-auto h-4 w-4" />
                                    </button>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <input
                                    v-model="item.description"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div class="mt-2 text-right text-sm font-medium text-gray-900">
                                Total: R{{ (item.quantity * item.unit_cost).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="form.items.length > 0" class="mt-6 border-t pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Subtotal</span>
                                    <span class="text-sm font-medium">R{{ subtotal.toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Tax</span>
                                    <span class="text-sm font-medium">R{{ taxAmount.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t pt-2">
                                    <span class="text-base font-semibold">Total</span>
                                    <span class="text-base font-bold text-green-600">R{{ total.toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Additional Information</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Internal notes about this purchase order..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Terms</label>
                            <textarea
                                v-model="form.terms"
                                rows="3"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Payment terms and conditions..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t pt-6">
                    <Link
                        :href="purchaseOrders.index().url"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || form.items.length === 0"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Purchase Order' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

