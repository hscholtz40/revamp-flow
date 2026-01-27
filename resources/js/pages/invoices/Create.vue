<template>

    <Head title="New Invoice" />

    <AppLayout :breadcrumbs="[
        { title: 'Invoices', href: invoices.index().url },
        { title: 'Create', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Creating invoice for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">New Invoice</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
                            <select v-model="form.customer_id" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.customer_id }" required>
                                <option value="">Select a customer</option>
                                <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.customer_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Salesperson</label>
                            <select v-model="form.salesperson_id" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.salesperson_id }">
                                <option value="">Select a salesperson</option>
                                <option v-for="user in props.users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.salesperson_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.salesperson_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.title }" required />
                            <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                                {{ form.errors.title }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Invoice Date *</label>
                            <input v-model="form.invoice_date" type="date" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.invoice_date }" required />
                            <div v-if="form.errors.invoice_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.invoice_date }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date *</label>
                            <input v-model="form.due_date" type="date" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.due_date }" required />
                            <div v-if="form.errors.due_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.due_date }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded border px-3 py-2"
                            :class="{ 'border-red-500': form.errors.description }"></textarea>
                        <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
                            {{ form.errors.description }}
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                        <button type="button" @click="addLineItem"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                            Add Item
                        </button>
                    </div>

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in form.line_items" :key="index" class="border rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                    <select v-model="item.product_id" @change="selectProduct(index, $event)"
                                        class="w-full rounded border px-3 py-2">
                                        <option value="">Custom Item</option>
                                        <option v-for="product in props.products" :key="product.id" :value="product.id">
                                            {{ product.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                                    <input v-model="item.description" type="text"
                                        class="w-full rounded border px-3 py-2" required />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <input v-model.number="item.quantity" @input="calculateItemTotal(index)"
                                        type="number" min="1" class="w-full rounded border px-3 py-2" required />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                                    <input v-model.number="item.unit_price" @input="calculateItemTotal(index)"
                                        type="number" step="0.01" min="0" class="w-full rounded border px-3 py-2"
                                        required />
                                </div>
                            </div>

                            <!-- Serial Number Selection -->
                            <div v-if="item.product_id && props.products.find(p => p.id === parseInt(item.product_id))?.track_serial_numbers" class="mt-4 border-t pt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Serial Numbers
                                    <span class="text-xs text-gray-500">
                                        (Select {{ item.quantity || 0 }} serial number(s))
                                    </span>
                                </label>
                                <div class="max-h-40 space-y-2 overflow-y-auto rounded border border-gray-300 p-2">
                                    <label
                                        v-for="serial in props.products.find(p => p.id === parseInt(item.product_id))?.serialNumbers || []"
                                        :key="serial.id"
                                        class="flex items-center gap-2 rounded px-2 py-1 hover:bg-gray-50"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="serial.id"
                                            v-model="item.serial_number_ids"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            :disabled="(item.serial_number_ids?.length || 0) >= (item.quantity || 0) && !item.serial_number_ids?.includes(serial.id)"
                                        />
                                        <span class="text-sm text-gray-900">{{ serial.serial_number }}</span>
                                    </label>
                                    <div v-if="!props.products.find(p => p.id === parseInt(item.product_id))?.serialNumbers || props.products.find(p => p.id === parseInt(item.product_id))?.serialNumbers.length === 0" class="text-sm text-gray-500">
                                        No available serial numbers for this product.
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    Selected: {{ item.serial_number_ids?.length || 0 }} / {{ item.quantity || 0 }}
                                </p>
                            </div>

                            <!-- Discount Fields for Line Item -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 pt-4 border-t">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Amount (R)</label>
                                    <input
                                        v-model.number="item.discount_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.discount_amount`] }"
                                        placeholder="0.00"
                                        @input="watchLineItemDiscount(index)"
                                    />
                                    <div v-if="form.errors[`line_items.${index}.discount_amount`]" class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.discount_amount`] }}
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Discount Percentage (%)</label>
                                    <input
                                        v-model.number="item.discount_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.discount_percentage`] }"
                                        placeholder="0.00"
                                        @input="watchLineItemDiscount(index)"
                                    />
                                    <div v-if="form.errors[`line_items.${index}.discount_percentage`]" class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.discount_percentage`] }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-4">
                                <div class="text-sm font-medium text-gray-900">
                                    Total: {{ formatCurrency(item.total) }}
                                </div>
                                <button type="button" @click="removeLineItem(index)"
                                    class="text-red-600 hover:text-red-800">
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div><br />

                    <div class="flex items-center justify-between mb-4">
                        <button type="button" @click="addLineItem"
                            class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
                            +
                        </button>
                    </div>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Totals</h2>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-medium text-red-600">-{{ formatCurrency(discountAmount) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (15%):</span>
                            <span class="font-medium">{{ formatCurrency(taxAmount) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold border-t pt-2">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes and Terms -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h2>
                    <div class="space-y-6">
                        <!-- Discount Fields (Read-only, calculated from line items) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Total Discount</label>
                                <input
                                    type="text"
                                    :value="formatCurrency(discountAmount)"
                                    class="w-full rounded border px-3 py-2 bg-gray-50"
                                    readonly
                                    disabled
                                />
                                <p class="text-xs text-gray-500 mt-1">Calculated from line item discounts</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal Before Discount</label>
                                <input
                                    type="text"
                                    :value="formatCurrency(subtotalBeforeDiscount)"
                                    class="w-full rounded border px-3 py-2 bg-gray-50"
                                    readonly
                                    disabled
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                <textarea v-model="form.notes" rows="4" class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.notes }"></textarea>
                                <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.notes }}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                            <textarea v-model="form.terms" rows="4" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.terms }"></textarea>
                            <div v-if="form.errors.terms" class="text-red-500 text-sm mt-1">
                                {{ form.errors.terms }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="invoices.index().url"
                        class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                    Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Invoice' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import invoices from '@/routes/invoices';

interface Customer {
    id: number;
    name: string;
}

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    price: number;
    stock_quantity: number;
    track_stock: boolean;
    track_serial_numbers: boolean;
    serialNumbers?: SerialNumber[];
}

interface User {
    id: number;
    name: string;
}

interface Company {
    id: number;
    name: string;
}

interface LineItem {
    product_id: string | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    total: number;
    serial_number_ids?: number[];
}

interface Props {
    customers: Customer[];
    products: Product[];
    users: User[];
    currentCompany: Company;
    selectedCustomer?: Customer | null;
    defaultTerms?: string;
    currentUser: User;
}

const props = defineProps<Props>();

const form = useForm({
    title: '',
    description: '',
    customer_id: props.selectedCustomer?.id || '',
    salesperson_id: props.currentUser.id, // Default to current user
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
    tax_rate: 15,
    discount_amount: 0,
    discount_percentage: 0,
    notes: '',
    terms: props.defaultTerms || '',
    line_items: [
        {
            product_id: null,
            description: '',
            quantity: 1,
            unit_price: 0,
            discount_amount: 0,
            discount_percentage: 0,
            total: 0,
            serial_number_ids: [],
        },
    ] as LineItem[],
});

// Watch for customer changes to update title
watch(() => form.customer_id, (newCustomerId) => {
    if (newCustomerId) {
        const customer = props.customers.find(c => c.id === parseInt(newCustomerId));
        if (customer) {
            form.title = `Invoice for ${customer.name}`;
        }
    }
});

const addLineItem = () => {
    form.line_items.push({
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        total: 0,
        serial_number_ids: [] as number[],
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
};

const selectProduct = (index: number, event: Event) => {
    const target = event.target as HTMLSelectElement;
    const productId = target.value;

    // Ensure serial_number_ids is initialized
    if (!form.line_items[index].serial_number_ids) {
        form.line_items[index].serial_number_ids = [];
    }

    if (productId) {
        const product = props.products.find(p => p.id === parseInt(productId));
        if (product) {
            form.line_items[index].product_id = productId;
            form.line_items[index].description = product.name;
            form.line_items[index].unit_price = product.price;
            calculateItemTotal(index);
        }
    } else {
        form.line_items[index].product_id = null;
        form.line_items[index].description = '';
        form.line_items[index].unit_price = 0;
        form.line_items[index].total = 0;
        form.line_items[index].serial_number_ids = [];
    }
};

const calculateItemTotal = (index: number) => {
    const item = form.line_items[index];
    const quantity = item.quantity || 0;
    const unitPrice = item.unit_price || 0;
    const discountAmount = item.discount_amount || 0;
    const discountPercentage = item.discount_percentage || 0;
    
    const subtotal = quantity * unitPrice;
    
    // Apply discount: percentage takes precedence over amount
    let finalDiscount = discountAmount;
    if (discountPercentage > 0) {
        finalDiscount = subtotal * (discountPercentage / 100);
    }
    
    item.total = Math.max(0, subtotal - finalDiscount);
};

const watchLineItemDiscount = (index: number) => {
    const item = form.line_items[index];
    if (!item) return;
    
    // Clear one discount field when the other is filled
    if (item.discount_amount && item.discount_amount > 0) {
        item.discount_percentage = 0;
    }
    if (item.discount_percentage && item.discount_percentage > 0) {
        item.discount_amount = 0;
    }
    
    calculateItemTotal(index);
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: 'ZAR',
    }).format(amount || 0);
};

// Calculate subtotal before discounts
const subtotalBeforeDiscount = computed(() => {
    return form.line_items.reduce((sum, item) => {
        const quantity = item.quantity || 0;
        const unitPrice = item.unit_price || 0;
        return sum + (quantity * unitPrice);
    }, 0);
});

// Calculate total discount from line items
const lineItemDiscountsTotal = computed(() => {
    return form.line_items.reduce((sum, item) => {
        const quantity = item.quantity || 0;
        const unitPrice = item.unit_price || 0;
        const discountAmount = item.discount_amount || 0;
        const discountPercentage = item.discount_percentage || 0;
        
        const itemSubtotal = quantity * unitPrice;
        let itemDiscount = discountAmount;
        if (discountPercentage > 0) {
            itemDiscount = itemSubtotal * (discountPercentage / 100);
        }
        
        return sum + itemDiscount;
    }, 0);
});

const subtotal = computed(() => {
    return subtotalBeforeDiscount.value - lineItemDiscountsTotal.value;
});

const discountAmount = computed(() => {
    // Total discount is the sum of all line item discounts
    return lineItemDiscountsTotal.value;
});

const taxAmount = computed(() => {
    // Subtotal already has discounts applied, so calculate tax directly on it
    // Round UP to 2 decimal places
    return Math.ceil((subtotal.value * (form.tax_rate / 100)) * 100) / 100;
});

const total = computed(() => {
    // Subtotal already has discounts applied, so just add tax
    return subtotal.value + taxAmount.value;
});

// Update form discount_amount when line item discounts change
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

const submit = () => {
    form.post(invoices.store().url);
};
</script>
