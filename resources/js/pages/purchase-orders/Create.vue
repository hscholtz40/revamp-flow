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
}

const props = withDefaults(defineProps<Props>(), {
    suppliers: () => [],
    products: () => [],
});

interface LineItem {
    product_id: string | number;
    quantity: number;
    unit_cost: number;
    description: string;
    total: number;
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
    });
}

function removeLineItem(index: number) {
    form.items.splice(index, 1);
    calculateTotals();
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
                            <div class="grid gap-4 md:grid-cols-5">
                                <div class="md:col-span-2">
                                    <label class="mb-1 block text-sm font-medium text-gray-700">
                                        Product
                                    </label>
                                    <select
                                        :value="item.product_id"
                                        @change="selectProduct(index, $event)"
                                        class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Custom Item</option>
                                        <option v-for="product in props.products" :key="product.id" :value="product.id">
                                            {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }}
                                        </option>
                                    </select>
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
                            <div class="text-right">
                                <div class="text-sm text-gray-500">Subtotal</div>
                                <div class="text-xl font-bold text-gray-900">
                                    R{{ subtotal.toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
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

