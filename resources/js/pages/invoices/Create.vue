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
                    </div>

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <!-- Table Header -->
                    <div class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_8rem_5.5rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                        <div>Qty</div>
                        <div>Description</div>
                        <div>Price</div>
                        <div>Discount</div>
                        <div>Tax</div>
                        <div>Account</div>
                        <div class="text-right">Total</div>
                        <div></div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="(item, index) in form.line_items"
                            :key="index"
                            class="py-3 px-1"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_8rem_5.5rem_2rem] gap-2 items-start">
                                <!-- Qty -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Qty</label>
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-center focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        required
                                    />
                                </div>

                                <!-- Description / Product Search -->
                                <div class="relative">
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Description</label>
                                    <div class="flex items-center gap-1">
                                        <input
                                            v-model="item.description"
                                            @input="handleDescriptionInput(index)"
                                            @focus="showProductSuggestions[index] = true"
                                            @blur="handleDescriptionBlur(index)"
                                            type="text"
                                            placeholder="Type description or search products..."
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            :class="{ 'border-red-500': form.errors[`line_items.${index}.description`] }"
                                            required
                                        />
                                        <span
                                            v-if="item.product_id"
                                            class="flex-shrink-0 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700 border border-blue-200"
                                            :title="getProductName(item.product_id)"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                            <button type="button" @click="unlinkProduct(index)" class="ml-0.5 text-blue-400 hover:text-blue-600">&times;</button>
                                        </span>
                                    </div>
                                    <!-- Product Suggestions Dropdown -->
                                    <div
                                        v-if="showProductSuggestions[index] && productSuggestions(index).length > 0"
                                        class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-auto"
                                    >
                                        <div
                                            v-for="product in productSuggestions(index)"
                                            :key="product.id"
                                            @mousedown.prevent="selectProductSuggestion(index, product)"
                                            class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-b-0"
                                        >
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ product.name }}</span>
                                                    <span v-if="product.sku" class="text-gray-400 ml-1 text-xs">({{ product.sku }})</span>
                                                </div>
                                                <span class="text-gray-500 text-xs ml-2">R{{ product.price.toFixed(2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.errors[`line_items.${index}.description`]" class="text-red-500 text-xs mt-0.5">
                                        {{ form.errors[`line_items.${index}.description`] }}
                                    </div>
                                </div>

                                <!-- Unit Price -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Price</label>
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        required
                                    />
                                </div>

                                <!-- Discount -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Discount</label>
                                    <div class="flex">
                                        <input
                                            :value="getDiscountValue(index)"
                                            @input="setDiscountValue(index, $event)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="discountTypes[index] === 'percentage' ? 100 : undefined"
                                            class="w-full min-w-0 rounded-l border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            placeholder="0"
                                        />
                                        <select
                                            :value="discountTypes[index] || 'amount'"
                                            @change="handleDiscountTypeChange(index, $event)"
                                            class="rounded-r border border-l-0 border-gray-300 bg-gray-50 px-1 py-1.5 text-xs font-medium text-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        >
                                            <option value="amount">R</option>
                                            <option value="percentage">%</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Tax Rate -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Tax</label>
                                    <select
                                        v-model="item.tax_rate_id"
                                        class="w-full rounded border border-gray-300 px-1 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option :value="null">None</option>
                                        <option v-for="tr in props.taxRates" :key="tr.id" :value="tr.id">
                                            {{ tr.name }} ({{ tr.rate }}%)
                                        </option>
                                    </select>
                                </div>

                                <!-- Account -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Account</label>
                                    <select
                                        v-model="item.account_id"
                                        class="w-full rounded border border-gray-300 px-1 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option :value="null">None</option>
                                        <option v-for="acc in props.chartOfAccounts" :key="acc.id" :value="acc.id">
                                            {{ acc.account_code }} - {{ acc.account_name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Total -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Total</label>
                                    <div class="text-right text-sm font-medium text-gray-700 py-1.5">
                                        {{ formatCurrency(calculateLineTotalValue(item)) }}
                                    </div>
                                </div>

                                <!-- Remove -->
                                <div class="flex items-center justify-center md:pt-1.5">
                                    <button
                                        type="button"
                                        @click="removeLineItem(index)"
                                        class="text-gray-400 hover:text-red-600 transition-colors"
                                        :disabled="form.line_items.length === 1"
                                        :class="{ 'opacity-30 cursor-not-allowed': form.line_items.length === 1 }"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Serial Number Selection (preserved from original) -->
                            <div v-if="item.product_id && props.products.find(p => p.id === parseInt(item.product_id))?.track_serial_numbers" class="mt-3 ml-14 border-l-2 border-blue-200 pl-3">
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    Serial Numbers
                                    <span class="text-gray-400">(Select {{ item.quantity || 0 }})</span>
                                </label>
                                <div class="max-h-32 space-y-1 overflow-y-auto rounded border border-gray-200 p-1.5 bg-gray-50">
                                    <label
                                        v-for="serial in props.products.find(p => p.id === parseInt(item.product_id))?.serialNumbers || []"
                                        :key="serial.id"
                                        class="flex items-center gap-2 rounded px-2 py-0.5 hover:bg-white text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="serial.id"
                                            v-model="item.serial_number_ids"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            :disabled="(item.serial_number_ids?.length || 0) >= (item.quantity || 0) && !item.serial_number_ids?.includes(serial.id)"
                                        />
                                        <span class="text-gray-700">{{ serial.serial_number }}</span>
                                    </label>
                                    <div v-if="!props.products.find(p => p.id === parseInt(item.product_id))?.serialNumbers?.length" class="text-xs text-gray-400 px-1">
                                        No serial numbers available.
                                    </div>
                                </div>
                                <p class="mt-0.5 text-xs text-gray-400">
                                    Selected: {{ item.serial_number_ids?.length || 0 }} / {{ item.quantity || 0 }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Add Line Item button -->
                    <button
                        type="button"
                        @click="addLineItem"
                        class="mt-3 w-full rounded border-2 border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors"
                    >
                        + Add Line Item
                    </button>
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
                            <span class="text-gray-600">Tax:</span>
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
import { computed, watch, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import invoices from '@/routes/invoices';

interface Customer {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    account_code?: string;
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
    tax_rate_id?: number | null;
    account_id?: number | null;
}

interface Props {
    customers: Customer[];
    products: Product[];
    users: User[];
    currentCompany: Company;
    selectedCustomer?: Customer | null;
    defaultTerms?: string;
    currentUser: User;
    taxRates: { id: number; name: string; rate: number; is_default_sales: boolean }[];
    defaultSalesTaxRateId: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string; is_default_sales: boolean }[];
    defaultSalesAccountId: number | null;
}

const props = defineProps<Props>();

// Track product suggestions and discount types for each line item
const showProductSuggestions = ref<Record<number, boolean>>({});
const discountTypes = ref<Record<number, 'amount' | 'percentage'>>({});

// Customer search
const customerSearchQuery = ref('');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<Customer[]>([]);
const selectedCustomer = ref<Customer | null>(null);
const showQuickCreateModal = ref(false);
const quickCreateForm = useForm({
    name: customerSearchQuery.value || '',
    email: '',
    phone: '',
});

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
            tax_rate_id: props.defaultSalesTaxRateId || null,
            account_id: props.defaultSalesAccountId || null,
        },
    ] as LineItem[],
});

// Initialize selected customer if customer_id is set (must be after form declaration)
if (props.selectedCustomer) {
    selectedCustomer.value = props.selectedCustomer;
    customerSearchQuery.value = props.selectedCustomer.name;
} else if (form.customer_id) {
    const customer = props.customers.find(c => c.id === parseInt(form.customer_id));
    if (customer) {
        selectedCustomer.value = customer;
        customerSearchQuery.value = customer.name;
    }
}

// Update quick create form name when search query changes (moved after form declaration)
watch(customerSearchQuery, (newQuery) => {
    if (!showQuickCreateModal.value) {
        quickCreateForm.name = newQuery;
    }
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
    // Delay to allow click events on dropdown items
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
    form.title = `Invoice for ${customer.name}`;
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
    const newIndex = form.line_items.length;
    form.line_items.push({
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        total: 0,
        serial_number_ids: [] as number[],
        tax_rate_id: props.defaultSalesTaxRateId || null,
        account_id: props.defaultSalesAccountId || null,
    });
    discountTypes.value[newIndex] = 'amount';
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
};

const calculateLineTotalValue = (item: LineItem) => {
    const quantity = item.quantity || 0;
    const unitPrice = item.unit_price || 0;
    const discountAmount = item.discount_amount || 0;
    const discountPercentage = item.discount_percentage || 0;
    
    const subtotal = quantity * unitPrice;
    
    let finalDiscount = discountAmount;
    if (discountPercentage > 0) {
        finalDiscount = subtotal * (discountPercentage / 100);
    }
    
    return Math.max(0, subtotal - finalDiscount);
};

// Product suggestions based on description text
const productSuggestions = (index: number) => {
    const query = form.line_items[index]?.description?.toLowerCase() || '';
    if (query.length < 2) return [];
    return props.products.filter(product => {
        const nameMatch = product.name?.toLowerCase().includes(query);
        const skuMatch = product.sku?.toLowerCase().includes(query);
        return nameMatch || skuMatch;
    }).slice(0, 8);
};

const handleDescriptionInput = (index: number) => {
    showProductSuggestions.value[index] = true;
};

const handleDescriptionBlur = (index: number) => {
    setTimeout(() => {
        showProductSuggestions.value[index] = false;
    }, 200);
};

const selectProductSuggestion = (index: number, product: Product) => {
    const item = form.line_items[index];
    if (!item) return;
    item.product_id = product.id.toString();
    item.description = product.name;
    item.unit_price = product.price;
    if (!item.serial_number_ids) {
        item.serial_number_ids = [];
    }
    showProductSuggestions.value[index] = false;
};

const getProductName = (productId: string | null) => {
    if (!productId) return '';
    const product = props.products.find(p => p.id === parseInt(productId));
    return product ? product.name : '';
};

const unlinkProduct = (index: number) => {
    const item = form.line_items[index];
    if (item) {
        item.product_id = null;
        item.serial_number_ids = [];
    }
};

const getDiscountValue = (index: number) => {
    const item = form.line_items[index];
    if (!item) return 0;
    const type = discountTypes.value[index] || 'amount';
    return type === 'percentage' ? (item.discount_percentage || 0) : (item.discount_amount || 0);
};

const setDiscountValue = (index: number, event: Event) => {
    const item = form.line_items[index];
    if (!item) return;
    const value = parseFloat((event.target as HTMLInputElement).value) || 0;
    const type = discountTypes.value[index] || 'amount';
    if (type === 'percentage') {
        item.discount_percentage = value;
        item.discount_amount = 0;
    } else {
        item.discount_amount = value;
        item.discount_percentage = 0;
    }
};

const handleDiscountTypeChange = (index: number, event: Event) => {
    const newType = (event.target as HTMLSelectElement).value as 'amount' | 'percentage';
    discountTypes.value[index] = newType;
    const item = form.line_items[index];
    if (item) {
        item.discount_amount = 0;
        item.discount_percentage = 0;
    }
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
    return form.line_items.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.unit_price) || 0;
        let discAmt = Number(item.discount_amount) || 0;
        const discPct = Number(item.discount_percentage) || 0;
        let lineSubtotal = qty * price;
        if (discPct > 0) {
            discAmt = lineSubtotal * (discPct / 100);
        }
        const lineTotal = Math.max(0, lineSubtotal - discAmt);
        const taxRate = props.taxRates.find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            return sum + Math.ceil(lineTotal * (taxRate.rate / 100) * 100) / 100;
        }
        return sum;
    }, 0);
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
