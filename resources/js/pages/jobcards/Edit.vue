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
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                            <div class="flex gap-2 mb-2">
                                <button type="button" @click="assignmentType = 'none'; form.assigned_to_user_id = null; form.assigned_to_team_id = null"
                                    :class="assignmentType === 'none' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="rounded px-3 py-1 text-sm font-medium transition-colors">
                                    Unassigned
                                </button>
                                <button type="button" @click="assignmentType = 'user'; form.assigned_to_team_id = null"
                                    :class="assignmentType === 'user' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="rounded px-3 py-1 text-sm font-medium transition-colors">
                                    User
                                </button>
                                <button type="button" @click="assignmentType = 'team'; form.assigned_to_user_id = null"
                                    :class="assignmentType === 'team' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="rounded px-3 py-1 text-sm font-medium transition-colors">
                                    Team
                                </button>
                            </div>
                            <select
                                v-if="assignmentType === 'user'"
                                v-model="form.assigned_to_user_id"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option :value="null">Select a user...</option>
                                <option v-for="user in props.users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <select
                                v-if="assignmentType === 'team'"
                                v-model="form.assigned_to_team_id"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option :value="null">Select a team...</option>
                                <option v-for="team in props.teams" :key="team.id" :value="team.id">
                                    {{ team.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.assigned_to_user_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.assigned_to_user_id }}
                            </div>
                            <div v-if="form.errors.assigned_to_team_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.assigned_to_team_id }}
                            </div>
                        </div>

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
                    </div>

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <!-- Table Header -->
                    <div class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                        <div>Qty</div>
                        <div>Description</div>
                        <div>Price</div>
                        <div>Discount</div>
                        <div>Tax</div>
                        <div class="text-right">Total</div>
                        <div></div>
                    </div>

                    <div class="divide-y divide-gray-100">
                        <div
                            v-for="(item, index) in form.line_items"
                            :key="index"
                            class="grid grid-cols-1 md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_2rem] gap-2 items-start py-3 px-1"
                        >
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

                            <!-- Total -->
                            <div>
                                <label class="block text-xs text-gray-500 mb-1 md:hidden">Total</label>
                                <div class="text-right text-sm font-medium text-gray-700 py-1.5">
                                    R{{ calculateLineTotal(item).toFixed(2) }}
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
                    </div>

                    <!-- Add Line Item button -->
                    <button
                        type="button"
                        @click="addLineItem"
                        class="mt-3 w-full rounded border-2 border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors"
                    >
                        + Add Line Item
                    </button>

                    <!-- Totals -->
                    <div class="mt-6 border-t pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Subtotal:</span>
                                    <span class="text-sm font-medium">R{{ subtotal.toFixed(2) }}</span>
                                </div>
                                <div v-if="discountAmount > 0" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Discount:</span>
                                    <span class="text-sm font-medium text-red-600">-R{{ discountAmount.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Tax:</span>
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
import { computed, watch, ref } from 'vue';
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
    id?: number;
    product_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate_id?: number | null;
}

interface Jobcard {
    id: number;
    job_number: string;
    customer_id: number;
    assigned_to_user_id: number | null;
    assigned_to_team_id: number | null;
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

interface AppUser {
    id: number;
    name: string;
}

interface TeamOption {
    id: number;
    name: string;
}

interface Props {
    jobcard: Jobcard;
    customers: Customer[];
    products: Product[];
    users: AppUser[];
    teams: TeamOption[];
    currentCompany: {
        id: number;
        name: string;
    };
    taxRates: { id: number; name: string; rate: number; is_default_sales: boolean }[];
    defaultSalesTaxRateId: number | null;
}

const props = defineProps<Props>();

const showProductSuggestions = ref<Record<number, boolean>>({});
const discountTypes = ref<Record<number, 'amount' | 'percentage'>>({});

// Customer search
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

// Initialize selected customer
const currentCustomer = props.customers.find(c => c.id === props.jobcard.customer_id);
if (currentCustomer) {
    selectedCustomer.value = currentCustomer;
    customerSearchQuery.value = currentCustomer.name;
}

// Update quick create form name when search query changes
watch(customerSearchQuery, (newQuery) => {
    if (!showQuickCreateModal.value) {
        quickCreateForm.name = newQuery;
    }
});

const assignmentType = ref<'none' | 'user' | 'team'>(
    props.jobcard.assigned_to_team_id ? 'team' : props.jobcard.assigned_to_user_id ? 'user' : 'none'
);

const form = useForm({
    customer_id: props.jobcard.customer_id,
    assigned_to_user_id: props.jobcard.assigned_to_user_id,
    assigned_to_team_id: props.jobcard.assigned_to_team_id,
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
        tax_rate_id: (item as any).tax_rate_id || null,
    })),
});

// Initialize discount types from existing line items
props.jobcard.line_items.forEach((item: any, index: number) => {
    if (item.discount_percentage && item.discount_percentage > 0) {
        discountTypes.value[index] = 'percentage';
    } else {
        discountTypes.value[index] = 'amount';
    }
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
        tax_rate_id: props.defaultSalesTaxRateId || null,
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
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
    item.product_id = product.id;
    item.description = product.name;
    item.unit_price = product.price;
    showProductSuggestions.value[index] = false;
};

const getProductName = (productId: number | null | undefined) => {
    if (!productId) return '';
    const product = props.products.find(p => p.id === productId);
    return product ? product.name : '';
};

const unlinkProduct = (index: number) => {
    const item = form.line_items[index];
    if (item) item.product_id = null;
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
    form.customer_id = customer.id;
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    
    // Update title
    form.title = customer.name;
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = 0;
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

// Watch for customer selection to update title
watch(() => form.customer_id, (newCustomerId) => {
    if (newCustomerId) {
        const customer = props.customers.find(c => c.id === parseInt(newCustomerId.toString()));
        if (customer) {
            form.title = customer.name;
        }
    }
});

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
    return form.line_items.reduce((sum, item) => {
        const lineTotal = calculateLineTotal(item);
        const taxRate = props.taxRates.find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            return sum + Math.ceil(lineTotal * (taxRate.rate / 100) * 100) / 100;
        }
        return sum;
    }, 0);
});

const total = computed(() => {
    // Subtotal already has discounts applied, so just add tax
    const subtotalValue = Number(subtotal.value) || 0;
    const taxValue = Number(taxAmount.value) || 0;
    const result = subtotalValue + taxValue;
    return Number(result) || 0;
});

// Update form discount_amount when line item discounts change (moved after computed properties)
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

const submit = () => {
    form.put(jobcards.update(props.jobcard.id).url);
};
</script>
