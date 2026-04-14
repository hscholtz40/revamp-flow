<template>
    <Head :title="`Quote ${props.quote.quote_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Quotes', href: quotes.index().url },
        { title: props.quote.quote_number, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Status Bar -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Status Management</h2>
                    <p class="text-sm text-gray-600">Update quote status and track progress</p>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">Current Status:</span>
                            <div class="flex items-center gap-2">
                                <template v-if="hasQuotesEdit">
                                    <button
                                        v-for="status in statusOptions"
                                        :key="status.value"
                                        @click="updateStatus(status.value)"
                                        :class="[
                                            'px-3 py-1 text-sm font-medium rounded-md transition-colors',
                                            props.quote.status === status.value
                                                ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'
                                        ]"
                                    >
                                        {{ status.label }}
                                    </button>
                                </template>
                                <span
                                    v-else
                                    :class="getStatusBadgeClass(props.quote.status)"
                                    class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                                >
                                    {{ formatStatus(props.quote.status) }}
                                </span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            Last updated: {{ formatDateTime(props.quote.updated_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.quote.quote_number }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ props.quote.title }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="getStatusBadgeClass(props.quote.status)"
                            class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                        >
                        {{ formatStatus(props.quote.status) }}
                    </span>
                        <button
                            @click="openDownloadModal"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Download PDF
                        </button>
                        <button
                            @click="showEmailModal = true"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Email
                        </button>
                        <button
                            v-if="canSignDocument"
                            @click="openSignModal"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            Sign
                        </button>
                        <button
                            v-if="hasPurchaseOrdersCreate"
                            @click="createPurchaseOrder"
                            class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2"
                        >
                            Create PO
                        </button>
                        <button
                            v-if="hasQuotesEdit && !props.convertedJobcardId"
                            @click="convertToJobcard"
                            class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                        >
                            Convert to Jobcard
                        </button>
                        <Link
                            v-else-if="props.convertedJobcardId"
                            :href="jobcards.show(props.convertedJobcardId).url"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            View Jobcard
                        </Link>
                        <button
                            v-if="hasQuotesEdit && !props.quote.invoice_id"
                            @click="convertToInvoice"
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                        >
                            Convert to Invoice
                        </button>
                        <Link
                            v-else-if="props.quote.invoice_id"
                            :href="invoices.show(props.quote.invoice_id).url"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            View Invoice
                        </Link>
                        <Link
                            v-if="canEditQuote"
                            :href="quotes.edit(props.quote.id).url"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Edit
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Customer Information -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Customer Information</h2>
                            <p class="text-sm text-gray-600">Customer details and contact information</p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Customer Name</label>
                                    <p class="mt-1 text-sm text-gray-900">
                                        <Link
                                            v-if="props.quote.customer && props.quote.customer.id"
                                            :href="customers.show(props.quote.customer.id).url"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ props.quote.customer.name }}
                                        </Link>
                                        <span v-else>{{ props.quote.customer?.name || '-' }}</span>
                                    </p>
                                </div>
                                <div v-if="(props.quote as any).contact">
                                    <label class="block text-sm font-medium text-gray-500">Contact</label>
                                    <p class="mt-1 text-sm text-gray-900">
                                        <Link
                                            :href="`/contacts/${(props.quote as any).contact.id}`"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ (props.quote as any).contact.name }}
                                        </Link>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Email</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.email || props.quote.customer?.email || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.phone || props.quote.customer?.phone || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Address</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.customer?.address || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div v-if="props.quote.description" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Description</h2>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ props.quote.description }}</p>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                            <p class="text-sm text-gray-600">Products and services included in this quote</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Qty</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Price</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Discount</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Tax</th>
                                        <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="group in groupedVisibleQuoteLineItems" :key="`group-${group.groupId}`">
                                        <tr class="bg-gray-100">
                                            <td colspan="6" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700">
                                                {{ group.groupName }}
                                            </td>
                                        </tr>
                                        <tr v-for="item in group.items" :key="item.id">
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-center">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-900">
                                            <template v-if="item.product && item.product.id">
                                                <Link
                                                    :href="products.show(item.product.id).url"
                                                    class="text-blue-600 hover:text-blue-800 hover:underline"
                                                >
                                                    {{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}
                                                </Link>
                                            </template>
                                            <template v-else-if="item.product_id">
                                                <Link
                                                    :href="products.show(item.product_id).url"
                                                    class="text-blue-600 hover:text-blue-800 hover:underline"
                                                >
                                                    {{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}
                                                </Link>
                                            </template>
                                            <template v-else>
                                                <span>{{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}</span>
                                            </template>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                            {{ item.formatted_unit_price }}
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                            <span v-if="item.discount_percentage && item.discount_percentage > 0" class="text-red-600">{{ item.discount_percentage }}%</span>
                                            <span v-else-if="item.discount_amount && item.discount_amount > 0" class="text-red-600">R{{ Number(item.discount_amount).toFixed(2) }}</span>
                                            <span v-else class="text-gray-400">&mdash;</span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                            <span v-if="item.tax_rate" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700">{{ item.tax_rate.name }} ({{ item.tax_rate.rate }}%)</span>
                                            <span v-else class="text-gray-400">&mdash;</span>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                            {{ item.formatted_total }}
                                        </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes and Terms -->
                    <div v-if="props.quote.notes || props.quote.terms_conditions" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Additional Information</h2>
                            <p class="text-sm text-gray-600">Notes and terms & conditions</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div v-if="props.quote.notes">
                                <label class="block text-sm font-medium text-gray-500">Notes</label>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ props.quote.notes }}</p>
                            </div>
                            <div v-if="props.quote.terms_conditions">
                                <label class="block text-sm font-medium text-gray-500">Terms & Conditions</label>
                                <p class="mt-1 text-sm text-gray-900 whitespace-pre-wrap">{{ props.quote.terms_conditions }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.signatures && props.signatures.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Signatures</h2>
                            <p class="text-sm text-gray-600">Captured signatures for this quote</p>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="signature in props.signatures" :key="signature.id" class="rounded border border-gray-200 p-3">
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
                                    <span class="font-medium text-gray-700">{{ signature.signer_name }}</span>
                                    <span>{{ signature.signed_at ? formatDateTime(signature.signed_at) : '-' }}</span>
                                </div>
                                <img
                                    v-if="signature.signature_url"
                                    :src="signature.signature_url"
                                    alt="Signature"
                                    class="h-24 w-full object-contain bg-white border border-gray-100 rounded"
                                />
                                <p v-if="signature.user_name" class="mt-2 text-xs text-gray-500">Captured by {{ signature.user_name }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quote Details -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Quote Details</h2>
                            <p class="text-sm text-gray-600">Key information about this quote</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Quote Number</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.quote.quote_number }}</p>
                            </div>
                            <div v-if="props.quote.order_number">
                                <label class="block text-sm font-medium text-gray-500">Order Number</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.quote.order_number }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.quote.created_at) }}</p>
                            </div>
                            <div v-if="props.quote.expiry_date">
                                <label class="block text-sm font-medium text-gray-500">Expiry Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.quote.expiry_date) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.quote.source || props.quote.source_type" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Source</h2>
                            <p class="text-sm text-gray-600">Original document this quote was created from</p>
                        </div>
                        <div class="p-6">
                            <div class="text-sm">
                                <div><span class="font-medium">Type:</span> {{ props.quote.source_type || '-' }}</div>
                                <div v-if="props.quote.source_type === 'jobcard' && props.quote.source_id">
                                    <Link
                                        :href="jobcards.show(props.quote.source_id).url"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View Original Jobcard
                                    </Link>
                                    <span v-if="props.quote.source?.job_number" class="ml-2 text-gray-500">
                                        ({{ props.quote.source.job_number }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Financial Summary</h2>
                            <p class="text-sm text-gray-600">Cost breakdown and totals</p>
                        </div>
                        <div class="p-6 space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Subtotal:</span>
                                <span class="text-sm font-medium text-gray-900">R{{ formatCurrency(props.quote.subtotal) }}</span>
                            </div>
                            <div v-if="(Number(props.quote.discount_amount) || 0) > 0" class="flex justify-between">
                                <span class="text-sm text-gray-500">Discount:</span>
                                <span class="text-sm font-medium text-red-600">-R{{ formatCurrency(props.quote.discount_amount) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-500">Tax:</span>
                                <span class="text-sm font-medium text-gray-900">R{{ formatCurrency(props.quote.tax_amount) }}</span>
                            </div>
                            <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                                <span class="text-sm text-gray-500">Rounding Adjustment:</span>
                                <span class="text-sm font-medium text-gray-900">R{{ formatCurrency(roundingAdjustment) }}</span>
                            </div>
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-base font-semibold text-gray-900">Total:</span>
                                    <span class="text-base font-semibold text-gray-900">R{{ formatCurrency(props.quote.total) }}</span>
                                </div>
                                <div class="mt-2 flex justify-between">
                                    <span class="text-sm text-gray-500">Purchasing Total:</span>
                                    <span class="text-sm font-medium text-gray-900">R{{ formatCurrency(purchaseOrdersTotal) }}</span>
                                </div>
                                <div class="mt-2 flex justify-between">
                                    <span class="text-sm text-gray-500">Total vs Purchasing:</span>
                                    <span class="text-sm font-medium" :class="purchaseVariance >= 0 ? 'text-green-700' : 'text-red-700'">
                                        R{{ formatCurrency(purchaseVariance) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="props.relatedPurchaseOrders && props.relatedPurchaseOrders.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Related Purchase Orders</h2>
                            <p class="text-sm text-gray-600">Purchase orders linked to this quote</p>
                        </div>
                        <div class="p-6 space-y-2">
                            <div v-for="po in props.relatedPurchaseOrders" :key="po.id" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <Link :href="purchaseOrders.show(po.id).url" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ po.po_number }}
                                    </Link>
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 capitalize">{{ po.status }}</span>
                                </div>
                                <span class="text-sm font-medium text-gray-900">R{{ formatCurrency(po.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Modal -->
        <div v-if="showEmailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Email Quote</h3>
                    <form @submit.prevent="sendEmail">
                        <div class="mb-4">
                            <EmailRecipientsInput
                                v-model="emailRecipients"
                                :customer-id="props.quote.customer?.id ?? null"
                                :error="emailRecipientError || emailForm.errors.email"
                            />
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">PDF Type</label>
                            <select
                                v-model="emailForm.type"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option value="quotation">Quotation</option>
                                <option value="proforma-invoice">Proforma Invoice</option>
                            </select>
                        </div>
                        <div v-if="props.pdfTemplates && props.pdfTemplates.length > 0" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">PDF Template</label>
                            <select
                                v-model="emailForm.template_id"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option :value="null">Use System Template (Default)</option>
                                <option v-for="template in emailTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea
                                v-model="emailForm.message"
                                rows="3"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Optional message to include with the quote..."
                            ></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="showEmailModal = false"
                                class="rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="emailForm.processing"
                                class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                            >
                                {{ emailForm.processing ? 'Sending...' : 'Send Email' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Result Dialog -->
        <div v-if="showResultDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium text-gray-900 mb-2 text-center">
                        {{ emailResult.success ? 'Email Sent Successfully!' : 'Email Failed to Send' }}
                    </h3>
                    
                    <div class="text-center">
                        <p v-if="emailResult.success" class="text-sm text-gray-600 mb-4">
                            The quote has been sent to <strong>{{ emailResult.email }}</strong>
                        </p>
                        <p v-else class="text-sm text-red-600 mb-4">
                            {{ emailResult.message }}
                        </p>
                    </div>
                    
                    <div class="flex justify-center">
                        <button
                            @click="showResultDialog = false"
                            class="rounded-md px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
                            :class="emailResult.success ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'"
                        >
                            {{ emailResult.success ? 'Great!' : 'Try Again' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showSignModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-[720px] max-w-[95vw] shadow-lg rounded-md bg-white">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Capture Signature</h3>
                <form @submit.prevent="saveSignature">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input v-model="signForm.signer_name" type="text" class="w-full rounded border px-3 py-2" required />
                        <div v-if="signForm.errors.signer_name" class="mt-1 text-sm text-red-600">{{ signForm.errors.signer_name }}</div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Signature</label>
                        <canvas
                            ref="signatureCanvas"
                            class="w-full border rounded bg-white touch-none"
                            @mousedown="startDraw"
                            @mousemove="draw"
                            @mouseup="stopDraw"
                            @mouseleave="stopDraw"
                            @touchstart.prevent="startDraw"
                            @touchmove.prevent="draw"
                            @touchend.prevent="stopDraw"
                        />
                        <div v-if="signForm.errors.signature_data" class="mt-1 text-sm text-red-600">{{ signForm.errors.signature_data }}</div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button type="button" @click="clearSignature" class="rounded border px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Clear</button>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showSignModal = false" class="rounded border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="signForm.processing" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                                {{ signForm.processing ? 'Saving...' : 'Save Signature' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Template Selection Modal for Download -->
        <div v-if="showTemplateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select PDF Template</h3>
                    <div class="mb-4">
                        <label for="pdf_type_select" class="block text-sm font-medium text-gray-700 mb-1">PDF Type</label>
                        <select
                            id="pdf_type_select"
                            v-model="selectedPdfType"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option :value="null">Select PDF Type</option>
                            <option value="quotation">Quotation</option>
                            <option value="proforma-invoice">Proforma Invoice</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="template_select" class="block text-sm font-medium text-gray-700 mb-1">Choose Template</label>
                        <select
                            id="template_select"
                            v-model="selectedTemplateId"
                            :disabled="!selectedPdfType"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option :value="null">Use System Template (Default)</option>
                            <option v-for="template in filteredTemplates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showTemplateModal = false; selectedTemplateId = null; selectedPdfType = null;"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="downloadPDF(selectedPdfType)"
                            :disabled="!selectedPdfType"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Download
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailRecipientsInput from '@/components/EmailRecipientsInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import jobcards from '@/routes/jobcards';
import purchaseOrders from '@/routes/purchase-orders';
import products from '@/routes/products';
import customers from '@/routes/customers';
import { ref, computed, watch } from 'vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';

interface Customer {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
}

interface Product {
    id: number;
    name: string;
    sku?: string | null;
    barcode?: string | null;
}

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
    product_id?: number | null;
    formatted_unit_price: string;
    formatted_total: string;
    line_group_id?: number | null;
    product?: Product | null;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate?: { id: number; name: string; rate: number } | null;
}

interface Quote {
    id: number;
    invoice_id?: number;
    source_type?: string | null;
    source_id?: number | null;
    quote_number: string;
    order_number?: string | null;
    email?: string | null;
    phone?: string | null;
    title: string;
    description?: string;
    status: string;
    expiry_date?: string;
    subtotal: number;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    notes?: string;
    terms_conditions?: string;
    created_at: string;
    updated_at: string;
    customer?: Customer;
    line_items: LineItem[];
    line_groups?: { id: number; name: string; sort_order?: number }[];
    source?: {
        id: number;
        job_number?: string;
    } | null;
}

interface Props {
    quote: Quote;
    canEditCompleted: boolean;
    statusOptions: Array<{ value: string; label: string }>;
    pdfTemplates?: Array<{ id: number; name: string; module: string; is_default: boolean }>;
    defaultQuoteTemplateId?: number | null;
    defaultProformaTemplateId?: number | null;
    convertedJobcardId?: number | null;
    relatedPurchaseOrders?: Array<{
        id: number;
        po_number: string;
        status: string;
        total: number;
        created_at: string | null;
    }>;
    purchaseOrdersTotal?: number;
    signatures?: Array<{
        id: number;
        signer_name: string;
        signature_url: string | null;
        signed_at: string | null;
        user_name?: string | null;
    }>;
    documentSigningEnabled?: boolean;
}

const props = defineProps<Props>();

const hasQuotesEdit = useAuthAbility('quotes', 'edit');
const hasPurchaseOrdersCreate = useAuthAbility('purchase-orders', 'create');
const isRoundingAdjustmentLine = (item: LineItem) => {
    return (item.description || '').trim().toLowerCase() === 'rounding adjustment';
};

const visibleQuoteLineItems = computed(() => {
    return (props.quote.line_items || []).filter((item) => !isRoundingAdjustmentLine(item));
});

const groupedVisibleQuoteLineItems = computed(() => {
    const groups = [...(props.quote.line_groups || [])].sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
    const fallbackGroupId = groups[0]?.id ?? 1;
    const baseItems = visibleQuoteLineItems.value;
    const usedIds = new Set<number>();

    const grouped = groups
        .map((group) => {
            const items = baseItems.filter((item) => (item.line_group_id ?? fallbackGroupId) === group.id);
            items.forEach((item) => usedIds.add(item.id));
            return { groupId: group.id, groupName: group.name || 'Items', items };
        })
        .filter((group) => group.items.length > 0);

    const ungroupedItems = baseItems.filter((item) => !usedIds.has(item.id));
    if (ungroupedItems.length > 0) {
        grouped.push({
            groupId: -1,
            groupName: grouped.length === 0 ? 'Items' : 'Ungrouped',
            items: ungroupedItems,
        });
    }

    if (grouped.length === 0 && baseItems.length > 0) {
        grouped.push({ groupId: -1, groupName: 'Items', items: baseItems });
    }

    return grouped;
});
const roundingAdjustment = computed(() => {
    return (props.quote.line_items || []).reduce((sum, item) => {
        if (!isRoundingAdjustmentLine(item)) {
            return sum;
        }
        return sum + (Number(item.total) || (Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
    }, 0);
});

const showEmailModal = ref(false);

watch(showEmailModal, (open) => {
    if (open) {
        const str = (props.quote as any).recipient_email || props.quote.email || props.quote.customer?.email || '';
        emailRecipients.value = parseInitialEmails(str);
        emailRecipientError.value = '';
    }
});
const showResultDialog = ref(false);
const showTemplateModal = ref(false);
const showSignModal = ref(false);
const selectedPdfType = ref<string | null>(null);
const selectedTemplateId = ref<number | null>(null);
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});
const signForm = useForm({
    signer_name: '',
    signature_data: '',
});
const signatureCanvas = ref<HTMLCanvasElement | null>(null);
const isSigning = ref(false);

// Computed property to check if user can edit the quote
const canEditQuote = computed(() => {
    if (!hasQuotesEdit.value) {
        return false;
    }
    if (props.quote.status !== 'accepted') {
        return true;
    }
    return props.canEditCompleted;
});
const canSignDocument = computed(() => !!props.documentSigningEnabled && canEditQuote.value);
const purchaseOrdersTotal = computed(() => Number(props.purchaseOrdersTotal) || 0);
const purchaseVariance = computed(() => (Number(props.quote.total) || 0) - purchaseOrdersTotal.value);

function parseInitialEmails(str: string | null | undefined): string[] {
    if (!str) return [];
    return str.split(',').map(s => s.trim()).filter(Boolean);
}

const initialEmailStr = (props.quote as any).recipient_email || props.quote.email || props.quote.customer?.email || '';
const emailRecipients = ref<string[]>(parseInitialEmails(initialEmailStr));

const emailForm = useForm({
    email: initialEmailStr,
    message: '',
    type: 'quotation' as 'quotation' | 'proforma-invoice',
    template_id: null as number | null,
});

const statusOptions = computed(() => props.statusOptions || []);

const updateStatus = (status: string) => {
    router.patch(quotes.updateStatus(props.quote.id).url, {
        status: status,
    }, {
        preserveScroll: true,
    });
};

const getModuleForPdfType = (type: string | null | undefined): 'quote' | 'proforma-invoice' => {
    return type === 'proforma-invoice' ? 'proforma-invoice' : 'quote';
};

// Computed property to filter templates by selected PDF type
const filteredTemplates = computed(() => {
    if (!props.pdfTemplates || !selectedPdfType.value) {
        return [];
    }
    return props.pdfTemplates.filter(t => t.module === getModuleForPdfType(selectedPdfType.value));
});

// Initialize selected template ID based on PDF type
watch(selectedPdfType, (newType) => {
    if (newType === 'quotation') {
        selectedTemplateId.value = props.defaultQuoteTemplateId ?? null;
    } else if (newType === 'proforma-invoice') {
        selectedTemplateId.value = props.defaultProformaTemplateId ?? null;
    }
}, { immediate: true });

const getDefaultTemplateIdForType = (type: 'quotation' | 'proforma-invoice') => {
    return type === 'proforma-invoice'
        ? (props.defaultProformaTemplateId ?? null)
        : (props.defaultQuoteTemplateId ?? null);
};

const emailTemplates = computed(() => {
    if (!props.pdfTemplates) {
        return [];
    }
    return props.pdfTemplates.filter((template) => template.module === getModuleForPdfType(emailForm.type));
});

watch(() => emailForm.type, (newType) => {
    emailForm.template_id = getDefaultTemplateIdForType(newType);
}, { immediate: true });

const openDownloadModal = () => {
    selectedPdfType.value = 'quotation';
    selectedTemplateId.value = null;
    showTemplateModal.value = true;
};

const downloadPDF = (type: string | null) => {
    if (!type) {
        return;
    }

    const url = new URL(quotes.downloadPdf(props.quote.id).url, window.location.origin);
    url.searchParams.set('type', type);
    if (selectedTemplateId.value) {
        url.searchParams.set('template_id', selectedTemplateId.value.toString());
    }
    window.open(url.toString(), '_blank');
    showTemplateModal.value = false;
    selectedTemplateId.value = null;
    selectedPdfType.value = null;
};

const emailRecipientError = ref('');

const sendEmail = () => {
    emailRecipientError.value = '';
    if (emailRecipients.value.length === 0) {
        emailRecipientError.value = 'Please add at least one recipient';
        return;
    }
    emailForm.email = emailRecipients.value.join(', ');
    emailForm.post(quotes.email(props.quote.id).url, {
        onSuccess: (page) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: true,
                email: emailForm.email,
                message: page.props.flash?.success || 'Email sent successfully'
            };
            showResultDialog.value = true;
            emailForm.reset();
            emailForm.type = 'quotation';
            emailForm.template_id = null;
        },
        onError: (errors) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: false,
                email: emailForm.email,
                message: errors.message || 'Failed to send email'
            };
            showResultDialog.value = true;
        }
    });
};

const convertToJobcard = () => {
    if (confirm('Are you sure you want to convert this quote to a jobcard?')) {
        router.post(quotes.convertToJobcard(props.quote.id).url);
    }
};

const convertToInvoice = () => {
    if (confirm('Are you sure you want to convert this quote to an invoice?')) {
        router.post(quotes.convertToInvoice(props.quote.id).url);
    }
};

const createPurchaseOrder = () => {
    window.location.href = `${purchaseOrders.create().url}?source_type=quote&source_id=${props.quote.id}`;
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        accepted: 'bg-green-100 text-green-800',
        rejected: 'bg-red-100 text-red-800',
        expired: 'bg-yellow-100 text-yellow-800',
    };
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800';
};

const formatStatus = (code: string) => {
    if (!code) return '';
    const opt = props.statusOptions?.find((o) => o.value === code);
    if (opt) return opt.label;
    return code.replace(/_/g, ' ').toUpperCase();
};

const formatDate = (dateString: string) => {
    return formatLocalizedDate(dateString);
};

const formatCurrency = (value: number | null | undefined) => {
    const numValue = Number(value) || 0;
    return numValue.toFixed(2);
};

const formatDateTime = (dateString: string) => {
    return formatLocalizedDateTime(dateString);
};

const { formatDate: formatLocalizedDate, formatDateTime: formatLocalizedDateTime } = useDateTimeFormat();

const openSignModal = () => {
    signForm.reset();
    signForm.clearErrors();
    showSignModal.value = true;

    requestAnimationFrame(() => {
        const canvas = signatureCanvas.value;
        if (!canvas) return;
        const width = canvas.clientWidth || 600;
        canvas.width = width;
        canvas.height = 180;
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.strokeStyle = '#111827';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
    });
};

const startDraw = (event: MouseEvent | TouchEvent) => {
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    isSigning.value = true;
    const rect = canvas.getBoundingClientRect();
    const point = 'touches' in event ? event.touches[0] : event;
    ctx.beginPath();
    ctx.moveTo(point.clientX - rect.left, point.clientY - rect.top);
};

const draw = (event: MouseEvent | TouchEvent) => {
    if (!isSigning.value) return;
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    const rect = canvas.getBoundingClientRect();
    const point = 'touches' in event ? event.touches[0] : event;
    ctx.lineTo(point.clientX - rect.left, point.clientY - rect.top);
    ctx.stroke();
};

const stopDraw = () => {
    isSigning.value = false;
};

const clearSignature = () => {
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
};

const saveSignature = () => {
    const canvas = signatureCanvas.value;
    if (!canvas) return;
    signForm.signature_data = canvas.toDataURL('image/png');
    signForm.post(`/quotes/${props.quote.id}/sign`, {
        preserveScroll: true,
        onSuccess: () => {
            showSignModal.value = false;
            router.reload({ only: ['signatures'] });
        },
    });
};
</script>
