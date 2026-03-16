<template>
    <Head :title="`Invoice ${props.invoice.invoice_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Invoices', href: invoices.index().url },
        { title: props.invoice.invoice_number, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Status Bar -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Status Management</h2>
                    <p class="text-sm text-gray-600">Update invoice status and track progress</p>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">Current Status:</span>
                            <div class="flex items-center gap-2">
                                <button
                                    v-for="status in statusOptions"
                                    :key="status.value"
                                    @click="updateStatus(status.value)"
                                    :class="[
                                        'px-3 py-1 text-sm font-medium rounded-md transition-colors',
                                        props.invoice.status === status.value
                                            ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'
                                    ]"
                                >
                                    {{ status.label }}
                                </button>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            Last updated: {{ formatDateTime(props.invoice.updated_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.invoice.invoice_number }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ props.invoice.title }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="getStatusBadgeClass(props.invoice.status)"
                            class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                        >
                            {{ formatStatus(props.invoice.status) }}
                        </span>
                        <button
                            v-if="!props.pdfTemplates || props.pdfTemplates.length === 0"
                            @click="downloadPDF"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Download PDF
                        </button>
                        <div v-else class="relative">
                            <button
                                @click="showTemplateModal = true"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Download PDF
                            </button>
                        </div>
                        <button
                            @click="showEmailModal = true"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Email
                        </button>
                        <button
                            v-if="(props.invoice.remaining_balance || props.invoice.total) > 0"
                            @click="showPaymentModal = true"
                            class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                        >
                            Add Payment
                        </button>
                        <Link
                            v-if="canCreateCreditNote"
                            :href="`/credit-notes/create?invoice_id=${props.invoice.id}&customer_id=${props.invoice.customer?.id}`"
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                        >
                            Create Credit Note
                        </Link>
                        <Link
                            v-if="canEditInvoice"
                            :href="invoices.edit(props.invoice.id).url"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Edit
                        </Link>
                        <span
                            v-else
                            class="rounded-md bg-gray-400 px-4 py-2 text-sm font-medium text-white cursor-not-allowed"
                            title="Cannot edit paid invoices without permission"
                        >
                            Edit
                        </span>
                    </div>
                </div>
            </div>

            <!-- Invoice Details -->
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
                                    <label class="block text-sm font-medium text-gray-700">Customer</label>
                                    <p class="text-sm text-gray-900">
                                        <Link
                                            v-if="props.invoice.customer && props.invoice.customer.id"
                                            :href="customers.show(props.invoice.customer.id).url"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ props.invoice.customer.name }}
                                        </Link>
                                        <span v-else>{{ props.invoice.customer?.name || '-' }}</span>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p class="text-sm text-gray-900">{{ props.invoice.email || props.invoice.customer?.email || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <p class="text-sm text-gray-900">{{ props.invoice.phone || props.invoice.customer?.phone || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <p class="text-sm text-gray-900">{{ props.invoice.customer?.address || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Line Items -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                            <p class="text-sm text-gray-600">Invoice line items and pricing details</p>
                        </div>
                        <div class="p-6">
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
                                        <template v-for="item in props.invoice.line_items" :key="item.id">
                                            <tr>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-center">
                                                    {{ item.quantity }}
                                                </td>
                                                <td class="px-3 py-3 text-sm text-gray-900">
                                                    <div>
                                                        <template v-if="item.product && item.product.id">
                                                            <Link
                                                                :href="products.show(item.product.id).url"
                                                                class="text-blue-600 hover:text-blue-800 hover:underline"
                                                            >
                                                                {{ item.description }}
                                                            </Link>
                                                        </template>
                                                        <template v-else-if="item.product_id">
                                                            <Link
                                                                :href="products.show(item.product_id).url"
                                                                class="text-blue-600 hover:text-blue-800 hover:underline"
                                                            >
                                                                {{ item.description }}
                                                            </Link>
                                                        </template>
                                                        <template v-else>
                                                            <span>{{ item.description }}</span>
                                                        </template>
                                                    </div>
                                                    <div v-if="item.serialNumbers && item.serialNumbers.length > 0" class="mt-1">
                                                        <div class="flex flex-wrap gap-1">
                                                            <span
                                                                v-for="serial in item.serialNumbers"
                                                                :key="serial.id"
                                                                class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700"
                                                            >
                                                                {{ serial.serial_number }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                    {{ formatCurrency(item.unit_price) }}
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                    <span v-if="item.discount_percentage && item.discount_percentage > 0" class="text-red-600">{{ item.discount_percentage }}%</span>
                                                    <span v-else-if="item.discount_amount && item.discount_amount > 0" class="text-red-600">{{ formatCurrency(item.discount_amount) }}</span>
                                                    <span v-else class="text-gray-400">&mdash;</span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                                    <span v-if="item.tax_rate" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700">{{ item.tax_rate.name }} ({{ item.tax_rate.rate }}%)</span>
                                                    <span v-else class="text-gray-400">&mdash;</span>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                    {{ formatCurrency(item.total) }}
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Notes and Terms -->
                    <div v-if="props.invoice.notes || props.invoice.terms" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Additional Information</h2>
                            <p class="text-sm text-gray-600">Notes and terms for this invoice</p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-if="props.invoice.notes">
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ props.invoice.notes }}</p>
                                </div>
                                <div v-if="props.invoice.terms">
                                    <label class="block text-sm font-medium text-gray-700">Terms & Conditions</label>
                                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ props.invoice.terms }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Invoice Details -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Invoice Details</h2>
                            <p class="text-sm text-gray-600">Key information about this invoice</p>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Invoice Number</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.invoice.invoice_number }}</p>
                            </div>
                            <div v-if="props.invoice.order_number">
                                <label class="block text-sm font-medium text-gray-500">Order Number</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.invoice.order_number }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Invoice Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.invoice.invoice_date) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Due Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.invoice.due_date) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Status</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatStatus(props.invoice.status) }}</p>
                            </div>
                            <div v-if="props.invoice.salesperson">
                                <label class="block text-sm font-medium text-gray-500">Salesperson</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.invoice.salesperson.name }}</p>
                            </div>
                            <div v-if="props.invoice.description">
                                <label class="block text-sm font-medium text-gray-500">Description</label>
                                <p class="mt-1 text-sm text-gray-600">{{ props.invoice.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Totals</h2>
                            <p class="text-sm text-gray-600">Invoice totals and calculations</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="font-medium">{{ formatCurrency(props.invoice.subtotal) }}</span>
                                </div>
                                <div v-if="(Number(props.invoice.discount_amount) || 0) > 0" class="flex justify-between">
                                    <span class="text-gray-600">Discount:</span>
                                    <span class="font-medium text-red-600">-{{ formatCurrency(props.invoice.discount_amount) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax:</span>
                                    <span class="font-medium">{{ formatCurrency(props.invoice.tax_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-semibold border-t pt-2">
                                    <span>Total:</span>
                                    <span>{{ formatCurrency(props.invoice.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Notes -->
                    <div v-if="props.invoice.credit_notes && props.invoice.credit_notes.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-orange-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Credit Notes</h2>
                            <p class="text-sm text-gray-600">Credit notes linked to this invoice</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-2">
                                <div
                                    v-for="cn in props.invoice.credit_notes"
                                    :key="cn.id"
                                    class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg"
                                >
                                    <div>
                                        <Link :href="`/credit-notes/${cn.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-900">
                                            {{ cn.credit_note_number }}
                                        </Link>
                                        <p class="text-xs text-gray-500">{{ new Date(cn.credit_note_date).toLocaleDateString('en-ZA') }}</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-sm font-medium text-orange-600">-{{ formatCurrency(cn.total) }}</span>
                                        <p class="text-xs text-gray-500 capitalize">{{ cn.status }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payments -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Payments</h2>
                            <p class="text-sm text-gray-600">Payment history and balance</p>
                        </div>
                        <div class="p-6">
                            <!-- Payment Summary -->
                            <div class="mb-4 space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-700">Total Amount:</span>
                                    <span class="text-sm font-medium">{{ formatCurrency(props.invoice.total) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-700">Total Paid:</span>
                                    <span class="text-sm font-medium text-green-600">{{ formatCurrency(props.invoice.total_paid || 0) }}</span>
                                </div>
                                <div v-if="(props.invoice.total_credited || 0) > 0" class="flex justify-between">
                                    <span class="text-sm font-medium text-gray-700">Credit Notes Applied:</span>
                                    <span class="text-sm font-medium text-orange-600">-{{ formatCurrency(props.invoice.total_credited || 0) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="text-sm font-semibold text-gray-900">Remaining Balance:</span>
                                    <span class="text-sm font-semibold" :class="(props.invoice.remaining_balance || props.invoice.total) > 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ formatCurrency(props.invoice.remaining_balance ?? props.invoice.total) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Payment History -->
                            <div v-if="props.invoice.payments && props.invoice.payments.length > 0">
                                <h3 class="text-sm font-medium text-gray-900 mb-3">Payment History</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="payment in props.invoice.payments"
                                        :key="payment.id"
                                        class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg"
                                    >
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-medium">{{ formatCurrency(payment.amount) }}</span>
                                                <span class="text-xs px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                                    {{ payment.payment_method.toUpperCase() }}
                                                </span>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ formatDate(payment.payment_date) }}
                                                <span v-if="payment.notes" class="ml-2">• {{ payment.notes }}</span>
                                            </div>
                                        </div>
                                        <button
                                            @click="removePayment(payment.id)"
                                            class="text-red-600 hover:text-red-800 text-xs"
                                            title="Remove Payment"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-center py-4 text-sm text-gray-500">
                                No payments recorded
                            </div>
                        </div>
                    </div>

                    <!-- Source Information -->
                    <div v-if="props.invoice.source" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Source</h2>
                            <p class="text-sm text-gray-600">Original document this invoice was created from</p>
                        </div>
                        <div class="p-6">
                            <div class="text-sm">
                                <div><span class="font-medium">Type:</span> {{ props.invoice.source_type }}</div>
                                <div v-if="props.invoice.source_type === 'quote'">
                                    <Link
                                        :href="quotes.show(props.invoice.source_id).url"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View Original Quote
                                    </Link>
                                </div>
                                <div v-else-if="props.invoice.source_type === 'jobcard'">
                                    <Link
                                        :href="jobcards.show(props.invoice.source_id).url"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View Original Jobcard
                                    </Link>
                                </div>
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
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Email Invoice</h3>
                    <form @submit.prevent="sendEmail">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input
                                v-model="emailForm.email"
                                type="email"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': emailForm.errors.email }"
                                required
                            />
                            <div v-if="emailForm.errors.email" class="text-red-500 text-sm mt-1">
                                {{ emailForm.errors.email }}
                            </div>
                        </div>
                        <div class="mb-4" v-if="props.pdfTemplates && props.pdfTemplates.length > 0">
                            <label class="block text-sm font-medium text-gray-700 mb-1">PDF Template</label>
                            <select
                                v-model="emailForm.template_id"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option :value="null">Use System Template</option>
                                <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea
                                v-model="emailForm.customMessage"
                                rows="3"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Optional message to include with the invoice..."
                            ></textarea>
                        </div>
                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="showEmailModal = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="emailForm.processing"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ emailForm.processing ? 'Sending...' : 'Send Email' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Template Selection Modal for Download -->
        <div v-if="showTemplateModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3 text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select PDF Template</h3>
                    <div class="mb-4">
                        <label for="template_select" class="block text-sm font-medium text-gray-700 mb-1">Choose Template</label>
                        <select
                            id="template_select"
                            v-model="selectedTemplateId"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option :value="null">Use Blade Template (Default)</option>
                            <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showTemplateModal = false; selectedTemplateId = null;"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="downloadPDF"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Download
                        </button>
                    </div>
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
                            The invoice has been sent to <strong>{{ emailResult.email }}</strong>
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

        <!-- Payment Modal -->
        <div v-if="showPaymentModal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;" @click="showPaymentModal = false">
            <div style="background: white; padding: 20px; border-radius: 8px; max-width: 500px; width: 90%;" @click.stop>
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: #111827;">
                    Add Payment
                </h3>
                <form @submit.prevent="addPayment" style="display: flex; flex-direction: column; gap: 16px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Amount</label>
                            <input
                                v-model="paymentForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :max="props.invoice.remaining_balance || props.invoice.total"
                                style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 12px; font-size: 14px;"
                                :style="{ 'border-color': paymentForm.errors.amount ? '#ef4444' : '#d1d5db' }"
                                required
                            />
                            <div style="font-size: 12px; color: #6b7280; margin-top: 4px;">
                                Auto-filled with remaining balance
                            </div>
                            <div v-if="paymentForm.errors.amount" style="color: #ef4444; font-size: 12px; margin-top: 4px;">
                                {{ paymentForm.errors.amount }}
                            </div>
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Payment Method</label>
                            <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px;">
                                <button
                                    v-for="method in paymentMethodOptions"
                                    :key="method.value"
                                    type="button"
                                    @click="paymentForm.payment_method = method.value"
                                    :style="{
                                        border: paymentForm.payment_method === method.value ? '1px solid #3b82f6' : '1px solid #d1d5db',
                                        background: paymentForm.payment_method === method.value ? '#eff6ff' : '#ffffff',
                                        color: paymentForm.payment_method === method.value ? '#1d4ed8' : '#374151',
                                        borderRadius: '4px',
                                        padding: '8px 10px',
                                        fontSize: '14px',
                                        fontWeight: '500',
                                        cursor: 'pointer'
                                    }"
                                >
                                    {{ method.label }}
                                </button>
                            </div>
                            <div v-if="paymentForm.errors.payment_method" style="color: #ef4444; font-size: 12px; margin-top: 4px;">
                                {{ paymentForm.errors.payment_method }}
                            </div>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Payment Date</label>
                        <input
                            v-model="paymentForm.payment_date"
                            type="date"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 12px; font-size: 14px;"
                            :style="{ 'border-color': paymentForm.errors.payment_date ? '#ef4444' : '#d1d5db' }"
                            required
                        />
                        <div v-if="paymentForm.errors.payment_date" style="color: #ef4444; font-size: 12px; margin-top: 4px;">
                            {{ paymentForm.errors.payment_date }}
                        </div>
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 4px;">Notes (Optional)</label>
                        <textarea
                            v-model="paymentForm.notes"
                            rows="3"
                            style="width: 100%; border: 1px solid #d1d5db; border-radius: 4px; padding: 8px 12px; font-size: 14px; resize: vertical;"
                            :style="{ 'border-color': paymentForm.errors.notes ? '#ef4444' : '#d1d5db' }"
                            placeholder="Add any additional notes about this payment..."
                        ></textarea>
                        <div v-if="paymentForm.errors.notes" style="color: #ef4444; font-size: 12px; margin-top: 4px;">
                            {{ paymentForm.errors.notes }}
                        </div>
                    </div>
                </form>
                <div style="display: flex; gap: 12px; margin-top: 20px; justify-content: flex-end;">
                    <button
                        @click="showPaymentModal = false"
                        :disabled="paymentForm.processing"
                        style="padding: 8px 16px; border: 1px solid #d1d5db; border-radius: 4px; background: white; color: #374151; font-size: 14px; font-weight: 500; cursor: pointer;"
                        :style="{ 'opacity': paymentForm.processing ? '0.5' : '1' }"
                    >
                        Cancel
                    </button>
                    <button
                        @click="addPayment"
                        :disabled="paymentForm.processing"
                        style="padding: 8px 16px; border: none; border-radius: 4px; background: #2563eb; color: white; font-size: 14px; font-weight: 500; cursor: pointer;"
                        :style="{ 'opacity': paymentForm.processing ? '0.5' : '1' }"
                    >
                        {{ paymentForm.processing ? 'Adding...' : 'Add Payment' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import invoices from '@/routes/invoices';
import quotes from '@/routes/quotes';
import jobcards from '@/routes/jobcards';
import products from '@/routes/products';
import customers from '@/routes/customers';

const page = usePage();
const canCreateCreditNote = computed(() => !!(page.props.auth as any)?.abilities?.['credit-notes']?.create);

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface Product {
    id: number;
    name: string;
}

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    discount_amount?: number;
    discount_percentage?: number;
    total: number;
    product_id?: number | null;
    product?: Product | null;
    serial_number_ids?: number[];
    serialNumbers?: SerialNumber[];
}

interface Customer {
    id: number;
    name: string;
    email?: string;
    phone?: string;
}

interface Salesperson {
    id: number;
    name: string;
}

interface Payment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    notes?: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
    order_number?: string | null;
    job_number?: string | null;
    title: string;
    description?: string;
    status: string;
    invoice_date: string;
    due_date: string;
    subtotal: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    total_paid?: number;
    total_credited?: number;
    remaining_balance?: number;
    email?: string | null;
    phone?: string | null;
    notes?: string;
    terms?: string;
    source_type?: string;
    source_id?: number;
    updated_at: string;
    customer?: Customer;
    salesperson?: Salesperson;
    line_items: LineItem[];
    payments?: Payment[];
    source?: any;
}

interface PdfTemplate {
    id: number;
    name: string;
}

interface Props {
    invoice: Invoice;
    canEditCompleted: boolean;
    pdfTemplates?: PdfTemplate[];
    defaultTemplateId?: number | null;
}

const props = defineProps<Props>();

const showEmailModal = ref(false);
const showResultDialog = ref(false);
const showPaymentModal = ref(false);
const showTemplateModal = ref(false);
const selectedTemplateId = ref<number | null>(props.defaultTemplateId ?? null);
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});

// Computed property to check if user can edit the invoice
const canEditInvoice = computed(() => {
    if (props.invoice.status !== 'paid') {
        return true;
    }
    return props.canEditCompleted;
});

const emailForm = useForm({
    email: props.invoice.email || props.invoice.customer?.email || '',
    customMessage: '',
    template_id: null as number | null,
});

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString();
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: 'ZAR',
    }).format(amount || 0);
};

const formatStatus = (status: string) => {
    return status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        sent: 'bg-blue-100 text-blue-800',
        paid: 'bg-green-100 text-green-800',
        overdue: 'bg-red-100 text-red-800',
        cancelled: 'bg-gray-100 text-gray-800',
    };
    return classes[status as keyof typeof classes] || 'bg-gray-100 text-gray-800';
};

const updateStatus = (status: string) => {
    router.patch(invoices.updateStatus(props.invoice.id).url, { status });
};

const downloadPDF = () => {
    const url = new URL(invoices.downloadPdf(props.invoice.id).url, window.location.origin);
    if (selectedTemplateId.value) {
        url.searchParams.set('template_id', selectedTemplateId.value.toString());
    }
    window.open(url.toString(), '_blank');
    showTemplateModal.value = false;
    selectedTemplateId.value = null;
};

const sendEmail = () => {
    emailForm.post(invoices.email(props.invoice.id).url, {
        onSuccess: (page) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: true,
                email: emailForm.email,
                message: page.props.flash?.success || 'Email sent successfully'
            };
            showResultDialog.value = true;
            emailForm.reset();
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

// Payment form
const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'eft', label: 'EFT' },
];

const paymentForm = useForm({
    invoice_id: props.invoice.id,
    amount: (props.invoice.remaining_balance || props.invoice.total).toString(),
    payment_method: '',
    payment_date: new Date().toISOString().split('T')[0], // Today's date
    notes: '',
});

const addPayment = () => {
    paymentForm.post('/payments', {
        onSuccess: () => {
            paymentForm.reset();
            paymentForm.invoice_id = props.invoice.id;
            paymentForm.amount = (props.invoice.remaining_balance || props.invoice.total).toString();
            paymentForm.payment_date = new Date().toISOString().split('T')[0];
            showPaymentModal.value = false;
            // Reload the page to get updated invoice data
            router.visit(window.location.pathname, { method: 'get' });
        }
    });
};

const removePayment = (paymentId: number) => {
    if (confirm('Are you sure you want to remove this payment?')) {
        router.delete(`/payments/${paymentId}`, {
            onSuccess: () => {
                // Reload the page to get updated invoice data
                router.visit(window.location.pathname, { method: 'get' });
            }
        });
    }
};
</script>
