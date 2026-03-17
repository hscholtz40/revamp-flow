<template>

    <Head :title="`Edit Invoice ${props.invoice.invoice_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Invoices', href: invoices.index().url },
        { title: props.invoice.invoice_number, href: invoices.show(props.invoice.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Editing invoice for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Invoice {{ props.invoice.invoice_number }}</h1>
            </div>

            <!-- Warning for completed invoices -->
            <div v-if="isCompleted && !canEditCompleted" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Invoice is Paid</h3>
                        <p class="text-sm text-yellow-700 mt-1">This invoice has been paid and cannot be edited. Contact an administrator if changes are needed.</p>
                    </div>
                </div>
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
                                    :class="{ 'border-red-500': form.errors.customer_id, 'bg-gray-100 cursor-not-allowed': !canEdit }"
                                    :disabled="!canEdit"
                                    required
                                />
                                <button
                                    v-if="selectedCustomer && canEdit"
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
                                v-if="canEdit && customerSearchFocused && (filteredCustomers.length > 0 || customerSearchQuery)"
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
                                :class="{ 'border-red-500': form.errors.salesperson_id }"
                                :disabled="!canEditSalesperson">
                                <option value="">Select a salesperson</option>
                                <option v-for="user in props.users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.salesperson_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.salesperson_id }}
                            </div>
                            <div v-if="!canEditSalesperson" class="text-gray-500 text-sm mt-1">
                                You don't have permission to edit the salesperson
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.title }" placeholder="Optional (defaults to invoice number)" />
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

                        <div v-if="form.customer_id && canEdit">
                            <ContactSelector
                                v-model="form.contact_id"
                                :customer-id="form.customer_id ? parseInt(String(form.customer_id)) : null"
                                :initial-contact="(props.invoice as any).contact ?? null"
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
                                :class="{ 'border-red-500': form.errors.due_date }" required readonly />
                            <p class="text-xs text-gray-500 mt-1">Auto-calculated from payment terms.</p>
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

                    <div class="mb-4 rounded border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-800">Line Groups</h3>
                            <button
                                type="button"
                                @click="addLineGroup"
                                class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-gray-50"
                                :disabled="!canEdit"
                            >
                                + Add Group
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="(group, groupIndex) in form.line_groups"
                                :key="group.id || groupIndex"
                                class="flex items-center gap-2"
                            >
                                <input
                                    v-model="group.name"
                                    type="text"
                                    class="w-full rounded border px-2 py-1 text-sm"
                                    :disabled="!canEdit"
                                    placeholder="Group name"
                                />
                                <button
                                    type="button"
                                    @click="removeLineGroup(groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                    :disabled="!canEdit || form.line_groups.length === 1"
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
                                    :disabled="!canEdit"
                                >
                                    + Add line item
                                </button>
                            </div>

                            <div
                                v-for="({ item, index }) in groupBlock.items"
                                :key="index"
                                class="border-t border-gray-100 py-3 px-1 transition-colors"
                                :class="{ 'bg-blue-50/60': dragOverItemIndex === index, 'opacity-60': activeDragIndex === index }"
                                :draggable="canEdit"
                                @dragstart="onDragStart(index, $event)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver"
                                @dragenter.prevent="onDragEnterItem(index)"
                                @dragleave="onDragLeaveItem(index)"
                                @drop.stop="onDropOnItem(index, groupBlock.groupId)"
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
                                        :disabled="!canEdit || isRoundingLineAtIndex(index)"
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
                                            :class="{ 'border-red-500': form.errors[`line_items.${index}.description`], 'bg-gray-100 cursor-not-allowed': !canEdit }"
                                            :disabled="!canEdit || isRoundingLineAtIndex(index)"
                                            required
                                        />
                                        <span
                                        v-if="item.product_id && !isRoundingLineAtIndex(index)"
                                            class="flex-shrink-0 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700 border border-blue-200"
                                            :title="getProductName(item.product_id)"
                                        >
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                            <button v-if="canEdit" type="button" @click="unlinkProduct(index)" class="ml-0.5 text-blue-400 hover:text-blue-600">&times;</button>
                                        </span>
                                    </div>
                                    <!-- Product Suggestions Dropdown -->
                                    <div
                                        v-if="canEdit && !isRoundingLineAtIndex(index) && showProductSuggestions[index] && productSuggestions(index).length > 0"
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
                                            <span class="text-gray-500 text-xs ml-2">R{{ Number(product.price || 0).toFixed(2) }}</span>
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
                                        :disabled="!canEdit || isRoundingLineAtIndex(index)"
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
                                            :class="{ 'bg-gray-100 cursor-not-allowed': !canEdit }"
                                            :disabled="!canEdit || isRoundingLineAtIndex(index)"
                                            placeholder="0"
                                        />
                                        <select
                                            :value="discountTypes[index] || 'amount'"
                                            @change="handleDiscountTypeChange(index, $event)"
                                            class="rounded-r border border-l-0 border-gray-300 bg-gray-50 px-1 py-1.5 text-xs font-medium text-gray-600 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            :disabled="!canEdit || isRoundingLineAtIndex(index)"
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
                                        :disabled="!canEdit || isRoundingLineAtIndex(index)"
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
                                        :disabled="!canEdit || isRoundingLineAtIndex(index)"
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
                                        :disabled="form.line_items.filter((li) => !isRoundingAdjustmentLine(li)).length === 1 || !canEdit || isRoundingLineAtIndex(index)"
                                        :class="{ 'opacity-30 cursor-not-allowed': form.line_items.filter((li) => !isRoundingAdjustmentLine(li)).length === 1 || !canEdit || isRoundingLineAtIndex(index) }"
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
                                        :class="{ 'bg-blue-50': serial.status === 'sold' }"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="serial.id"
                                            v-model="item.serial_number_ids"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            :disabled="(item.serial_number_ids?.length || 0) >= (item.quantity || 0) && !item.serial_number_ids?.includes(serial.id) || !canEdit"
                                        />
                                        <span class="text-gray-700" :class="serial.status === 'sold' ? 'font-medium' : ''">
                                            {{ serial.serial_number }}
                                            <span v-if="serial.status === 'sold'" class="text-xs text-gray-500 ml-1">(selected)</span>
                                        </span>
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
                    </div>

                    <!-- Add Line Item button -->
                    <button
                        type="button"
                        @click="addLineItem(0)"
                        :disabled="!canEdit"
                        class="mt-3 w-full rounded border-2 border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
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
                        <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                            <span class="text-gray-600">Rounding Adjustment:</span>
                            <span class="font-medium">{{ formatCurrency(roundingAdjustment) }}</span>
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Terms</label>
                            <select v-model="form.terms" class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.terms }">
                                <option v-for="term in paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                            </select>
                            <div v-if="form.errors.terms" class="text-red-500 text-sm mt-1">
                                {{ form.errors.terms }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3">
                    <Link :href="invoices.show(props.invoice.id).url"
                        class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                    Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing || !canEdit"
                        class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Updating...' : 'Update Invoice' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ContactSelector from '@/components/ContactSelector.vue';
import { matchesProductSearch } from '@/composables/productSearch';
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
    terms?: string;
}

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface Product {
    id: number;
    name: string;
    price: number;
    sku: string | null;
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
    line_group_id?: number | null;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    total: number;
    serial_number_ids?: number[];
    tax_rate_id?: number | null;
    account_id?: number | null;
    is_rounding_adjustment?: boolean;
}

interface LineGroup {
    id?: number;
    name: string;
    sort_order: number;
}

interface Invoice {
    id: number;
    invoice_number: string;
    order_number?: string | null;
    title: string;
    description?: string;
    customer_id: number;
    email?: string | null;
    phone?: string | null;
    salesperson_id?: number;
    invoice_date: string;
    due_date: string;
    tax_rate: number;
    notes?: string;
    terms?: string;
    line_items: LineItem[];
    line_groups?: LineGroup[];
    lineGroups?: LineGroup[];
}

interface Props {
    invoice: Invoice;
    customers: Customer[];
    products: Product[];
    users: User[];
    currentCompany: Company;
    canEditInvoices: boolean;
    canEditSalesperson: boolean;
    canEditCompleted: boolean;
    taxRates: { id: number; name: string; rate: number; is_default_sales: boolean }[];
    defaultSalesTaxRateId: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string; is_default_sales: boolean }[];
    defaultSalesAccountId: number | null;
    defaultRoundingAccountId: number | null;
}

const props = defineProps<Props>();
const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];
const ROUNDING_LINE_DESCRIPTION = 'Rounding Adjustment';

// Track product suggestions and discount types for each line item
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

// Initialize selected customer
const currentCustomer = props.customers.find(c => c.id === props.invoice.customer_id);
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

// Use the permission passed from backend
const canEditSalesperson = computed(() => props.canEditSalesperson);
const canEditCompleted = computed(() => props.canEditCompleted);
const canEditInvoices = computed(() => props.canEditInvoices);

// Check if invoice is completed (paid status)
const isCompleted = computed(() => props.invoice.status === 'paid');

// Check if user can edit this invoice
const canEdit = computed(() => canEditInvoices.value && (!isCompleted.value || canEditCompleted.value));

const form = useForm({
    title: props.invoice.title,
    description: props.invoice.description || '',
    customer_id: props.invoice.customer_id,
    contact_id: (props.invoice as any).contact_id ?? null,
    email: props.invoice.email || '',
    phone: props.invoice.phone || '',
    order_number: props.invoice.order_number || '',
    salesperson_id: props.invoice.salesperson_id || '',
    invoice_date: props.invoice.invoice_date ? new Date(props.invoice.invoice_date).toISOString().split('T')[0] : '',
    due_date: props.invoice.due_date ? new Date(props.invoice.due_date).toISOString().split('T')[0] : '',
    tax_rate: props.invoice.tax_rate,
    discount_amount: props.invoice.discount_amount || 0,
    discount_percentage: props.invoice.discount_percentage || 0,
    notes: props.invoice.notes || '',
    terms: props.invoice.terms || '',
    line_groups: ((props.invoice.line_groups ?? props.invoice.lineGroups ?? []) as any[]).map((group: any, index: number) => ({
        id: group.id,
        name: group.name || `Group ${index + 1}`,
        sort_order: Number(group.sort_order ?? index),
    })),
    line_items: props.invoice.line_items.map(item => ({
        product_id: item.product_id?.toString() || null,
        line_group_id: (item as any).line_group_id != null ? Number((item as any).line_group_id) : 1,
        description: item.description,
        quantity: item.quantity,
        unit_price: item.unit_price,
        discount_amount: (item as any).discount_amount || 0,
        discount_percentage: (item as any).discount_percentage || 0,
        total: item.total,
        serial_number_ids: Array.isArray((item as any).serial_number_ids) ? (item as any).serial_number_ids : [],
        tax_rate_id: (item as any).tax_rate_id || null,
        account_id: (item as any).account_id ?? null,
        is_rounding_adjustment: (item.description || '').trim().toLowerCase() === ROUNDING_LINE_DESCRIPTION.toLowerCase(),
    })) as LineItem[],
});

if (form.line_groups.length === 0) {
    form.line_groups = [{ name: 'Items', sort_order: 0 }];
}

// Initialize discount types from existing line items
props.invoice.line_items.forEach((item: any, index: number) => {
    if (item.discount_percentage && item.discount_percentage > 0) {
        discountTypes.value[index] = 'percentage';
    } else {
        discountTypes.value[index] = 'amount';
    }
});

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

const parseCustomerTermsToDays = (terms?: string) => {
    const normalized = (terms || 'COD').trim();

    if (!normalized || /^cod$/i.test(normalized)) {
        return 0;
    }

    const netDaysMatch = normalized.match(/net\s*(\d+)\s*days?/i);
    if (netDaysMatch) {
        return Number(netDaysMatch[1]) || 0;
    }

    const numericMatch = normalized.match(/(\d+)/);
    if (numericMatch) {
        return Number(numericMatch[1]) || 0;
    }

    return 0;
};

const applyDueDateFromTerms = (syncTermsFromCustomer = false) => {
    const customer = selectedCustomer.value
        || props.customers.find(c => c.id === Number(form.customer_id))
        || null;

    if (syncTermsFromCustomer) {
        form.terms = (customer?.terms || 'COD').trim() || 'COD';
    }

    const invoiceDate = form.invoice_date ? new Date(form.invoice_date) : new Date();
    if (Number.isNaN(invoiceDate.getTime())) {
        return;
    }

    const termsDays = parseCustomerTermsToDays(form.terms);
    invoiceDate.setDate(invoiceDate.getDate() + termsDays);
    form.due_date = invoiceDate.toISOString().split('T')[0];
};

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
    return subtotal.value + taxAmount.value + roundingAdjustment.value;
});

const roundToNearestTenCents = (amount: number): number => {
    return Math.round(amount * 10) / 10;
};

const isRoundingAdjustmentLine = (item: LineItem | undefined): boolean => {
    if (!item) return false;
    if (item.is_rounding_adjustment) return true;
    return (item.description || '').trim().toLowerCase() === ROUNDING_LINE_DESCRIPTION.toLowerCase();
};

const isRoundingLineAtIndex = (index: number): boolean => {
    return isRoundingAdjustmentLine(form.line_items[index]);
};

const baseTotalBeforeRounding = computed(() => subtotal.value + taxAmount.value);
const roundedTargetTotal = computed(() => roundToNearestTenCents(baseTotalBeforeRounding.value));
const roundingAdjustment = computed(() => roundedTargetTotal.value - baseTotalBeforeRounding.value);

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

const onContactSelect = (contact: { email?: string | null; phone?: string | null } | null) => {
    if (contact) {
        form.email = contact.email || '';
        form.phone = contact.phone || '';
    }
};

const selectCustomer = (customer: Customer) => {
    selectedCustomer.value = customer;
    form.customer_id = customer.id;
    form.contact_id = null;
    form.email = customer.email || '';
    form.phone = customer.phone || '';
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    
    // Update title
    form.title = `Invoice for ${customer.name}`;
    applyDueDateFromTerms(true);
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = 0;
    form.contact_id = null;
    form.email = '';
    form.phone = '';
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
    applyDueDateFromTerms(true);
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
        const customer = props.customers.find(c => c.id === parseInt(newCustomerId.toString()));
        if (customer) {
            form.title = `Invoice for ${customer.name}`;
        }
    }
    applyDueDateFromTerms(true);
});

watch(() => form.invoice_date, () => {
    applyDueDateFromTerms();
});

watch(() => form.terms, () => {
    applyDueDateFromTerms();
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

function normalizeLineItemOrder() {
    const ordered: LineItem[] = [];
    for (let groupIndex = 0; groupIndex < form.line_groups.length; groupIndex++) {
        const groupId = getGroupValueByIndex(groupIndex);
        const groupItems = form.line_items.filter((item) => (item.line_group_id ?? getGroupValueByIndex(0)) === groupId);
        ordered.push(...groupItems);
    }

    const fallbackGroupId = getGroupValueByIndex(0);
    const ungroupedItems = form.line_items.filter((item) => {
        return !form.line_groups.some((_, idx) => (item.line_group_id ?? fallbackGroupId) === getGroupValueByIndex(idx));
    });
    ordered.push(...ungroupedItems.map((item) => ({ ...item, line_group_id: fallbackGroupId })));
    form.line_items = ordered;
}

function ensureRoundingAdjustmentLine() {
    const roundingIndex = form.line_items.findIndex((item) => isRoundingAdjustmentLine(item));
    const roundingAccountId = props.defaultRoundingAccountId ?? props.defaultSalesAccountId;

    if (!roundingAccountId) {
        if (roundingIndex >= 0) {
            form.line_items.splice(roundingIndex, 1);
        }
        return;
    }

    const adjustment = Math.round(roundingAdjustment.value * 100) / 100;
    if (Math.abs(adjustment) < 0.0001) {
        if (roundingIndex >= 0) {
            form.line_items.splice(roundingIndex, 1);
        }
        return;
    }

    const roundingLine: LineItem = {
        product_id: null,
        line_group_id: Number(form.line_groups[0]?.id) || 1,
        description: ROUNDING_LINE_DESCRIPTION,
        quantity: 1,
        unit_price: adjustment,
        discount_amount: 0,
        discount_percentage: 0,
        total: adjustment,
        serial_number_ids: [],
        tax_rate_id: null,
        account_id: roundingAccountId,
        is_rounding_adjustment: true,
    };

    if (roundingIndex >= 0) {
        form.line_items[roundingIndex] = {
            ...form.line_items[roundingIndex],
            ...roundingLine,
        };
    } else {
        form.line_items.push(roundingLine);
    }
}

const groupedLineItems = computed(() =>
    form.line_groups.map((group, groupIndex) => {
        const groupId = getGroupValueByIndex(groupIndex);
        const items = form.line_items
            .map((item, index) => ({ item, index }))
            .filter(({ item }) => (item.line_group_id ?? getGroupValueByIndex(0)) === groupId);

        return { group, groupIndex, groupId, items };
    }),
);

const visibleGroupedLineItems = computed(() =>
    groupedLineItems.value.map((groupBlock) => ({
        ...groupBlock,
        items: groupBlock.items.filter(({ item }) => !isRoundingAdjustmentLine(item)),
    })),
);

const addLineItem = (groupIndex = 0) => {
    const newIndex = form.line_items.length;
    form.line_items.push({
        product_id: null,
        line_group_id: getGroupValueByIndex(groupIndex),
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
    normalizeLineItemOrder();
};

const getGroupValueByIndex = (index: number) => Number(form.line_groups[index]?.id) || index + 1;

normalizeLineItemOrder();

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

    const removedGroupId = getGroupValueByIndex(index);
    form.line_groups.splice(index, 1);
    form.line_groups.forEach((group, idx) => {
        group.sort_order = idx;
    });

    const fallbackGroupId = getGroupValueByIndex(0);
    form.line_items.forEach((item) => {
        if (item.line_group_id === removedGroupId || !item.line_group_id) {
            item.line_group_id = fallbackGroupId;
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
    if (!canEdit.value) return;
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
    if (!canEdit.value) return;
    dragOverItemIndex.value = itemIndex;
    dragOverGroupId.value = null;
};

const onDragLeaveItem = (itemIndex: number) => {
    if (dragOverItemIndex.value === itemIndex) {
        dragOverItemIndex.value = null;
    }
};

const onDragEnterGroup = (groupId: number) => {
    if (!canEdit.value) return;
    dragOverGroupId.value = groupId;
};

const onDragLeaveGroup = (groupId: number) => {
    if (dragOverGroupId.value === groupId) {
        dragOverGroupId.value = null;
    }
};

const moveItem = (sourceIndex: number, targetIndex: number, targetGroupId: number) => {
    if (sourceIndex === targetIndex || sourceIndex < 0 || targetIndex < 0) return;

    const moved = form.line_items[sourceIndex];
    if (!moved) return;

    form.line_items.splice(sourceIndex, 1);
    moved.line_group_id = targetGroupId;
    const adjustedTarget = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
    form.line_items.splice(adjustedTarget, 0, moved);
    normalizeLineItemOrder();
};

const onDropOnItem = (targetItemIndex: number, targetGroupId: number) => {
    if (!canEdit.value || draggedItemIndex.value === null) return;
    moveItem(draggedItemIndex.value, targetItemIndex, targetGroupId);
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
};

const onDropInGroup = (groupId: number) => {
    if (!canEdit.value || draggedItemIndex.value === null) return;

    const sourceIndex = draggedItemIndex.value;
    const moved = form.line_items[sourceIndex];
    if (!moved) {
        draggedItemIndex.value = null;
        return;
    }

    form.line_items.splice(sourceIndex, 1);
    moved.line_group_id = groupId;

    const fallbackGroupId = getGroupValueByIndex(0);
    const lastIndexInGroup = form.line_items.reduce((lastIndex, item, idx) => {
        return (item.line_group_id ?? fallbackGroupId) === groupId ? idx : lastIndex;
    }, -1);

    const insertIndex = lastIndexInGroup >= 0 ? lastIndexInGroup + 1 : form.line_items.length;
    form.line_items.splice(insertIndex, 0, moved);
    normalizeLineItemOrder();
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
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

const submit = () => {
    const nonRoundingItems = form.line_items.filter((item) => !isRoundingAdjustmentLine(item));
    if (nonRoundingItems.length === 0) {
        form.setError('line_items', 'At least one non-rounding line item is required.');
        return;
    }

    form.transform((data) => ({
        ...data,
        contact_id: form.contact_id ?? null,
        line_groups: data.line_groups.map((group, index) => ({
            id: group.id,
            name: group.name,
            sort_order: index,
        })),
        line_items: data.line_items.map((item) => ({
            product_id: item.product_id,
            description: item.description,
            quantity: item.quantity,
            unit_price: item.unit_price,
            discount_amount: item.discount_amount ?? 0,
            discount_percentage: item.discount_percentage ?? 0,
            tax_rate_id: item.tax_rate_id ?? null,
            account_id: item.account_id ?? null,
            serial_number_ids: item.serial_number_ids ?? [],
            line_group_id: item.line_group_id ?? getGroupValueByIndex(0),
        })),
    }))
        .put(invoices.update(props.invoice.id).url);
};
</script>
