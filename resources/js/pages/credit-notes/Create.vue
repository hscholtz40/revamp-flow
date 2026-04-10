<template>
    <Head title="Create Credit Note" />

    <AppLayout :breadcrumbs="[
        { title: 'Credit Notes', href: '/credit-notes' },
        { title: 'Create Credit Note', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Creating credit note for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Create Credit Note</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
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
                                    :placeholder="selectedCustomer ? selectedCustomer.name : 'Search customer by name, email, phone...'"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.customer_id }"
                                    required
                                />
                                <button
                                    v-if="selectedCustomer"
                                    @click.prevent="clearCustomer"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <X class="w-5 h-5" />
                                </button>
                            </div>
                            <div
                                v-if="customerSearchFocused && (filteredCustomers.length > 0 || customerSearchQuery)"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto"
                            >
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
                                            <span v-if="customer.account_code"> &bull; </span>{{ customer.email }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.customer_id }}
                            </p>
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Allocate to invoices (optional)</label>
                            <div class="relative">
                                <input
                                    v-model="invoiceSearchQuery"
                                    @input="handleInvoiceSearch"
                                    @focus="invoiceSearchFocused = true; handleInvoiceSearch()"
                                    @blur="handleInvoiceBlur"
                                    type="text"
                                    :placeholder="selectedInvoiceOption ? selectedInvoiceLabel(selectedInvoiceOption) : 'Search invoice by number or title...'"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.invoice_id }"
                                />
                                <button
                                    v-if="form.invoice_id"
                                    @click.prevent="clearInvoice"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    <X class="w-5 h-5" />
                                </button>
                            </div>
                            <div
                                v-if="invoiceSearchFocused && (filteredInvoiceOptions.length > 0 || invoiceSearchQuery)"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-auto"
                            >
                                <div
                                    @mousedown.prevent="clearInvoice"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 text-sm text-gray-600"
                                >
                                    None
                                </div>
                                <div
                                    v-for="inv in filteredInvoiceOptions"
                                    :key="inv.id"
                                    @mousedown.prevent="selectInvoice(inv)"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                >
                                    <div class="font-medium">{{ inv.invoice_number }}</div>
                                    <div class="text-xs text-gray-500">{{ inv.title || 'Untitled' }} • {{ formatCurrency(inv.total) }}</div>
                                </div>
                            </div>
                            <p v-if="form.errors.invoice_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.invoice_id }}
                            </p>

                            <div class="mt-2 flex items-center justify-between gap-2">
                                <button
                                    type="button"
                                    class="rounded border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                    :disabled="!selectedInvoiceOption"
                                    @click="addAllocationFromSelectedInvoice()"
                                >
                                    Add allocation
                                </button>
                                <p v-if="form.errors.allocations" class="text-red-500 text-sm">
                                    {{ form.errors.allocations }}
                                </p>
                            </div>

                            <div v-if="(form as any).allocations?.length" class="mt-3 space-y-2">
                                <div
                                    v-for="(alloc, idx) in (form as any).allocations"
                                    :key="`${alloc.invoice_id}-${idx}`"
                                    class="flex items-center justify-between gap-3 rounded border border-gray-200 px-3 py-2"
                                >
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium text-gray-900">
                                            {{ allocationInvoiceLabel(alloc.invoice_id) }}
                                        </div>
                                        <div class="text-xs text-gray-500">Invoice ID: {{ alloc.invoice_id }}</div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model.number="alloc.amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-32 rounded border border-gray-300 px-2 py-1 text-sm"
                                            placeholder="Amount"
                                        />
                                        <button
                                            type="button"
                                            class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-600 hover:bg-gray-50"
                                            @click="removeAllocation(idx)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Note Date *</label>
                            <input
                                v-model="form.credit_note_date"
                                type="date"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.credit_note_date }"
                                required
                            />
                            <p v-if="form.errors.credit_note_date" class="text-red-500 text-sm mt-1">
                                {{ form.errors.credit_note_date }}
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title (optional)</label>
                            <input
                                v-model="form.title"
                                type="text"
                                placeholder="e.g. Credit for returned goods"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.title }"
                            />
                            <p v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                                {{ form.errors.title }}
                            </p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference (optional)</label>
                            <input
                                v-model="form.reference"
                                type="text"
                                placeholder="Internal or external reference"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.reference }"
                            />
                            <p v-if="form.errors.reference" class="text-red-500 text-sm mt-1">
                                {{ form.errors.reference }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                    </div>

                    <div class="mb-4 rounded border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-800">Line Groups</h3>
                            <button type="button" @click="addLineGroup" class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                + Add Group
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(group, groupIndex) in form.line_groups" :key="groupIndex" class="flex items-center gap-2">
                                <input v-model="group.name" type="text" class="w-full rounded border px-2 py-1 text-sm" />
                                <button
                                    type="button"
                                    @click="removeLineGroup(groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                    :disabled="form.line_groups.length === 1"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <p v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </p>

                    <!-- Table Header -->
                    <div class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_8rem_5.5rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                        <div>Qty</div>
                        <div>Description</div>
                        <div>Price</div>
                        <div>Discount</div>
                        <div>Tax</div>
                        <div>Account</div>
                        <div class="text-right">Total</div>
                        <div></div>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="groupBlock in groupedLineItems"
                            :key="groupBlock.groupId"
                            class="rounded border border-gray-200 transition-colors"
                            :class="{ 'border-blue-300 bg-blue-50/30': dragOverGroupId === groupBlock.groupId && dragOverItemIndex === null }"
                            @dragover="onDragOver"
                            @dragenter.prevent="onDragEnterGroup(groupBlock.groupId)"
                            @dragleave="onDragLeaveGroup(groupBlock.groupId)"
                            @drop="onDropInGroup(groupBlock.groupId)"
                        >
                            <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700">
                                <span>{{ groupBlock.group.name || `Group ${groupBlock.groupIndex + 1}` }}</span>
                                <button
                                    type="button"
                                    @click.stop="addLineItem(groupBlock.groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-white"
                                >
                                    + Add line item
                                </button>
                            </div>

                            <div
                                v-for="({ item, index }) in groupBlock.items"
                                :key="item._uid || index"
                                class="border-t border-gray-100 py-3 px-1 transition-colors"
                                :class="{ 'bg-blue-50/60': dragOverItemIndex === index, 'opacity-60': activeDragIndex === index }"
                                draggable="true"
                                @dragstart="onDragStart(index, $event)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver"
                                @dragenter.prevent="onDragEnterItem(index)"
                                @dragleave="onDragLeaveItem(index)"
                                @drop.stop="onDropOnItem(index, groupBlock.groupId)"
                            >
                            <div class="grid grid-cols-1 md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_8rem_5.5rem_2rem] gap-2 items-start">
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
                                            :title="item.description"
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
                                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        required
                                    />
                                </div>

                                <!-- Discount -->
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Discount</label>
                                    <div class="flex">
                                        <input
                                            :value="getDiscountDisplay(index)"
                                            @input="setDiscountValue(index, $event)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :max="discountTypes[index] === 'percentage' ? 100 : undefined"
                                            class="w-full min-w-0 rounded-l border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            placeholder="0"
                                        />
                                        <select
                                            :value="discountTypes[index] ?? 'amount'"
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
                                <div class="flex items-center justify-center gap-2 md:pt-1.5">
                                    <DragHandleIcon />
                                    <button
                                        type="button"
                                        @click="removeLineItem(index)"
                                        class="text-gray-400 hover:text-red-600 transition-colors"
                                        :disabled="form.line_items.length === 1"
                                        :class="{ 'opacity-30 cursor-not-allowed': form.line_items.length === 1 }"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>

                    <button
                        type="button"
                        @click="addLineItem(0)"
                        class="mt-3 w-full rounded-md border-2 border-dashed border-gray-300 py-2.5 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 hover:bg-blue-50/50 transition-colors flex items-center justify-center gap-2"
                    >
                        <Plus class="w-4 h-4" />
                        Add Line Item
                    </button>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Totals</h2>
                    <div class="max-w-xs ml-auto space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Discount:</span>
                            <span class="font-medium text-red-600">-{{ formatCurrency(discountTotal) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tax:</span>
                            <span class="font-medium">{{ formatCurrency(taxTotal) }}</span>
                        </div>
                        <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between text-sm">
                            <span class="text-gray-600">Rounding Adjustment:</span>
                            <span class="font-medium">{{ formatCurrency(roundingAdjustment) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold border-t border-gray-200 pt-2 mt-2">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Notes</h2>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        placeholder="Additional notes for this credit note..."
                        class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                        :class="{ 'border-red-500': form.errors.notes }"
                    />
                    <p v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                        {{ form.errors.notes }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        href="/credit-notes"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Credit Note' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { useNumberFormat } from '@/composables/useNumberFormat';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch, ref, onMounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Plus, Trash2, X } from 'lucide-vue-next';

interface Props {
    taxRates: Array<{ id: number; name: string; rate: number; is_default_sales: boolean }>;
    defaultSalesTaxRateId: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string; is_default_sales: boolean }[];
    defaultSalesAccountId: number | null;
    currentCompany: { id: number; name: string };
    selectedInvoice: {
        id: number;
        invoice_number: string;
        title?: string;
        total?: number;
        customer_id: number;
        customer?: { id: number; name: string };
        line_items?: Array<{
            id: number;
            product_id: number | null;
            tax_rate_id: number | null;
            description: string;
            quantity: number;
            unit_price: number;
            discount_amount: number;
            discount_percentage: number;
            product?: { id: number; name: string; sku: string; price: number } | null;
        }>;
    } | null;
    selectedCustomer: { id: number; name: string } | null;
}

interface CustomerOption {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    account_code?: string;
}

interface InvoiceOption {
    id: number;
    invoice_number: string;
    title: string;
    customer_id: number;
    total: number;
    customer?: { id: number; name: string };
}

interface ProductOption {
    id: number;
    name: string;
    sku: string | null;
    price: number;
}

interface LineItem {
    _uid: string;
    product_id: number | null;
    line_group_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate_id: number | null;
    account_id?: number | null;
}

interface LineGroup {
    name: string;
    sort_order: number;
}

const props = defineProps<Props>();
const { formatCurrency } = useNumberFormat();
const createLineItemUid = () =>
    `line-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;

const discountTypes = ref<Record<number, 'amount' | 'percentage'>>({});
const showProductSuggestions = ref<Record<number, boolean>>({});
const draggedItemIndex = ref<number | null>(null);
const dragOverItemIndex = ref<number | null>(null);
const dragOverGroupId = ref<number | null>(null);
const activeDragIndex = ref<number | null>(null);
const customerSearchQuery = ref('');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<CustomerOption[]>([]);
const selectedCustomer = ref<{ id: number; name: string } | null>(null);
const invoiceSearchQuery = ref('');
const invoiceSearchFocused = ref(false);
const invoiceSearchResults = ref<InvoiceOption[]>([]);
const selectedInvoiceOption = ref<InvoiceOption | null>(null);
const productSearchResults = ref<Record<number, ProductOption[]>>({});
const customerSearchDebounce = ref<ReturnType<typeof setTimeout> | null>(null);
const invoiceSearchDebounce = ref<ReturnType<typeof setTimeout> | null>(null);
const productSearchDebounce = ref<Record<number, ReturnType<typeof setTimeout>>>({});

function buildLineItemsFromInvoice(): LineItem[] {
    const inv = props.selectedInvoice;
    if (!inv) return [];

    const rawItems = (inv as any).line_items ?? (inv as any).lineItems ?? [];
    if (!rawItems.length) return [];

    return rawItems.map((li: any) => ({
        _uid: createLineItemUid(),
        product_id: li.product_id ?? null,
        line_group_id: 1,
        description: li.description ?? (li.product?.name ?? ''),
        quantity: Number(li.quantity) || 1,
        unit_price: Number(li.unit_price) || 0,
        discount_amount: Number(li.discount_amount) || 0,
        discount_percentage: Number(li.discount_percentage) || 0,
        tax_rate_id: li.tax_rate_id ?? props.defaultSalesTaxRateId ?? null,
        account_id: li.account_id ?? props.defaultSalesAccountId ?? null,
    }));
}

function defaultLineItem(): LineItem {
    return {
        _uid: createLineItemUid(),
        product_id: null,
        line_group_id: 1,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        tax_rate_id: props.defaultSalesTaxRateId ?? null,
        account_id: props.defaultSalesAccountId ?? null,
    };
}

const initialLineItems = buildLineItemsFromInvoice();

const initialCustomerId = props.selectedCustomer?.id
    ?? props.selectedInvoice?.customer_id
    ?? '';

const form = useForm({
    customer_id: initialCustomerId,
    invoice_id: props.selectedInvoice?.id ?? '',
    allocations: [] as Array<{ invoice_id: number; amount: number }>,
    credit_note_date: new Date().toISOString().split('T')[0],
    title: props.selectedInvoice ? `Credit for ${props.selectedInvoice.invoice_number}` : '',
    reference: props.selectedInvoice?.invoice_number ?? '',
    notes: '',
    line_groups: [
        { name: 'Items', sort_order: 0 },
    ] as LineGroup[],
    line_items: initialLineItems.length > 0 ? initialLineItems : [defaultLineItem()] as LineItem[],
});

onMounted(() => {
    const resolvedCustomer = props.selectedCustomer
        ?? (props.selectedInvoice?.customer as { id: number; name: string } | undefined)
        ?? null;

    if (resolvedCustomer) {
        selectedCustomer.value = resolvedCustomer;
        customerSearchQuery.value = resolvedCustomer.name;
    }

    form.line_items.forEach((item, index) => {
        discountTypes.value[index] = (item.discount_percentage ?? 0) > 0 ? 'percentage' : 'amount';
    });

    if (props.selectedInvoice) {
        selectedInvoiceOption.value = {
            id: props.selectedInvoice.id,
            invoice_number: props.selectedInvoice.invoice_number,
            title: props.selectedInvoice.title ?? `Credit for ${props.selectedInvoice.invoice_number}`,
            customer_id: props.selectedInvoice.customer_id,
            total: props.selectedInvoice.total ?? 0,
            customer: props.selectedInvoice.customer,
        };
        invoiceSearchQuery.value = selectedInvoiceLabel(selectedInvoiceOption.value);
    }
});

watch(
    () => form.invoice_id,
    (invoiceId) => {
        if (!invoiceId) {
            invoiceSearchQuery.value = '';
            selectedInvoiceOption.value = null;
            return;
        }
        const inv = selectedInvoiceOption.value;
        if (inv) {
            form.customer_id = inv.customer_id;
            invoiceSearchQuery.value = selectedInvoiceLabel(inv);
            if (inv.customer) {
                selectedCustomer.value = { id: inv.customer.id, name: inv.customer.name };
                customerSearchQuery.value = inv.customer.name;
            }
        }
    },
);

watch(
    () => form.customer_id,
    (customerId, previousCustomerId) => {
        if (!form.invoice_id) return;
        const selectedInvoice = selectedInvoiceOption.value;
        if (!selectedInvoice) return;
        if (
            previousCustomerId
            && customerId
            && Number(customerId) !== Number(previousCustomerId)
            && Number(customerId) !== selectedInvoice.customer_id
        ) {
            clearInvoice();
        }
    },
);
const filteredInvoiceOptions = computed(() => invoiceSearchResults.value);
const allocationInvoices = ref<Record<number, InvoiceOption>>({});

function handleCustomerSearch() {
    if (customerSearchDebounce.value) {
        clearTimeout(customerSearchDebounce.value);
    }
    if (!customerSearchQuery.value.trim()) {
        filteredCustomers.value = [];
        return;
    }
    customerSearchDebounce.value = setTimeout(() => {
        fetch(`/customers/search?q=${encodeURIComponent(customerSearchQuery.value)}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => (r.ok ? r.json() : []))
            .then(data => (filteredCustomers.value = data))
            .catch(() => (filteredCustomers.value = []));
    }, 250);
}

function handleCustomerBlur() {
    setTimeout(() => (customerSearchFocused.value = false), 200);
}

function selectCustomer(customer: { id: number; name: string }) {
    selectedCustomer.value = customer;
    form.customer_id = customer.id;
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
}

function clearCustomer() {
    selectedCustomer.value = null;
    form.customer_id = '' as any;
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
}

function selectedInvoiceLabel(inv: { invoice_number: string; title: string; total: number }): string {
    return `${inv.invoice_number} - ${inv.title || 'Untitled'} (${formatCurrency(inv.total)})`;
}

function handleInvoiceSearch() {
    if (invoiceSearchDebounce.value) {
        clearTimeout(invoiceSearchDebounce.value);
    }
    invoiceSearchDebounce.value = setTimeout(() => {
        const params = new URLSearchParams();
        if (invoiceSearchQuery.value.trim()) {
            params.set('q', invoiceSearchQuery.value.trim());
        }
        if (form.customer_id) {
            params.set('customer_id', String(form.customer_id));
        }
        if (form.invoice_id) {
            params.set('invoice_id', String(form.invoice_id));
        }
        fetch(`/credit-notes/search/invoices?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => (r.ok ? r.json() : []))
            .then(data => {
                invoiceSearchResults.value = data;
                if (form.invoice_id && !selectedInvoiceOption.value) {
                    const selected = (data as InvoiceOption[]).find((inv) => inv.id === Number(form.invoice_id));
                    if (selected) {
                        selectedInvoiceOption.value = selected;
                        invoiceSearchQuery.value = selectedInvoiceLabel(selected);
                    }
                }
            })
            .catch(() => (invoiceSearchResults.value = []));
    }, 250);
}

function handleInvoiceBlur() {
    setTimeout(() => (invoiceSearchFocused.value = false), 200);
}

function selectInvoice(inv: InvoiceOption) {
    selectedInvoiceOption.value = inv;
    form.invoice_id = inv.id as any;
    invoiceSearchQuery.value = selectedInvoiceLabel(inv);
    invoiceSearchFocused.value = false;
    if (inv.customer) {
        selectedCustomer.value = { id: inv.customer.id, name: inv.customer.name };
        form.customer_id = inv.customer.id;
        customerSearchQuery.value = inv.customer.name;
    }
}

function clearInvoice() {
    selectedInvoiceOption.value = null;
    form.invoice_id = '' as any;
    invoiceSearchQuery.value = '';
    invoiceSearchFocused.value = false;
    invoiceSearchResults.value = [];
}

function allocationInvoiceLabel(invoiceId: number): string {
    const inv = allocationInvoices.value[invoiceId];
    if (!inv) return `Invoice #${invoiceId}`;
    return selectedInvoiceLabel(inv);
}

function addAllocationFromSelectedInvoice() {
    const inv = selectedInvoiceOption.value;
    if (!inv) return;
    allocationInvoices.value[inv.id] = inv;
    const list = ((form as any).allocations ?? []) as Array<{ invoice_id: number; amount: number }>;
    const existing = list.find((a) => Number(a.invoice_id) === Number(inv.id));
    if (!existing) {
        list.push({ invoice_id: inv.id, amount: 0 });
        (form as any).allocations = list;
    }
    clearInvoice();
}

function removeAllocation(index: number) {
    const list = ((form as any).allocations ?? []) as Array<{ invoice_id: number; amount: number }>;
    list.splice(index, 1);
    (form as any).allocations = list;
}

function normalizeLineItemOrder() {
    const ordered: LineItem[] = [];
    for (let groupIndex = 0; groupIndex < form.line_groups.length; groupIndex++) {
        const groupId = groupIndex + 1;
        const groupItems = form.line_items.filter((item) => (item.line_group_id ?? 1) === groupId);
        ordered.push(...groupItems);
    }

    const ungroupedItems = form.line_items.filter((item) => !item.line_group_id || item.line_group_id > form.line_groups.length);
    ordered.push(...ungroupedItems.map((item) => ({ ...item, line_group_id: 1 })));
    form.line_items = ordered;
}

const groupedLineItems = computed(() =>
    form.line_groups.map((group, groupIndex) => {
        const groupId = groupIndex + 1;
        const items = form.line_items
            .map((item, index) => ({ item, index }))
            .filter(({ item }) => (item.line_group_id ?? 1) === groupId);
        return { group, groupIndex, groupId, items };
    })
);

function addLineItem(groupIndex = 0) {
    const idx = form.line_items.length;
    form.line_items.push({ ...defaultLineItem(), _uid: createLineItemUid(), line_group_id: groupIndex + 1 });
    discountTypes.value[idx] = 'amount';
    normalizeLineItemOrder();
}

function addLineGroup() {
    const nextSortOrder = form.line_groups.length;
    form.line_groups.push({
        name: `Group ${nextSortOrder + 1}`,
        sort_order: nextSortOrder,
    });
}

function removeLineGroup(index: number) {
    if (form.line_groups.length <= 1) return;
    const removedGroupId = index + 1;
    form.line_groups.splice(index, 1);
    form.line_groups.forEach((group, idx) => {
        group.sort_order = idx;
    });
    form.line_items.forEach((item) => {
        if (item.line_group_id === removedGroupId || !item.line_group_id) {
            item.line_group_id = 1;
        } else if (item.line_group_id > removedGroupId) {
            item.line_group_id -= 1;
        }
    });
    normalizeLineItemOrder();
}

function removeLineItem(index: number) {
    if (form.line_items.length <= 1) return;
    form.line_items.splice(index, 1);
    normalizeLineItemOrder();
    const next: Record<number, 'amount' | 'percentage'> = {};
    Object.entries(discountTypes.value).forEach(([k, v]) => {
        const i = parseInt(k);
        if (i < index) next[i] = v;
        if (i > index) next[i - 1] = v;
    });
    discountTypes.value = next;
}

function onDragStart(itemIndex: number, event: DragEvent) {
    draggedItemIndex.value = itemIndex;
    activeDragIndex.value = itemIndex;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(itemIndex));
        const preview = document.createElement('div');
        preview.textContent = 'Moving line item';
        preview.className = 'pointer-events-none rounded border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 shadow';
        document.body.appendChild(preview);
        event.dataTransfer.setDragImage(preview, 10, 10);
        requestAnimationFrame(() => preview.remove());
    }
}

function onDragEnd() {
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

function onDragOver(event: DragEvent) {
    event.preventDefault();
}

function onDragEnterItem(itemIndex: number) {
    dragOverItemIndex.value = itemIndex;
    dragOverGroupId.value = null;
}

function onDragLeaveItem(itemIndex: number) {
    if (dragOverItemIndex.value === itemIndex) {
        dragOverItemIndex.value = null;
    }
}

function onDragEnterGroup(groupId: number) {
    dragOverGroupId.value = groupId;
}

function onDragLeaveGroup(groupId: number) {
    if (dragOverGroupId.value === groupId) {
        dragOverGroupId.value = null;
    }
}

function moveItem(sourceIndex: number, targetIndex: number, targetGroupId: number) {
    if (sourceIndex === targetIndex || sourceIndex < 0 || targetIndex < 0) return;
    const moved = form.line_items[sourceIndex];
    if (!moved) return;

    form.line_items.splice(sourceIndex, 1);
    moved.line_group_id = targetGroupId;
    const adjustedTarget = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
    form.line_items.splice(adjustedTarget, 0, moved);
    normalizeLineItemOrder();
}

function onDropOnItem(targetItemIndex: number, targetGroupId: number) {
    if (draggedItemIndex.value === null) return;
    moveItem(draggedItemIndex.value, targetItemIndex, targetGroupId);
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

function onDropInGroup(groupId: number) {
    if (draggedItemIndex.value === null) return;

    const sourceIndex = draggedItemIndex.value;
    const moved = form.line_items[sourceIndex];
    if (!moved) {
        draggedItemIndex.value = null;
        return;
    }

    form.line_items.splice(sourceIndex, 1);
    moved.line_group_id = groupId;

    const lastIndexInGroup = form.line_items.reduce((lastIndex, item, idx) => {
        return (item.line_group_id ?? 1) === groupId ? idx : lastIndex;
    }, -1);
    const insertIndex = lastIndexInGroup >= 0 ? lastIndexInGroup + 1 : form.line_items.length;
    form.line_items.splice(insertIndex, 0, moved);
    normalizeLineItemOrder();
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

function productSuggestions(index: number) {
    return productSearchResults.value[index] ?? [];
}

function handleDescriptionInput(index: number) {
    showProductSuggestions.value[index] = true;
    const query = form.line_items[index]?.description || '';
    if (productSearchDebounce.value[index]) {
        clearTimeout(productSearchDebounce.value[index]);
    }
    productSearchDebounce.value[index] = setTimeout(() => {
        if (!query.trim()) {
            productSearchResults.value[index] = [];
            return;
        }
        fetch(`/credit-notes/search/products?q=${encodeURIComponent(query.trim())}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => (r.ok ? r.json() : []))
            .then(data => (productSearchResults.value[index] = data))
            .catch(() => (productSearchResults.value[index] = []));
    }, 250);
}

function handleDescriptionBlur(index: number) {
    setTimeout(() => (showProductSuggestions.value[index] = false), 200);
}

function selectProductSuggestion(index: number, product: ProductOption) {
    const item = form.line_items[index];
    if (!item) return;
    item.product_id = product.id;
    item.description = product.name;
    item.unit_price = product.price;
    showProductSuggestions.value[index] = false;
    productSearchResults.value[index] = [];
}

function unlinkProduct(index: number) {
    const item = form.line_items[index];
    if (item) item.product_id = null;
}

function getDiscountDisplay(index: number): number {
    const item = form.line_items[index];
    if (!item) return 0;
    const type = discountTypes.value[index] ?? 'amount';
    return type === 'percentage' ? (item.discount_percentage ?? 0) : (item.discount_amount ?? 0);
}

function setDiscountValue(index: number, event: Event) {
    const item = form.line_items[index];
    if (!item) return;
    const value = parseFloat((event.target as HTMLInputElement).value) || 0;
    const type = discountTypes.value[index] ?? 'amount';
    if (type === 'percentage') {
        item.discount_percentage = value;
        item.discount_amount = 0;
    } else {
        item.discount_amount = value;
        item.discount_percentage = 0;
    }
}

function handleDiscountTypeChange(index: number, event: Event) {
    const newType = (event.target as HTMLSelectElement).value as 'amount' | 'percentage';
    discountTypes.value[index] = newType;
    const item = form.line_items[index];
    if (item) {
        item.discount_amount = 0;
        item.discount_percentage = 0;
    }
}

function calculateLineTotalValue(item: LineItem): number {
    const qty = item.quantity || 0;
    const price = item.unit_price || 0;
    const sub = qty * price;
    let discount = item.discount_amount || 0;
    if ((item.discount_percentage ?? 0) > 0) {
        discount = sub * (item.discount_percentage! / 100);
    }
    return sub - discount;
}

const subtotalAfterDiscount = computed(() => {
    return form.line_items.reduce((sum, item) => sum + calculateLineTotalValue(item), 0);
});

const discountTotal = computed(() => {
    return form.line_items.reduce((sum, item) => {
        const qty = item.quantity || 0;
        const price = item.unit_price || 0;
        let discount = item.discount_amount || 0;
        if ((item.discount_percentage ?? 0) > 0) {
            discount = (qty * price) * (item.discount_percentage! / 100);
        }
        return sum + discount;
    }, 0);
});

const subtotal = computed(() => subtotalAfterDiscount.value);

const roundCurrency = (amount: number): number => {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
};

const taxTotal = computed(() => {
    return form.line_items.reduce((sum, item) => {
        const lineTotal = calculateLineTotalValue(item);
        const taxRate = props.taxRates.find((tr) => tr.id === item.tax_rate_id);
        if (taxRate) {
            const lineTax = roundCurrency(lineTotal * (taxRate.rate / 100));
            return roundCurrency(sum + lineTax);
        }
        return sum;
    }, 0);
});

const total = computed(() => subtotal.value + taxTotal.value);

const roundingAdjustment = computed(() => {
    return form.line_items.reduce((sum, item) => {
        if ((item.description || '').trim().toLowerCase() !== 'rounding adjustment') {
            return sum;
        }
        return sum + calculateLineTotalValue(item);
    }, 0);
});

function submit() {
    form.transform((data) => ({
        ...data,
        line_groups: data.line_groups.map((group, index) => ({
            name: group.name,
            sort_order: index,
        })),
        line_items: data.line_items.map((item) => {
            const { _uid, ...rest } = item as LineItem;
            return {
                ...rest,
                line_group_id: item.line_group_id ?? 1,
            };
        }),
    })).post('/credit-notes');
}
</script>
