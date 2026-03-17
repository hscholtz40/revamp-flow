<template>
    <Head :title="`Edit ${creditNote.credit_note_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Credit Notes', href: '/credit-notes' },
        { title: creditNote.credit_note_number, href: `/credit-notes/${creditNote.id}` },
        { title: 'Edit', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Editing credit note for:</span>
                <span class="font-semibold">{{ currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Credit Note {{ creditNote.credit_note_number }}</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
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
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
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
                                    v-if="customerSearchQuery && !filteredCustomers.some(c => c.name.toLowerCase() === customerSearchQuery.toLowerCase())"
                                    @mousedown.prevent="showQuickCreateModal = true"
                                    class="px-4 py-2 bg-blue-50 hover:bg-blue-100 cursor-pointer border-b border-gray-200 flex items-center gap-2"
                                >
                                    <Plus class="w-5 h-5 text-blue-600" />
                                    <span class="text-sm font-medium text-blue-700">Quick Create: "{{ customerSearchQuery }}"</span>
                                </div>
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
                            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
                            @click.self="showQuickCreateModal = false"
                        >
                            <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl" @click.stop>
                                <h3 class="text-lg font-semibold mb-4">Quick Create Customer</h3>
                                <form @submit.prevent="quickCreateCustomer">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                            <input v-model="quickCreateForm.name" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" required />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                            <input v-model="quickCreateForm.email" type="email" class="w-full rounded-lg border border-gray-300 px-3 py-2" required />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                            <input v-model="quickCreateForm.phone" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                                        </div>
                                    </div>
                                    <div class="flex gap-3 mt-6">
                                        <button type="submit" :disabled="quickCreateForm.processing" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                                            Create
                                        </button>
                                        <button type="button" @click="showQuickCreateModal = false" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 hover:bg-gray-50">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="relative">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Link to Invoice</label>
                            <div class="relative">
                                <input
                                    v-model="invoiceSearchQuery"
                                    @input="handleInvoiceSearch"
                                    @focus="invoiceSearchFocused = true; handleInvoiceSearch()"
                                    @blur="handleInvoiceBlur"
                                    type="text"
                                    :placeholder="selectedInvoiceOption ? selectedInvoiceLabel(selectedInvoiceOption) : 'Search invoice by number or title...'"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
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
                                    <div class="text-xs text-gray-500">{{ inv.title || 'No title' }} • {{ formatCurrency(inv.total) }}</div>
                                </div>
                            </div>
                            <div v-if="form.errors.invoice_id" class="text-red-500 text-sm mt-1">{{ form.errors.invoice_id }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input v-model="form.title" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.title }" />
                            <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">{{ form.errors.title }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Credit Note Date *</label>
                            <input v-model="form.credit_note_date" type="date" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.credit_note_date }" required />
                            <div v-if="form.errors.credit_note_date" class="text-red-500 text-sm mt-1">{{ form.errors.credit_note_date }}</div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference</label>
                            <input v-model="form.reference" type="text" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.reference }" />
                            <div v-if="form.errors.reference" class="text-red-500 text-sm mt-1">{{ form.errors.reference }}</div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.description }"></textarea>
                        <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                    </div>
                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">{{ form.errors.line_items }}</div>

                    <div class="mb-4 rounded border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-800">Line Groups</h3>
                            <button type="button" @click="addLineGroup" class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                + Add Group
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(group, groupIndex) in (form as any).line_groups" :key="group.id || groupIndex" class="flex items-center gap-2">
                                <input v-model="group.name" type="text" class="w-full rounded border px-2 py-1 text-sm" />
                                <button
                                    type="button"
                                    @click="removeLineGroup(groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                    :disabled="((form as any).line_groups || []).length === 1"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_8rem_5.5rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
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
                                :key="index"
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
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Qty</label>
                                    <input v-model.number="item.quantity" type="number" min="1" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-center focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required />
                                </div>

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
                                        <span v-if="item.product_id" class="flex-shrink-0 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700 border border-blue-200" :title="item.description">
                                            <Package class="w-3 h-3" />
                                            <button type="button" @click="unlinkProduct(index)" class="ml-0.5 text-blue-400 hover:text-blue-600">&times;</button>
                                        </span>
                                    </div>
                                    <div v-if="showProductSuggestions[index] && productSuggestions(index).length > 0" class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-auto">
                                        <div v-for="product in productSuggestions(index)" :key="product.id" @mousedown.prevent="selectProductSuggestion(index, product)" class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-b-0">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    <span class="font-medium text-gray-900">{{ product.name }}</span>
                                                    <span v-if="product.sku" class="text-gray-400 ml-1 text-xs">({{ product.sku }})</span>
                                                </div>
                                                <span class="text-gray-500 text-xs ml-2">{{ formatCurrency(product.price) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.errors[`line_items.${index}.description`]" class="text-red-500 text-xs mt-0.5">{{ form.errors[`line_items.${index}.description`] }}</div>
                                </div>

                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Price</label>
                                    <input v-model.number="item.unit_price" type="number" step="0.01" class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500" required />
                                </div>

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
                                        <select :value="discountTypes[index] || 'amount'" @change="handleDiscountTypeChange(index, $event)" class="rounded-r border border-l-0 border-gray-300 bg-gray-50 px-1 py-1.5 text-xs font-medium text-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                            <option value="amount">R</option>
                                            <option value="percentage">%</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Tax</label>
                                    <select v-model="item.tax_rate_id" class="w-full rounded border border-gray-300 px-1 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                                        <option :value="null">None</option>
                                        <option v-for="tr in taxRates" :key="tr.id" :value="tr.id">
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

                                <div>
                                    <label class="block text-xs text-gray-500 mb-1 md:hidden">Total</label>
                                    <div class="text-right text-sm font-medium text-gray-700 py-1.5">
                                        {{ formatCurrency(calculateLineTotalValue(item)) }}
                                    </div>
                                </div>

                                <div class="flex items-center justify-center gap-2 md:pt-1.5">
                                    <svg class="h-5 w-5 cursor-grab rounded border border-gray-300 bg-gray-100 p-0.5 text-gray-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <circle cx="6" cy="5" r="1.2" />
                                        <circle cx="6" cy="10" r="1.2" />
                                        <circle cx="6" cy="15" r="1.2" />
                                        <circle cx="12" cy="5" r="1.2" />
                                        <circle cx="12" cy="10" r="1.2" />
                                        <circle cx="12" cy="15" r="1.2" />
                                    </svg>
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

                    <button type="button" @click="addLineItem(0)" class="mt-3 w-full rounded-lg border-2 border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors">
                        <Plus class="w-4 h-4 inline mr-1" />
                        Add Line Item
                    </button>
                </div>

                <!-- Totals -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
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
                        <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                            <span class="text-gray-600">Rounding Adjustment:</span>
                            <span class="font-medium">{{ formatCurrency(roundingAdjustment) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold border-t border-gray-200 pt-2">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="bg-white rounded-lg border border-gray-200 p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Additional Information</h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea v-model="form.notes" rows="4" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" :class="{ 'border-red-500': form.errors.notes }"></textarea>
                        <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">{{ form.errors.notes }}</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="`/credit-notes/${creditNote.id}`" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-gray-700 hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Updating...' : 'Update Credit Note' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import { Plus, X, Trash2, Package } from 'lucide-vue-next';

interface Props {
    creditNote: {
        id: number;
        credit_note_number: string;
        customer_id: number;
        customer?: { id: number; name: string } | null;
        invoice_id: number | null;
        title: string | null;
        description: string | null;
        credit_note_date: string;
        reference: string | null;
        notes: string | null;
        line_items: Array<{
            id: number;
            product_id: number | null;
            tax_rate_id: number | null;
            description: string;
            quantity: number;
            unit_price: number;
            discount_amount: number;
            discount_percentage: number;
            tax_amount: number;
            total: number;
            account_code: string | null;
            product: { id: number; name: string } | null;
            tax_rate: { id: number; name: string; rate: number } | null;
        }>;
    };
    taxRates: Array<{ id: number; name: string; rate: number; is_default_sales: boolean }>;
    defaultSalesTaxRateId: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string; is_default_sales: boolean }[];
    defaultSalesAccountId: number | null;
    currentCompany: { id: number; name: string };
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

const props = defineProps<Props>();

const creditNote = computed(() => props.creditNote);
const currentCompany = computed(() => props.currentCompany);

const rawLineItems = computed(() => (props.creditNote as any).line_items ?? (props.creditNote as any).lineItems ?? []);

const form = useForm({
    customer_id: props.creditNote.customer_id,
    invoice_id: props.creditNote.invoice_id,
    title: props.creditNote.title ?? '',
    description: props.creditNote.description ?? '',
    credit_note_date: typeof props.creditNote.credit_note_date === 'string' ? props.creditNote.credit_note_date.split('T')[0] : props.creditNote.credit_note_date,
    reference: props.creditNote.reference ?? '',
    notes: props.creditNote.notes ?? '',
    line_groups: ((props.creditNote as any).line_groups ?? (props.creditNote as any).lineGroups ?? []).map((group: any, index: number) => ({
        id: group.id,
        name: group.name || `Group ${index + 1}`,
        sort_order: Number(group.sort_order ?? index),
    })),
    line_items: rawLineItems.value.length > 0
        ? rawLineItems.value.map((li: any) => ({
              product_id: li.product_id ?? null,
              line_group_id: li.line_group_id != null ? Number(li.line_group_id) : 1,
              tax_rate_id: li.tax_rate_id ?? null,
              description: li.description ?? '',
              quantity: Number(li.quantity) || 1,
              unit_price: Number(li.unit_price) || 0,
              discount_amount: Number(li.discount_amount) || 0,
              discount_percentage: Number(li.discount_percentage) || 0,
              account_code: li.account_code ?? null,
              account_id: li.account_id ?? props.defaultSalesAccountId ?? null,
          }))
        : [
              {
                  product_id: null,
                  line_group_id: 1,
                  description: '',
                  quantity: 1,
                  unit_price: 0,
                  discount_amount: 0,
                  discount_percentage: 0,
                  account_code: null,
                  tax_rate_id: props.defaultSalesTaxRateId ?? null,
                  account_id: props.defaultSalesAccountId ?? null,
              },
          ],
});

if ((form as any).line_groups.length === 0) {
    (form as any).line_groups = [{ name: 'Items', sort_order: 0 }];
}

const selectedCustomer = ref<{ id: number; name: string } | null>(
    props.creditNote.customer_id ? { id: props.creditNote.customer_id, name: props.creditNote.customer?.name ?? '' } : null
);
const customerSearchQuery = ref(selectedCustomer.value?.name ?? '');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<CustomerOption[]>([]);
const invoiceSearchQuery = ref('');
const invoiceSearchFocused = ref(false);
const invoiceSearchResults = ref<InvoiceOption[]>([]);
const selectedInvoiceOption = ref<InvoiceOption | null>(null);
const productSearchResults = ref<Record<number, ProductOption[]>>({});
const customerSearchDebounce = ref<ReturnType<typeof setTimeout> | null>(null);
const invoiceSearchDebounce = ref<ReturnType<typeof setTimeout> | null>(null);
const productSearchDebounce = ref<Record<number, ReturnType<typeof setTimeout>>>({});
const showQuickCreateModal = ref(false);
const showProductSuggestions = ref<Record<number, boolean>>({});
const discountTypes = ref<Record<number, 'amount' | 'percentage'>>({});
const draggedItemIndex = ref<number | null>(null);
const dragOverItemIndex = ref<number | null>(null);
const dragOverGroupId = ref<number | null>(null);
const activeDragIndex = ref<number | null>(null);

const quickCreateForm = useForm({
    name: customerSearchQuery.value,
    email: '',
    phone: '',
});

const filteredInvoiceOptions = computed(() => invoiceSearchResults.value);

onMounted(() => {
    form.line_items.forEach((item, index) => {
        discountTypes.value[index] = (item.discount_percentage ?? 0) > 0 ? 'percentage' : 'amount';
    });
    if (form.invoice_id) {
        handleInvoiceSearch();
    }
});

watch(customerSearchQuery, (newQuery) => {
    if (!showQuickCreateModal.value) quickCreateForm.name = newQuery;
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
            invoiceSearchQuery.value = selectedInvoiceLabel(inv);
            if (Number(form.customer_id) !== inv.customer_id) {
                form.customer_id = inv.customer_id;
                if (inv.customer) {
                    selectedCustomer.value = { id: inv.customer.id, name: inv.customer.name };
                    customerSearchQuery.value = inv.customer.name;
                }
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
    form.customer_id = 0;
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
}

function selectedInvoiceLabel(inv: { invoice_number: string; title: string; total: number }): string {
    return `${inv.invoice_number} - ${inv.title || 'No title'} (${formatCurrency(inv.total)})`;
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
    form.invoice_id = null as any;
    invoiceSearchQuery.value = '';
    invoiceSearchFocused.value = false;
    invoiceSearchResults.value = [];
}

async function quickCreateCustomer() {
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch('/customers/quick-create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(quickCreateForm.data()),
        });
        if (res.ok) {
            const data = await res.json();
            if (data.success && data.customer) {
                selectCustomer(data.customer);
                showQuickCreateModal.value = false;
                quickCreateForm.reset();
                quickCreateForm.name = customerSearchQuery.value;
            }
        } else {
            const err = await res.json();
            if (err.errors) quickCreateForm.setError(err.errors);
        }
    } catch (e) {
        console.error(e);
    }
}

function normalizeLineItemOrder() {
    const ordered: typeof form.line_items = [];
    const groups = (form as any).line_groups as Array<{ id?: number; name: string; sort_order: number }>;
    for (let groupIndex = 0; groupIndex < groups.length; groupIndex++) {
        const groupId = getGroupValueByIndex(groupIndex);
        const groupItems = form.line_items.filter((item: any) => (item.line_group_id ?? getGroupValueByIndex(0)) === groupId);
        ordered.push(...groupItems);
    }

    const fallbackGroupId = getGroupValueByIndex(0);
    const ungroupedItems = form.line_items.filter((item: any) => {
        return !groups.some((_, idx) => (item.line_group_id ?? fallbackGroupId) === getGroupValueByIndex(idx));
    });
    ordered.push(...ungroupedItems.map((item: any) => ({ ...item, line_group_id: fallbackGroupId })));
    (form.line_items as any) = ordered;
}

const groupedLineItems = computed(() => {
    const groups = (form as any).line_groups as Array<{ id?: number; name: string; sort_order: number }>;
    return groups.map((group, groupIndex) => {
        const groupId = getGroupValueByIndex(groupIndex);
        const items = form.line_items
            .map((item, index) => ({ item, index }))
            .filter(({ item }: any) => (item.line_group_id ?? getGroupValueByIndex(0)) === groupId);
        return { group, groupIndex, groupId, items };
    });
});

function addLineItem(groupIndex = 0) {
    const nextIndex = form.line_items.length;
    form.line_items.push({
        product_id: null,
        line_group_id: getGroupValueByIndex(groupIndex),
        tax_rate_id: props.defaultSalesTaxRateId ?? null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        account_code: null,
        account_id: props.defaultSalesAccountId ?? null,
    });
    discountTypes.value[nextIndex] = 'amount';
    normalizeLineItemOrder();
}

function getGroupValueByIndex(index: number) {
    return Number((form as any).line_groups[index]?.id) || index + 1;
}

normalizeLineItemOrder();

function addLineGroup() {
    const groups = (form as any).line_groups as Array<{ name: string; sort_order: number }>;
    const nextSortOrder = groups.length;
    groups.push({
        name: `Group ${nextSortOrder + 1}`,
        sort_order: nextSortOrder,
    });
}

function removeLineGroup(index: number) {
    const groups = (form as any).line_groups as Array<{ id?: number; name: string; sort_order: number }>;
    if (groups.length <= 1) return;
    const removedGroupId = getGroupValueByIndex(index);
    groups.splice(index, 1);
    groups.forEach((group, idx) => {
        group.sort_order = idx;
    });
    form.line_items.forEach((item: any) => {
        if (item.line_group_id === removedGroupId || !item.line_group_id) {
            item.line_group_id = getGroupValueByIndex(0);
        }
    });
    normalizeLineItemOrder();
}

function removeLineItem(index: number) {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
        normalizeLineItemOrder();
    }
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
    (moved as any).line_group_id = targetGroupId;
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
    (moved as any).line_group_id = groupId;

    const fallbackGroupId = getGroupValueByIndex(0);
    const lastIndexInGroup = form.line_items.reduce((lastIndex, item: any, idx) => {
        return (item.line_group_id ?? fallbackGroupId) === groupId ? idx : lastIndex;
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

function getDiscountValue(index: number) {
    const item = form.line_items[index];
    if (!item) return 0;
    const t = discountTypes.value[index] ?? 'amount';
    return t === 'percentage' ? (item.discount_percentage ?? 0) : (item.discount_amount ?? 0);
}

function setDiscountValue(index: number, e: Event) {
    const item = form.line_items[index];
    if (!item) return;
    const val = parseFloat((e.target as HTMLInputElement).value) || 0;
    const t = discountTypes.value[index] ?? 'amount';
    if (t === 'percentage') {
        item.discount_percentage = val;
        item.discount_amount = 0;
    } else {
        item.discount_amount = val;
        item.discount_percentage = 0;
    }
}

function handleDiscountTypeChange(index: number, e: Event) {
    const t = (e.target as HTMLSelectElement).value as 'amount' | 'percentage';
    discountTypes.value[index] = t;
    const item = form.line_items[index];
    if (item) {
        item.discount_amount = 0;
        item.discount_percentage = 0;
    }
}

function calculateLineTotalValue(item: (typeof form.line_items)[0]) {
    const qty = item.quantity || 0;
    const price = item.unit_price || 0;
    const sub = qty * price;
    let disc = item.discount_amount ?? 0;
    if ((item.discount_percentage ?? 0) > 0) disc = sub * (item.discount_percentage! / 100);
    return sub - disc;
}

const subtotalBeforeDiscount = computed(() =>
    form.line_items.reduce((s, i) => s + (Number(i.quantity) || 0) * (Number(i.unit_price) || 0), 0)
);

const lineItemDiscountsTotal = computed(() =>
    form.line_items.reduce((s, i) => {
        const q = Number(i.quantity) || 0;
        const p = Number(i.unit_price) || 0;
        const sub = q * p;
        let d = Number(i.discount_amount) || 0;
        if ((Number(i.discount_percentage) || 0) > 0) d = sub * (Number(i.discount_percentage) / 100);
        return s + d;
    }, 0)
);

const subtotal = computed(() => subtotalBeforeDiscount.value - lineItemDiscountsTotal.value);
const discountAmount = computed(() => lineItemDiscountsTotal.value);

const roundCurrency = (amount: number): number => {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
};

const taxAmount = computed(() =>
    form.line_items.reduce((sum, item) => {
        const qty = Number(item.quantity) || 0;
        const price = Number(item.unit_price) || 0;
        let discAmt = Number(item.discount_amount) || 0;
        const discPct = Number(item.discount_percentage) || 0;
        let lineSub = qty * price;
        if (discPct > 0) discAmt = lineSub * (discPct / 100);
        const lineTotal = lineSub - discAmt;
        const tr = props.taxRates.find(t => t.id === item.tax_rate_id);
        if (tr) {
            const lineTax = roundCurrency(lineTotal * (tr.rate / 100));
            return roundCurrency(sum + lineTax);
        }
        return sum;
    }, 0)
);

const total = computed(() => subtotal.value + taxAmount.value);

const roundingAdjustment = computed(() =>
    form.line_items.reduce((sum, item) => {
        if ((item.description || '').trim().toLowerCase() !== 'rounding adjustment') {
            return sum;
        }
        return sum + calculateLineTotalValue(item);
    }, 0)
);

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(amount ?? 0);
}

function submit() {
    form.transform((data: any) => ({
        ...data,
        line_groups: (data.line_groups || []).map((group: any, index: number) => ({
            id: group.id,
            name: group.name,
            sort_order: index,
        })),
        line_items: data.line_items.map((item: any) => ({
            ...item,
            line_group_id: item.line_group_id ?? getGroupValueByIndex(0),
        })),
    })).put(`/credit-notes/${props.creditNote.id}`);
}
</script>
