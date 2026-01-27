<template>
    <Head :title="`Edit ${props.jobcard.job_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Jobcards', href: jobcards.index().url },
        { title: props.jobcard.job_number, href: jobcards.show(props.jobcard.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Editing jobcard for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Jobcard {{ props.jobcard.job_number }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
                            <select
                                v-model="form.customer_id"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.customer_id }"
                                required
                            >
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
                            <input
                                v-model="form.title"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.title }"
                                required
                            />
                            <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                                {{ form.errors.title }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status *</label>
                            <select
                                v-model="form.status"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.status }"
                                required
                            >
                                <option value="draft">Draft</option>
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                {{ form.errors.status }}
                            </div>
                        </div><br />


                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                            <input
                                v-model="form.start_date"
                                type="date"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.start_date }"
                            />
                            <div v-if="form.errors.start_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.start_date }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                            <input
                                v-model="form.due_date"
                                type="date"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.due_date }"
                            />
                            <div v-if="form.errors.due_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.due_date }}
                            </div>
                        </div>

                        <div v-if="form.status === 'completed'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Completed Date</label>
                            <input
                                v-model="form.completed_date"
                                type="date"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.completed_date }"
                            />
                            <div v-if="form.errors.completed_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.completed_date }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded border px-3 py-2"
                            :class="{ 'border-red-500': form.errors.description }"
                        ></textarea>
                        <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
                            {{ form.errors.description }}
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                        <button
                            type="button"
                            @click="addLineItem"
                            class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700"
                        >
                            Add Item
                        </button>
                    </div>

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(item, index) in form.line_items"
                            :key="index"
                            class="border rounded-lg p-4"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                    <select
                                        :value="item.product_id"
                                        @change="selectProduct(index, ($event.target as HTMLSelectElement).value ? parseInt(($event.target as HTMLSelectElement).value) : null)"
                                        class="w-full rounded border px-3 py-2"
                                    >
                                        <option value="">Custom Item</option>
                                        <option v-for="product in props.products" :key="product.id" :value="product.id">
                                            {{ product.name }} ({{ product.type }})
                                        </option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                                    <input
                                        v-model="item.description"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.description`] }"
                                        required
                                    />
                                    <div v-if="form.errors[`line_items.${index}.description`]" class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.description`] }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.quantity`] }"
                                        required
                                    />
                                    <div v-if="form.errors[`line_items.${index}.quantity`]" class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.quantity`] }}
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price *</label>
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full rounded border px-3 py-2"
                                        :class="{ 'border-red-500': form.errors[`line_items.${index}.unit_price`] }"
                                        required
                                    />
                                    <div v-if="form.errors[`line_items.${index}.unit_price`]" class="text-red-500 text-sm mt-1">
                                        {{ form.errors[`line_items.${index}.unit_price`] }}
                                    </div>
                                </div>

                                <div class="flex items-end">
                                    <div class="w-full">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Total</label>
                                        <div class="w-full rounded border px-3 py-2 bg-gray-50 text-gray-700">
                                            R{{ calculateLineTotal(item).toFixed(2) }}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        @click="removeLineItem(index)"
                                        class="ml-2 text-red-600 hover:text-red-800"
                                        :disabled="form.line_items.length === 1"
                                    >
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
                    </div>
                    </div>

                    <br />

                    <div class="flex items-center justify-between mb-4">
                        <button
                            type="button"
                            @click="addLineItem"
                            class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700"
                        >
                            +
                        </button>
                    </div>

                    <!-- Totals -->
                    <div class="mt-6 border-t pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Subtotal:</span>
                                    <span class="text-sm font-medium">R{{ subtotal.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Discount:</span>
                                    <span class="text-sm font-medium text-red-600">-R{{ discountAmount.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Tax ({{ form.tax_rate || 0 }}%):</span>
                                    <span class="text-sm font-medium">R{{ taxAmount.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-base font-semibold">Total:</span>
                                    <span class="text-base font-semibold">R{{ total.toFixed(2) }}</span>
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
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.notes }"
                            ></textarea>
                            <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                                {{ form.errors.notes }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Terms & Conditions</label>
                            <textarea
                                v-model="form.terms_conditions"
                                rows="3"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.terms_conditions }"
                            ></textarea>
                            <div v-if="form.errors.terms_conditions" class="text-red-500 text-sm mt-1">
                                {{ form.errors.terms_conditions }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="jobcards.show(props.jobcard.id).url"
                        class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Updating...' : 'Update Jobcard' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import jobcards from '@/routes/jobcards';

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

interface LineItem {
    id?: number;
    product_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
}

interface Jobcard {
    id: number;
    job_number: string;
    customer_id: number;
    title: string;
    description: string | null;
    status: string;
    start_date: string | null;
    due_date: string | null;
    completed_date: string | null;
    tax_rate: number;
    notes: string | null;
    terms_conditions: string | null;
    line_items: LineItem[];
}

interface Props {
    jobcard: Jobcard;
    customers: Customer[];
    products: Product[];
    currentCompany: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();

const form = useForm({
    customer_id: props.jobcard.customer_id,
    title: props.jobcard.title,
    description: props.jobcard.description || '',
    status: props.jobcard.status,
    start_date: props.jobcard.start_date ? new Date(props.jobcard.start_date).toISOString().split('T')[0] : '',
    due_date: props.jobcard.due_date ? new Date(props.jobcard.due_date).toISOString().split('T')[0] : '',
    completed_date: props.jobcard.completed_date ? new Date(props.jobcard.completed_date).toISOString().split('T')[0] : '',
    tax_rate: props.jobcard.tax_rate,
    discount_amount: props.jobcard.discount_amount || 0,
    discount_percentage: props.jobcard.discount_percentage || 0,
    notes: props.jobcard.notes || '',
    terms_conditions: props.jobcard.terms_conditions || '',
    line_items: props.jobcard.line_items.map(item => ({
        id: item.id,
        product_id: item.product_id,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount_amount: (item as any).discount_amount || 0,
        discount_percentage: (item as any).discount_percentage || 0,
    })),
});

// Update form discount_amount when line item discounts change
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

const addLineItem = () => {
    form.line_items.push({
        id: undefined,
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
};

const selectProduct = (index: number, productId: number | null) => {
    const item = form.line_items[index];
    if (!item) return;
    
    item.product_id = productId;
    
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
};

const calculateLineTotal = (item: LineItem) => {
    const quantity = Number(item.quantity) || 0;
    const unitPrice = Number(item.unit_price) || 0;
    const discountAmount = Number(item.discount_amount) || 0;
    const discountPercentage = Number(item.discount_percentage) || 0;
    
    const subtotal = quantity * unitPrice;
    
    // Apply discount: percentage takes precedence over amount
    let finalDiscount = discountAmount;
    if (discountPercentage > 0) {
        finalDiscount = subtotal * (discountPercentage / 100);
    }
    
    return Math.max(0, subtotal - finalDiscount);
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
};

// Calculate subtotal before discounts
const subtotalBeforeDiscount = computed(() => {
    if (!form.line_items || form.line_items.length === 0) return 0;
    const result = form.line_items.reduce((sum, item) => {
        if (!item) return sum;
        const quantity = Number(item.quantity) || 0;
        const unitPrice = Number(item.unit_price) || 0;
        return sum + (quantity * unitPrice);
    }, 0);
    return Number(result) || 0;
});

// Calculate total discount from line items
const lineItemDiscountsTotal = computed(() => {
    if (!form.line_items || form.line_items.length === 0) return 0;
    return form.line_items.reduce((sum, item) => {
        if (!item) return sum;
        const quantity = Number(item.quantity) || 0;
        const unitPrice = Number(item.unit_price) || 0;
        const discountAmount = Number(item.discount_amount) || 0;
        const discountPercentage = Number(item.discount_percentage) || 0;
        
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
    const rate = Number(form.tax_rate) || 0;
    // Subtotal already has discounts applied, so calculate tax directly on it
    // Round UP to 2 decimal places
    const result = subtotal.value * (rate / 100);
    return Number(Math.ceil(result * 100) / 100) || 0;
});

const total = computed(() => {
    // Subtotal already has discounts applied, so just add tax
    const result = subtotal.value + taxAmount.value;
    return Number(result) || 0;
});

const submit = () => {
    form.put(jobcards.update(props.jobcard.id).url);
};
</script>
