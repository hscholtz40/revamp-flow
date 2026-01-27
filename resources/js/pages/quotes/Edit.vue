<template>

    <Head :title="`Edit Quote ${props.quote.quote_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Quotes', href: quotes.index().url },
        { title: props.quote.quote_number, href: quotes.show(props.quote.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Editing quote for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Quote {{ props.quote.quote_number }}</h1>
            </div>

            <!-- Warning for completed quotes -->
            <div v-if="isCompleted && !canEditCompleted" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Quote is Accepted</h3>
                        <p class="text-sm text-yellow-700 mt-1">This quote has been accepted and cannot be edited. Contact an administrator if changes are needed.</p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
                            <select v-model="form.customer_id" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.customer_id, 'bg-gray-100 cursor-not-allowed': !canEdit }" 
                                :disabled="!canEdit" required>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                            <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.title }" required />
                            <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                                {{ form.errors.title }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select v-model="form.status" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.status }" required>
                                <option value="draft">Draft</option>
                                <option value="sent">Sent</option>
                                <option value="accepted">Accepted</option>
                                <option value="rejected">Rejected</option>
                                <option value="expired">Expired</option>
                            </select>
                            <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                {{ form.errors.status }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input v-model="form.expiry_date" type="date" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.expiry_date }" />
                            <div v-if="form.errors.expiry_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.expiry_date }}
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.description }"></textarea>
                            <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
                                {{ form.errors.description }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                        <button type="button" @click="addLineItem"
                            class="rounded bg-blue-600 px-3 py-1 text-white text-sm hover:bg-blue-700">
                            Add Item
                        </button>
                    </div>

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <div class="space-y-4">
                        <div v-for="(item, index) in form.line_items" :key="index"
                            class="border rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                    <select :value="item.product_id"
                                        @change="selectProduct(index, ($event.target as HTMLSelectElement).value ? parseInt(($event.target as HTMLSelectElement).value) : null)"
                                        class="w-full rounded border px-3 py-2">
                                        <option value="">Custom Item</option>
                                        <option v-for="product in props.products" :key="product.id" :value="product.id">
                                            {{ product.name }} ({{ product.type }})
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                                    <input v-model="item.description" type="text" class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.description`] }"
                                        required />
                                    <div v-if="form.errors[`line_items.${index}.description`]"
                                        class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.description`] }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <input v-model.number="item.quantity" type="number" min="1"
                                        @input="calculateLineTotal(item)" class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.quantity`] }"
                                        required />
                                    <div v-if="form.errors[`line_items.${index}.quantity`]"
                                        class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.quantity`] }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                                    <input v-model.number="item.unit_price" type="number" step="0.01" min="0"
                                        @input="calculateLineTotal(item)" class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.unit_price`] }"
                                        required />
                                    <div v-if="form.errors[`line_items.${index}.unit_price`]"
                                        class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.unit_price`] }}
                                    </div>
                                </div>

                                <div class="flex items-end">
                                    <div class="w-full">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                                        <div class="w-full rounded border px-3 py-2 bg-gray-50 text-gray-700">
                                            R{{ formatCurrency(item.total) }}
                                        </div>
                                    </div>
                                    <button type="button" @click="removeLineItem(index)"
                                        class="ml-2 text-red-600 hover:text-red-800"
                                        :disabled="form.line_items.length === 1">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <!-- Discount Fields for Line Item -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t">
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
                        </div>
                    </div><br />

                    <div class="flex items-center justify-between mb-4">
                        <button type="button" @click="addLineItem"
                            class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700">
                            +
                        </button>
                    </div>

                    <!-- Totals -->
                    <div class="mt-6 border-t pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Subtotal:</span>
                                    <span class="text-sm font-medium">R{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div v-if="discountAmount > 0" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Discount:</span>
                                    <span class="text-sm font-medium text-red-600">-R{{ formatCurrency(discountAmount)
                                        }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Tax ({{ form.tax_rate || 0 }}%):</span>
                                    <span class="text-sm font-medium">R{{ formatCurrency(taxAmount) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-base font-semibold">Total:</span>
                                    <span class="text-base font-semibold">R{{ formatCurrency(total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h2>
                    <div class="space-y-4">
                        <!-- Discount Fields (Read-only, calculated from line items) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Total Discount (R)</label>
                                <input
                                    type="text"
                                    :value="discountAmount.toFixed(2)"
                                    class="w-full rounded border px-3 py-2 bg-gray-50"
                                    readonly
                                    disabled
                                />
                                <p class="text-xs text-gray-500 mt-1">Calculated from line item discounts</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Subtotal Before Discount (R)</label>
                                <input
                                    type="text"
                                    :value="subtotalBeforeDiscount.toFixed(2)"
                                    class="w-full rounded border px-3 py-2 bg-gray-50"
                                    readonly
                                    disabled
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.notes }"></textarea>
                            <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                                {{ form.errors.notes }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                            <textarea v-model="form.terms_conditions" rows="3" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.terms_conditions }"></textarea>
                            <div v-if="form.errors.terms_conditions" class="text-red-500 text-sm mt-1">
                                {{ form.errors.terms_conditions }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="quotes.show(props.quote.id).url"
                        class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                    Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing || !canEdit"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Updating...' : 'Update Quote' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import quotes from '@/routes/quotes';
import { computed, ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    price: number;
    type: string;
}

interface Company {
    id: number;
    name: string;
}

interface LineItem {
    id?: number;
    product_id: string | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    total: number;
}

interface Quote {
    id: number;
    customer_id: number;
    quote_number: string;
    title: string;
    description?: string;
    status: string;
    expiry_date?: string;
    tax_rate: number;
    discount_amount?: number;
    discount_percentage?: number;
    notes?: string;
    terms_conditions?: string;
    line_items: LineItem[];
}

const props = defineProps<{
    quote: Quote;
    customers: Customer[];
    products: Product[];
    currentCompany: Company;
    canEditCompleted: boolean;
}>();

// Check if quote is completed (accepted status)
const isCompleted = computed(() => props.quote.status === 'accepted');

// Check if user can edit this quote
const canEdit = computed(() => !isCompleted.value || props.canEditCompleted);

const form = useForm({
    customer_id: props.quote.customer_id || '',
    title: props.quote.title,
    description: props.quote.description || '',
    status: props.quote.status,
    expiry_date: props.quote.expiry_date ? new Date(props.quote.expiry_date).toISOString().split('T')[0] : '',
    tax_rate: props.quote.tax_rate || 15,
    discount_amount: props.quote.discount_amount || 0,
    discount_percentage: props.quote.discount_percentage || 0,
    notes: props.quote.notes || '',
    terms_conditions: props.quote.terms_conditions || '',
    line_items: props.quote.line_items.map(item => ({
        id: item.id,
        product_id: item.product_id?.toString() || null,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount_amount: (item as any).discount_amount || 0,
        discount_percentage: (item as any).discount_percentage || 0,
        total: item.total,
    })) as LineItem[],
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
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
        calculateTotals();
    }
};

const selectProduct = (index: number, productId: number | null) => {
    const item = form.line_items[index];
    if (!item) return;

    item.product_id = productId ? productId.toString() : null;

    if (productId) {
        const product = props.products.find(p => p.id === productId);
        if (product) {
            item.description = product.name || '';
            item.unit_price = product.price || 0;
        }
    } else {
        item.description = '';
        item.unit_price = 0;
    }

    calculateLineTotal(item);
};

const calculateLineTotal = (item: LineItem) => {
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
    calculateTotals();
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
    
    calculateLineTotal(item);
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
    return Math.ceil((subtotal.value * ((form.tax_rate || 0) / 100)) * 100) / 100;
});

const total = computed(() => {
    // Subtotal already has discounts applied, so just add tax
    return subtotal.value + taxAmount.value;
});

const calculateTotals = () => {
    // This is handled by computed properties
};

// Watch for customer selection to update title
watch(() => form.customer_id, (newCustomerId) => {
    if (newCustomerId && !form.title) {
        const selectedCustomer = props.customers.find(c => c.id == parseInt(String(newCustomerId)));
        if (selectedCustomer) {
            form.title = selectedCustomer.name;
        }
    }
});

// Update form discount_amount when line item discounts change
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

const formatCurrency = (value: number | null | undefined) => {
    const numValue = Number(value) || 0;
    return numValue.toFixed(2);
};

const submit = () => {
    form.put(quotes.update(props.quote.id).url);
};
</script>
