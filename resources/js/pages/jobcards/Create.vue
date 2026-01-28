<template>
    <Head title="New Jobcard" />

    <AppLayout :breadcrumbs="[
        { title: 'Jobcards', href: jobcards.index().url },
        { title: 'Create', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Creating jobcard for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">New Jobcard</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Customer *</label>
                            <div class="relative">
                                <input
                                    v-model="customerSearchQuery"
                                    @input="handleCustomerSearch"
                                    @focus="customerSearchFocused = true"
                                    @blur="handleCustomerBlur"
                                    type="text"
                                    :placeholder="selectedCustomer ? selectedCustomer.name : 'Search customer by name, email, phone, or account code'"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.customer_id }"
                                    required
                                />
                                <button
                                    v-if="selectedCustomer"
                                    @click.prevent="clearCustomer"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Search Results Dropdown -->
                            <div
                                v-if="customerSearchFocused && (filteredCustomers.length > 0 || customerSearchQuery)"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            >
                                <!-- Quick Create Option -->
                                <div
                                    v-if="customerSearchQuery && !filteredCustomers.some(c => c.name.toLowerCase() === customerSearchQuery.toLowerCase())"
                                    @mousedown.prevent="showQuickCreateModal = true"
                                    class="px-4 py-2 bg-blue-50 hover:bg-blue-100 cursor-pointer border-b border-gray-200"
                                >
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span class="text-sm font-medium text-blue-700">Quick Create: "{{ customerSearchQuery }}"</span>
                                    </div>
                                </div>
                                
                                <!-- Customer Results -->
                                <div
                                    v-for="customer in filteredCustomers"
                                    :key="customer.id"
                                    @mousedown.prevent="selectCustomer(customer)"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                >
                                    <div class="font-medium">{{ customer.name }}</div>
                                    <div class="text-xs text-gray-500">
                                        <span v-if="customer.account_code">{{ customer.account_code }}</span>
                                        <span v-if="customer.email">
                                            <span v-if="customer.account_code"> • </span>{{ customer.email }}
                                        </span>
                                        <span v-if="customer.phone">
                                            <span v-if="customer.email || customer.account_code"> • </span>{{ customer.phone }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.customer_id }}
                            </div>
                        </div>
                        
                        <!-- Quick Create Customer Modal -->
                        <div
                            v-if="showQuickCreateModal"
                            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                            @click.self="showQuickCreateModal = false"
                        >
                            <div class="bg-white rounded-lg p-6 w-full max-w-md" @click.stop>
                                <h3 class="text-lg font-semibold mb-4">Quick Create Customer</h3>
                                <form @submit.prevent="quickCreateCustomer">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                            <input
                                                v-model="quickCreateForm.name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                            <input
                                                v-model="quickCreateForm.email"
                                                type="email"
                                                class="w-full rounded border px-3 py-2"
                                                required
                                            />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input
                                                v-model="quickCreateForm.phone"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                            />
                                        </div>
                                    </div>
                                    <div class="flex gap-3 mt-6">
                                        <button
                                            type="submit"
                                            :disabled="quickCreateForm.processing"
                                            class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            Create
                                        </button>
                                        <button
                                            type="button"
                                            @click="showQuickCreateModal = false"
                                            class="flex-1 rounded border px-4 py-2 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
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
                                <div class="relative">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                                    <div class="relative">
                                        <input 
                                            type="text"
                                            :value="getProductDisplayName(item.product_id)"
                                            @input="handleProductSearch(index, $event)"
                                            @focus="handleProductFocus(index)"
                                            @blur="handleProductBlur(index)"
                                            placeholder="Search by name or SKU..."
                                            class="w-full rounded border px-3 py-2 pr-8"
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
                                                <div class="text-sm text-gray-500">{{ product.type }}<span v-if="product.sku"> • SKU: {{ product.sku }}</span></div>
                                            </div>
                                        </div>
                                    </div>
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
                    </div><br />

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
                        :href="jobcards.index().url"
                        class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Jobcard' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import jobcards from '@/routes/jobcards';

interface Customer {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    account_code?: string;
}

interface Product {
    id: number;
    name: string;
    price: number;
    type: string;
    sku?: string | null;
}

interface LineItem {
    product_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
}

interface Props {
    customers: Customer[];
    products: Product[];
    currentCompany: {
        id: number;
        name: string;
    };
    defaultTerms?: string;
}

const props = defineProps<Props>();

// Track product search queries for each line item
const productSearchQueries = ref<Record<number, string>>({});
const productSearchFocused = ref<Record<number, boolean>>({});

const form = useForm({
    customer_id: '',
    title: '',
    description: '',
    status: 'draft',
    start_date: new Date().toISOString().split('T')[0], // Current date
    due_date: new Date().toISOString().split('T')[0], // Current date
    tax_rate: 15, // Default to 15%
    discount_amount: 0,
    discount_percentage: 0,
    notes: '',
    terms_conditions: props.defaultTerms || '',
    line_items: [
        {
            product_id: null,
            description: '',
            quantity: 1,
            unit_price: 0,
            discount_amount: 0,
            discount_percentage: 0,
        }
    ] as LineItem[],
});

// Customer search - must be declared after form
const customerSearchQuery = ref('');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<Customer[]>([]);
const selectedCustomer = ref<Customer | null>(null);
const showQuickCreateModal = ref(false);
const quickCreateForm = useForm({
    name: '',
    email: '',
    phone: '',
});

// Customer search functions
const handleCustomerSearch = async () => {
    if (!customerSearchQuery.value.trim()) {
        filteredCustomers.value = [];
        return;
    }
    
    try {
        const response = await fetch(`/customers/search?q=${encodeURIComponent(customerSearchQuery.value)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        
        if (response.ok) {
            filteredCustomers.value = await response.json();
        } else {
            filteredCustomers.value = [];
        }
    } catch (error) {
        console.error('Error searching customers:', error);
        filteredCustomers.value = [];
    }
};

const handleCustomerBlur = () => {
    setTimeout(() => {
        customerSearchFocused.value = false;
    }, 200);
};

const selectCustomer = (customer: Customer) => {
    selectedCustomer.value = customer;
    form.customer_id = customer.id.toString();
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    
    // Update title
    form.title = customer.name;
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = '';
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
};

const quickCreateCustomer = async () => {
    try {
        const response = await fetch('/customers/quick-create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(quickCreateForm.data()),
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.success && data.customer) {
                selectCustomer(data.customer);
                showQuickCreateModal.value = false;
                quickCreateForm.reset();
                quickCreateForm.name = customerSearchQuery.value;
            }
        } else {
            const errorData = await response.json();
            if (errorData.errors) {
                quickCreateForm.setError(errorData.errors);
            }
        }
    } catch (error) {
        console.error('Error creating customer:', error);
    }
};

// Update quick create form name when search query changes (moved after all declarations)
watch(customerSearchQuery, (newQuery) => {
    if (!showQuickCreateModal.value) {
        quickCreateForm.name = newQuery;
    }
}, { immediate: false });

// Watch for customer selection to update title
watch(() => form.customer_id, (newCustomerId) => {
    if (newCustomerId) {
        const customer = props.customers.find(c => c.id === parseInt(newCustomerId));
        if (customer) {
            form.title = customer.name;
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
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
};

// Filter products based on search query
const filteredProducts = (index: number) => {
    const query = productSearchQueries.value[index]?.toLowerCase() || '';
    if (!query) return [];
    
    return props.products.filter(product => {
        const nameMatch = product.name?.toLowerCase().includes(query);
        const skuMatch = product.sku?.toLowerCase().includes(query);
        return nameMatch || skuMatch;
    }).slice(0, 10); // Limit to 10 results
};

// Get display name for selected product
const getProductDisplayName = (productId: number | null | undefined) => {
    if (!productId) return '';
    const product = props.products.find(p => p.id === productId);
    if (!product) return '';
    return product.sku ? `${product.name} (${product.sku})` : `${product.name} (${product.type})`;
};

// Handle product search input
const handleProductSearch = (index: number, event: Event) => {
    const target = event.target as HTMLInputElement;
    productSearchQueries.value[index] = target.value;
    
    // If input is cleared, clear product selection
    if (!target.value) {
        clearProduct(index);
    }
};

// Handle product input focus
const handleProductFocus = (index: number) => {
    productSearchFocused.value[index] = true;
    const productId = form.line_items[index].product_id;
    if (productId) {
        // Show current product name/SKU in search
        const product = props.products.find(p => p.id === productId);
        if (product) {
            productSearchQueries.value[index] = product.sku ? `${product.name} ${product.sku}` : product.name;
        }
    }
};

// Handle product input blur (with delay to allow click on dropdown)
const handleProductBlur = (index: number) => {
    setTimeout(() => {
        productSearchFocused.value[index] = false;
        productSearchQueries.value[index] = '';
    }, 200);
};

// Select product from search results
const selectProductFromSearch = (index: number, product: Product) => {
    selectProduct(index, product.id);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
};

// Select custom item (no product)
const selectCustomItem = (index: number) => {
    selectProduct(index, null);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
};

// Clear product selection
const clearProduct = (index: number) => {
    selectProduct(index, null);
    productSearchQueries.value[index] = '';
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
    const result = form.line_items.reduce((sum, item) => {
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
    return Number(result) || 0;
});

const subtotal = computed(() => {
    const subtotalBefore = Number(subtotalBeforeDiscount.value) || 0;
    const discounts = Number(lineItemDiscountsTotal.value) || 0;
    return Number(subtotalBefore - discounts) || 0;
});

const discountAmount = computed(() => {
    // Total discount is the sum of all line item discounts
    return Number(lineItemDiscountsTotal.value) || 0;
});

const taxAmount = computed(() => {
    const rate = Number(form.tax_rate) || 0;
    // Subtotal already has discounts applied, so calculate tax directly on it
    // Round UP to 2 decimal places
    const subtotalValue = Number(subtotal.value) || 0;
    const result = subtotalValue * (rate / 100);
    return Number(Math.ceil(result * 100) / 100) || 0;
});

const total = computed(() => {
    // Subtotal already has discounts applied, so just add tax
    const subtotalValue = Number(subtotal.value) || 0;
    const taxValue = Number(taxAmount.value) || 0;
    const result = subtotalValue + taxValue;
    return Number(result) || 0;
});

// Update form discount_amount when line item discounts change
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

const submit = () => {
    form.post(jobcards.store().url);
};
</script>
