<template>

    <Head title="New Quote" />

    <AppLayout :breadcrumbs="[
        { title: 'Quotes', href: quotes.index().url },
        { title: 'Create', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Creating quote for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">New Quote</h1>
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
                            <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.title }" required />
                            <div v-if="form.errors.title" class="text-red-500 text-sm mt-1">
                                {{ form.errors.title }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Order Number</label>
                            <input
                                v-model="form.order_number"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.order_number }"
                            />
                            <div v-if="form.errors.order_number" class="text-red-500 text-sm mt-1">
                                {{ form.errors.order_number }}
                            </div>
                        </div>

                        <div v-if="form.customer_id">
                            <ContactSelector
                                v-model="form.contact_id"
                                :customer-id="form.customer_id ? parseInt(form.customer_id) : null"
                                label="Contact"
                                :error="form.errors.contact_id"
                                @select="onContactSelect"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.email }"
                            />
                            <div v-if="form.errors.email" class="text-red-500 text-sm mt-1">
                                {{ form.errors.email }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.phone }"
                            />
                            <div v-if="form.errors.phone" class="text-red-500 text-sm mt-1">
                                {{ form.errors.phone }}
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
                    </div>

                    <div class="mb-4 rounded border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-800">Line Groups</h3>
                            <button
                                type="button"
                                @click="addLineGroup"
                                class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                            >
                                + Add Group
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="(group, groupIndex) in form.line_groups"
                                :key="groupIndex"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="group.name"
                                    type="text"
                                    class="w-full rounded border px-2 py-1 text-sm"
                                    placeholder="Group name"
                                />
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

                    <div v-if="form.errors.line_items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.line_items }}
                    </div>

                    <!-- Table Header -->
                    <div class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_8rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
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
                            v-for="groupBlock in visibleGroupedLineItems"
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
                                v-for="groupedItem in groupBlock.items"
                                :key="groupedItem.item._uid || groupedItem.itemIndex"
                                class="grid grid-cols-1 md:grid-cols-[3.5rem_1fr_6.5rem_9rem_8rem_5.5rem_8rem_2rem] gap-2 items-start py-3 px-1 border-t border-gray-100 transition-colors"
                                :class="{ 'bg-blue-50/60': dragOverItemIndex === groupedItem.itemIndex, 'opacity-60': activeDragIndex === groupedItem.itemIndex }"
                                draggable="true"
                                @dragstart="onDragStart(groupedItem.itemIndex, $event)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver"
                                @dragenter.prevent="onDragEnterItem(groupedItem.itemIndex)"
                                @dragleave="onDragLeaveItem(groupedItem.itemIndex)"
                                @drop.stop="onDropOnItem(groupedItem.itemIndex, groupBlock.groupId)"
                            >
                            <template v-if="groupedItem.item">
                            <!-- Qty -->
                            <div>
                                <label class="block text-xs text-gray-500 mb-1 md:hidden">Qty</label>
                                <input
                                    v-model.number="groupedItem.item.quantity"
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
                                        v-model="groupedItem.item.description"
                                        @input="handleDescriptionInput(groupedItem.itemIndex)"
                                        @focus="showProductSuggestions[groupedItem.itemIndex] = true"
                                        @blur="handleDescriptionBlur(groupedItem.itemIndex)"
                                        type="text"
                                        placeholder="Type description or search products..."
                                        class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        :class="{ 'border-red-500': form.errors[`line_items.${groupedItem.itemIndex}.description`] }"
                                        required
                                    />
                                    <span
                                        v-if="groupedItem.item.product_id"
                                        class="flex-shrink-0 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700 border border-blue-200"
                                        :title="getProductName(groupedItem.item.product_id)"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        <button type="button" @click="unlinkProduct(groupedItem.itemIndex)" class="ml-0.5 text-blue-400 hover:text-blue-600">&times;</button>
                                    </span>
                                </div>
                                <!-- Product Suggestions Dropdown -->
                                <div
                                    v-if="showProductSuggestions[groupedItem.itemIndex] && productSuggestions(groupedItem.itemIndex).length > 0"
                                    class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-auto"
                                >
                                    <div
                                        v-for="product in productSuggestions(groupedItem.itemIndex)"
                                        :key="product.id"
                                        @mousedown.prevent="selectProductSuggestion(groupedItem.itemIndex, product)"
                                        class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-b-0"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="font-medium text-gray-900">{{ product.name }}</span>
                                                <span v-if="product.sku" class="text-gray-400 ml-1 text-xs">({{ product.sku }})</span>
                                            </div>
                                            <span class="text-gray-500 text-xs ml-2">R{{ Number(product.price || 0).toFixed(2) }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="form.errors[`line_items.${groupedItem.itemIndex}.description`]" class="text-red-500 text-xs mt-0.5">
                                    {{ form.errors[`line_items.${groupedItem.itemIndex}.description`] }}
                                </div>
                            </div>

                            <!-- Unit Price -->
                            <div>
                                <label class="block text-xs text-gray-500 mb-1 md:hidden">Price</label>
                                <input
                                    v-model.number="groupedItem.item.unit_price"
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
                                        :value="getDiscountValue(groupedItem.itemIndex)"
                                        @input="setDiscountValue(groupedItem.itemIndex, $event)"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        :max="discountTypes[groupedItem.itemIndex] === 'percentage' ? 100 : undefined"
                                        class="w-full min-w-0 rounded-l border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        placeholder="0"
                                    />
                                    <select
                                        :value="discountTypes[groupedItem.itemIndex] || 'amount'"
                                        @change="handleDiscountTypeChange(groupedItem.itemIndex, $event)"
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
                                    v-model="groupedItem.item.tax_rate_id"
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
                                    v-model="groupedItem.item.account_id"
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
                                    R{{ calculateLineTotalValue(groupedItem.item).toFixed(2) }}
                                </div>
                            </div>

                            <!-- Remove -->
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
                                    @click="removeLineItem(groupedItem.itemIndex)"
                                    class="text-gray-400 hover:text-red-600 transition-colors"
                                    :disabled="form.line_items.length === 1"
                                    :class="{ 'opacity-30 cursor-not-allowed': form.line_items.length === 1 }"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                            </template>
                        </div>
                    </div>
                    </div>

                    <!-- Add Line Item button -->
                    <button
                        type="button"
                        @click="addLineItem(0)"
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
                                    <span class="text-sm font-medium">R{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div v-if="discountAmount > 0" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Discount:</span>
                                    <span class="text-sm font-medium text-red-600">-R{{ formatCurrency(discountAmount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Tax:</span>
                                    <span class="text-sm font-medium">R{{ formatCurrency(taxAmount) }}</span>
                                </div>
                                <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Rounding Adjustment:</span>
                                    <span class="text-sm font-medium">R{{ formatCurrency(roundingAdjustment) }}</span>
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
                                    :value="formatCurrency(discountAmount)"
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
                                    :value="formatCurrency(subtotalBeforeDiscount)"
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
                    <Link :href="quotes.index().url" class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                    Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Quote' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ContactSelector from '@/components/ContactSelector.vue';
import { matchesProductSearch } from '@/composables/productSearch';
import { Head, Link, useForm } from '@inertiajs/vue3';
import quotes from '@/routes/quotes';
import { computed, ref, watch } from 'vue';

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

interface Company {
    id: number;
    name: string;
}

interface LineItem {
    _uid: string;
    product_id: string | null;
    line_group_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate_id?: number | null;
    account_id?: number | null;
    total: number;
}

interface LineGroup {
    id?: number;
    name: string;
    sort_order: number;
}

const props = defineProps<{
    customers: Customer[];
    products: Product[];
    currentCompany: Company;
    defaultTerms?: string;
    defaultSalesCustomerId?: number | null;
    taxRates: { id: number; name: string; rate: number; is_default_sales: boolean }[];
    defaultSalesTaxRateId: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string; is_default_sales: boolean }[];
    defaultSalesAccountId: number | null;
    defaultRoundingAccountId?: number | null;
    prefill?: {
        source_type?: string;
        source_id?: number;
        customer_id?: number | null;
        contact_id?: number | null;
        email?: string | null;
        phone?: string | null;
        order_number?: string | null;
        title?: string | null;
        description?: string | null;
        status?: string | null;
        expiry_date?: string | null;
        tax_rate?: number | null;
        discount_amount?: number | null;
        discount_percentage?: number | null;
        notes?: string | null;
        terms_conditions?: string | null;
        line_groups?: { name?: string | null; sort_order?: number | null }[];
        line_items?: {
            product_id?: number | null;
            line_group_id?: number | null;
            description?: string | null;
            quantity?: number | null;
            unit_price?: number | null;
            discount_amount?: number | null;
            discount_percentage?: number | null;
            tax_rate_id?: number | null;
            account_id?: number | null;
            total?: number | null;
        }[];
    } | null;
}>();

const ROUNDING_LINE_DESCRIPTION = 'Rounding Adjustment';
const createLineItemUid = () =>
    `line-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
const showProductSuggestions = ref<Record<number, boolean>>({});
const discountTypes = ref<Record<number, 'amount' | 'percentage'>>({});
const draggedItemIndex = ref<number | null>(null);
const dragOverItemIndex = ref<number | null>(null);
const dragOverGroupId = ref<number | null>(null);
const activeDragIndex = ref<number | null>(null);

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

// Update quick create form name when search query changes
watch(customerSearchQuery, (newQuery) => {
    if (!showQuickCreateModal.value) {
        quickCreateForm.name = newQuery;
    }
});

// Calculate default expiry date (30 days from today)
const getDefaultExpiryDate = () => {
    const date = new Date();
    date.setDate(date.getDate() + 30);
    return date.toISOString().split('T')[0]; // Format as YYYY-MM-DD
};

const form = useForm({
    customer_id: props.defaultSalesCustomerId ? props.defaultSalesCustomerId.toString() : '',
    contact_id: null as number | null,
    email: '',
    phone: '',
    source_type: null as string | null,
    source_id: null as number | null,
    order_number: '',
    title: '',
    description: '',
    status: 'draft',
    expiry_date: getDefaultExpiryDate(),
    tax_rate: 15,
    discount_amount: 0,
    discount_percentage: 0,
    notes: '',
    terms_conditions: props.defaultTerms || '',
    line_groups: [
        { name: 'Items', sort_order: 0 },
    ] as LineGroup[],
    line_items: [
        {
            _uid: createLineItemUid(),
            product_id: null,
            line_group_id: 1,
            description: '',
            quantity: 1,
            unit_price: 0,
            discount_amount: 0,
            discount_percentage: 0,
            tax_rate_id: props.defaultSalesTaxRateId || null,
            account_id: props.defaultSalesAccountId || null,
            total: 0,
        }
    ] as LineItem[],
});

if (props.defaultSalesCustomerId) {
    const defaultCustomer = props.customers.find(c => c.id === props.defaultSalesCustomerId);
    if (defaultCustomer) {
        selectedCustomer.value = defaultCustomer;
        customerSearchQuery.value = defaultCustomer.name;
        form.email = defaultCustomer.email || '';
        form.phone = defaultCustomer.phone || '';
        form.title = defaultCustomer.name;
    }
}

if (props.prefill) {
    const source = props.prefill;

    form.customer_id = source.customer_id ? source.customer_id.toString() : '';
    form.contact_id = source.contact_id ?? null;
    form.email = source.email || '';
    form.phone = source.phone || '';
    form.source_type = source.source_type || null;
    form.source_id = source.source_id ?? null;
    form.order_number = source.order_number || '';
    form.title = source.title || '';
    form.description = source.description || '';
    form.status = source.status || 'draft';
    form.expiry_date = source.expiry_date || getDefaultExpiryDate();
    form.tax_rate = Number(source.tax_rate ?? form.tax_rate) || 0;
    form.discount_amount = Number(source.discount_amount ?? 0) || 0;
    form.discount_percentage = Number(source.discount_percentage ?? 0) || 0;
    form.notes = source.notes || '';
    form.terms_conditions = source.terms_conditions || form.terms_conditions;

    const prefillGroups = (source.line_groups || [])
        .map((group, index) => ({
            name: group?.name || `Group ${index + 1}`,
            sort_order: Number(group?.sort_order ?? index),
        }))
        .sort((a, b) => a.sort_order - b.sort_order);
    form.line_groups = prefillGroups.length > 0 ? prefillGroups : [{ name: 'Items', sort_order: 0 }];

    const prefillItems = (source.line_items || []).map((item) => ({
        _uid: createLineItemUid(),
        product_id: item.product_id != null ? String(item.product_id) : null,
        line_group_id: Number(item.line_group_id ?? 1) || 1,
        description: item.description || '',
        quantity: Number(item.quantity ?? 1) || 1,
        unit_price: Number(item.unit_price ?? 0) || 0,
        discount_amount: Number(item.discount_amount ?? 0) || 0,
        discount_percentage: Number(item.discount_percentage ?? 0) || 0,
        tax_rate_id: item.tax_rate_id ?? props.defaultSalesTaxRateId ?? null,
        account_id: item.account_id ?? props.defaultSalesAccountId ?? null,
        total: Number(item.total ?? 0) || 0,
    }));
    form.line_items = prefillItems.length > 0 ? prefillItems : [{
        _uid: createLineItemUid(),
        product_id: null,
        line_group_id: 1,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        tax_rate_id: props.defaultSalesTaxRateId || null,
        account_id: props.defaultSalesAccountId || null,
        total: 0,
    }];

    const prefillCustomer = props.customers.find((customer) => customer.id === Number(source.customer_id));
    if (prefillCustomer) {
        selectedCustomer.value = prefillCustomer;
        customerSearchQuery.value = prefillCustomer.name;
    }

    form.line_items.forEach((item, index) => {
        discountTypes.value[index] =
            (Number(item.discount_percentage) || 0) > 0 ? 'percentage' : 'amount';
    });
}

const normalizeLineItemOrder = () => {
    const ordered: LineItem[] = [];
    for (let groupIndex = 0; groupIndex < form.line_groups.length; groupIndex++) {
        const groupId = groupIndex + 1;
        const groupItems = form.line_items.filter((item) => (item.line_group_id ?? 1) === groupId);
        ordered.push(...groupItems);
    }

    const ungroupedItems = form.line_items.filter((item) => !item.line_group_id || item.line_group_id > form.line_groups.length);
    ordered.push(...ungroupedItems.map((item) => ({ ...item, line_group_id: 1 })));
    form.line_items = ordered;
};

const addLineItem = (groupIndex = 0) => {
    const defaultGroupId = getDefaultGroupId(groupIndex);
    form.line_items.push({
        _uid: createLineItemUid(),
        product_id: null,
        line_group_id: defaultGroupId,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
        tax_rate_id: props.defaultSalesTaxRateId || null,
        account_id: props.defaultSalesAccountId || null,
        total: 0,
    });
    normalizeLineItemOrder();
};

const getDefaultGroupId = (groupIndex = 0) => {
    if (form.line_groups.length === 0) {
        return null;
    }

    return groupIndex + 1;
};

const groupedLineItems = computed(() =>
    form.line_groups.map((group, groupIndex) => {
        const groupId = groupIndex + 1;
        const items = form.line_items
            .map((item, itemIndex) => ({ item, itemIndex }))
            .filter(({ item }) => (item.line_group_id ?? 1) === groupId);

        return { group, groupIndex, groupId, items };
    }),
);

const visibleGroupedLineItems = computed(() =>
    groupedLineItems.value.map((groupBlock) => ({
        ...groupBlock,
        items: groupBlock.items.filter(({ item }) => !isRoundingAdjustmentLine(item)),
    })),
);

const addLineGroup = () => {
    const nextSortOrder = form.line_groups.length;
    form.line_groups.push({
        name: `Group ${nextSortOrder + 1}`,
        sort_order: nextSortOrder,
    });
};

const removeLineGroup = (index: number) => {
    if (form.line_groups.length <= 1) {
        return;
    }

    const removedGroupId = index + 1;
    form.line_groups.splice(index, 1);
    form.line_groups.forEach((group, idx) => {
        group.sort_order = idx;
    });

    const fallbackGroupId = 1;
    form.line_items.forEach((item) => {
        if (item.line_group_id === removedGroupId || !item.line_group_id) {
            item.line_group_id = fallbackGroupId;
        } else if (item.line_group_id > removedGroupId) {
            item.line_group_id -= 1;
        }
    });
    normalizeLineItemOrder();
};

const removeLineItem = (index: number) => {
    if (isRoundingLineAtIndex(index)) {
        return;
    }

    const nonRoundingCount = form.line_items.filter((item) => !isRoundingAdjustmentLine(item)).length;
    if (nonRoundingCount > 1) {
        form.line_items.splice(index, 1);
        normalizeLineItemOrder();
    }
};

const onDragStart = (itemIndex: number, event: DragEvent) => {
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
};

const onDragEnd = () => {
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
};

const onDragOver = (event: DragEvent) => {
    event.preventDefault();
};

const onDragEnterItem = (itemIndex: number) => {
    dragOverItemIndex.value = itemIndex;
    dragOverGroupId.value = null;
};

const onDragLeaveItem = (itemIndex: number) => {
    if (dragOverItemIndex.value === itemIndex) {
        dragOverItemIndex.value = null;
    }
};

const onDragEnterGroup = (groupId: number) => {
    dragOverGroupId.value = groupId;
};

const onDragLeaveGroup = (groupId: number) => {
    if (dragOverGroupId.value === groupId) {
        dragOverGroupId.value = null;
    }
};

const moveItem = (sourceIndex: number, targetIndex: number, targetGroupId: number) => {
    if (sourceIndex === targetIndex || sourceIndex < 0 || targetIndex < 0) {
        return;
    }

    const moved = form.line_items[sourceIndex];
    if (!moved) {
        return;
    }

    form.line_items.splice(sourceIndex, 1);
    moved.line_group_id = targetGroupId;
    const adjustedTarget = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
    form.line_items.splice(adjustedTarget, 0, moved);
    normalizeLineItemOrder();
};

const onDropOnItem = (targetItemIndex: number, targetGroupId: number) => {
    if (draggedItemIndex.value === null) {
        return;
    }
    moveItem(draggedItemIndex.value, targetItemIndex, targetGroupId);
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
};

const onDropInGroup = (groupId: number) => {
    if (draggedItemIndex.value === null) {
        return;
    }

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
};

// Product suggestions based on description text
const productSuggestions = (index: number) => {
    const query = form.line_items[index]?.description || '';
    return props.products.filter(product => matchesProductSearch(product, query)).slice(0, 8);
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
    showProductSuggestions.value[index] = false;
};

const getProductName = (productId: string | number | null | undefined) => {
    if (!productId) return '';
    const product = props.products.find(p => p.id === parseInt(productId.toString()));
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
    
    return subtotal - finalDiscount;
};

const isRoundingAdjustmentLine = (item: LineItem | undefined): boolean => {
    if (!item) return false;
    return (item.description || '').trim().toLowerCase() === ROUNDING_LINE_DESCRIPTION.toLowerCase();
};

const isRoundingLineAtIndex = (index: number): boolean => {
    return isRoundingAdjustmentLine(form.line_items[index]);
};

// Calculate subtotal before discounts
const subtotalBeforeDiscount = computed(() => {
    return form.line_items.reduce((sum, item) => {
        if (isRoundingAdjustmentLine(item)) {
            return sum;
        }
        const quantity = item.quantity || 0;
        const unitPrice = item.unit_price || 0;
        return sum + (quantity * unitPrice);
    }, 0);
});

// Calculate total discount from line items
const lineItemDiscountsTotal = computed(() => {
    return form.line_items.reduce((sum, item) => {
        if (isRoundingAdjustmentLine(item)) {
            return sum;
        }
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

const roundCurrency = (amount: number): number => {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
};

const taxAmount = computed(() => {
    return form.line_items.reduce((sum: number, item: any) => {
        if (isRoundingAdjustmentLine(item)) {
            return sum;
        }
        const qty = Number(item.quantity) || 0;
        const price = Number(item.unit_price) || 0;
        let discAmt = Number(item.discount_amount) || 0;
        const discPct = Number(item.discount_percentage) || 0;
        let lineSubtotal = qty * price;
        if (discPct > 0) {
            discAmt = lineSubtotal * (discPct / 100);
        }
        const lineTotal = lineSubtotal - discAmt;
        const taxRate = props.taxRates.find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            const lineTax = roundCurrency(lineTotal * (taxRate.rate / 100));
            return roundCurrency(sum + lineTax);
        }
        return sum;
    }, 0);
});

const total = computed(() => {
    // Subtotal already has discounts applied; rounding is added separately.
    return subtotal.value + taxAmount.value + roundingAdjustment.value;
});

const roundingAdjustment = computed(() => {
    return roundedTargetTotal.value - baseTotalBeforeRounding.value;
});

const roundToNearestTenCents = (amount: number): number => Math.round(amount * 10) / 10;
const baseTotalBeforeRounding = computed(() => subtotal.value + taxAmount.value);
const roundedTargetTotal = computed(() => roundToNearestTenCents(baseTotalBeforeRounding.value));

const ensureRoundingAdjustmentLine = () => {
    const roundingIndex = form.line_items.findIndex((item) => isRoundingAdjustmentLine(item));
    const roundingAccountId = props.defaultRoundingAccountId ?? props.defaultSalesAccountId;

    if (!roundingAccountId) {
        if (roundingIndex >= 0) form.line_items.splice(roundingIndex, 1);
        return;
    }

    const adjustment = Math.round(roundingAdjustment.value * 100) / 100;
    if (Math.abs(adjustment) < 0.0001) {
        if (roundingIndex >= 0) form.line_items.splice(roundingIndex, 1);
        return;
    }

    const roundingLine: LineItem = {
        _uid: form.line_items[roundingIndex]?._uid || createLineItemUid(),
        product_id: null,
        line_group_id: 1,
        description: ROUNDING_LINE_DESCRIPTION,
        quantity: 1,
        unit_price: adjustment,
        discount_amount: 0,
        discount_percentage: 0,
        tax_rate_id: null,
        account_id: roundingAccountId,
        total: adjustment,
    };

    if (roundingIndex >= 0) {
        form.line_items[roundingIndex] = { ...form.line_items[roundingIndex], ...roundingLine };
    } else {
        form.line_items.push(roundingLine);
    }
};

const calculateTotals = () => {
    // This is handled by computed properties
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
    form.customer_id = customer.id.toString();
    form.contact_id = null;
    form.email = customer.email || '';
    form.phone = customer.phone || '';
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    
    // Update title
    form.title = customer.name;
};

const onContactSelect = (contact: { email?: string | null; phone?: string | null } | null) => {
    if (contact) {
        form.email = contact.email || '';
        form.phone = contact.phone || '';
    }
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = '';
    form.contact_id = null;
    form.email = '';
    form.phone = '';
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
        const customer = props.customers.find(c => c.id == parseInt(newCustomerId));
        if (customer && !props.prefill?.title) {
            form.title = customer.name;
        }
    }
});

// Update form discount_amount when line item discounts change
watch(() => lineItemDiscountsTotal.value, (newTotal) => {
    form.discount_amount = newTotal;
    form.discount_percentage = 0; // Clear percentage since we're using amount from line items
});

watch(
    () => [subtotal.value, taxAmount.value, props.defaultRoundingAccountId, props.defaultSalesAccountId, form.line_groups.length],
    () => {
        ensureRoundingAdjustmentLine();
        normalizeLineItemOrder();
    },
    { immediate: true },
);

const formatCurrency = (value: number | null | undefined) => {
    const numValue = Number(value) || 0;
    return numValue.toFixed(2);
};

const submit = () => {
    const nonRoundingItems = form.line_items.filter((item) => !isRoundingAdjustmentLine(item));
    if (nonRoundingItems.length === 0) {
        form.setError('line_items', 'At least one non-rounding line item is required.');
        return;
    }

    form.transform((data) => ({
        ...data,
        contact_id: form.contact_id ?? null,
        source_type: data.source_type ?? null,
        source_id: data.source_id ?? null,
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
    }))
        .post(quotes.store().url);
};
</script>
