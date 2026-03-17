<template>
    <Head :title="`Jobcard ${props.jobcard.job_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Jobcards', href: jobcards.index().url },
        { title: props.jobcard.job_number, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Status Bar -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Status Management</h2>
                    <p class="text-sm text-gray-600">Update jobcard status and track progress</p>
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
                                    :disabled="!canUpdateStatus(status.value)"
                                    :class="[
                                        'px-3 py-1 text-sm font-medium rounded-md transition-colors',
                                        props.jobcard.status === status.value
                                            ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200',
                                        !canUpdateStatus(status.value)
                                            ? 'opacity-50 cursor-not-allowed'
                                            : 'cursor-pointer'
                                    ]"
                                >
                                    {{ status.label }}
                                </button>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            Last updated: {{ formatDateTime(props.jobcard.updated_at) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.jobcard.job_number }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ props.jobcard.title }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            :class="getStatusBadgeClass(props.jobcard.status)"
                            class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                        >
                        {{ formatStatus(props.jobcard.status) }}
                    </span>
                        <button
                            v-if="!props.pdfTemplates || props.pdfTemplates.length === 0"
                            @click="downloadPDF"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Download PDF
                        </button>
                        <button
                            v-else
                            @click="showTemplateModal = true"
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
                        <template v-if="!isLimitedUser">
                            <button
                                v-if="!props.jobcard.invoice_id"
                                @click="convertToInvoice"
                                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                            >
                                Convert to Invoice
                            </button>
                            <Link
                                v-else
                                :href="invoices.show(props.jobcard.invoice_id).url"
                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            >
                                View Invoice
                            </Link>
                            <Link
                                v-if="canEditJobcard"
                                :href="jobcards.edit(props.jobcard.id).url"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Edit
                            </Link>
                            <span
                                v-else
                                class="rounded-md bg-gray-400 px-4 py-2 text-sm font-medium text-white cursor-not-allowed"
                                title="Cannot edit completed jobcards without permission"
                            >
                                Edit
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Time Tracking -->
                    <TimeTracking
                        :jobcard-id="props.jobcard.id"
                        :time-entries="props.jobcard.time_entries"
                        :running-timer="props.runningTimer"
                        :time-summary="props.timeSummary"
                    />
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
                                            v-if="props.jobcard.customer && props.jobcard.customer.id"
                                            :href="customers.show(props.jobcard.customer.id).url"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ props.jobcard.customer.name }}
                                        </Link>
                                        <span v-else>{{ props.jobcard.customer?.name || '-' }}</span>
                                    </p>
                                </div>
                                <div v-if="(props.jobcard as any).contact">
                                    <label class="block text-sm font-medium text-gray-700">Contact</label>
                                    <p class="text-sm text-gray-900">
                                        <Link
                                            :href="`/contacts/${(props.jobcard as any).contact.id}`"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ (props.jobcard as any).contact.name }}
                                        </Link>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.email || props.jobcard.customer.email || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.phone || props.jobcard.customer.phone || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Address</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.customer.address || '-' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Job Details -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Job Details</h2>
                            <p class="text-sm text-gray-600">Work description and additional notes</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <p class="text-sm text-gray-900 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.jobcard.description || '-' }}</p>
                                </div>
                                <div v-if="props.jobcard.notes">
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <p class="text-sm text-gray-900 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.jobcard.notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                            <p class="text-sm text-gray-600">Products and services included in this jobcard</p>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-16">Qty</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Price</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Discount</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Tax</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in props.jobcard.line_items" :key="item.id">
                                            <td class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-center">
                                                {{ item.quantity }}
                                            </td>
                                            <td class="px-3 py-3 text-sm text-gray-900">
                                                <Link
                                                    v-if="item.product_id && item.product"
                                                    :href="products.show(item.product_id).url"
                                                    class="text-blue-600 hover:text-blue-800 hover:underline"
                                                >
                                                    {{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}
                                                </Link>
                                                <span v-else>{{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}</span>
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                {{ item.formatted_unit_price }}
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-right">
                                                <span v-if="item.discount_percentage && item.discount_percentage > 0" class="text-red-600">{{ item.discount_percentage }}%</span>
                                                <span v-else-if="item.discount_amount && item.discount_amount > 0" class="text-red-600">R{{ Number(item.discount_amount).toFixed(2) }}</span>
                                                <span v-else class="text-gray-400">&mdash;</span>
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <span v-if="item.tax_rate" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700">{{ item.tax_rate.name }} ({{ item.tax_rate.rate }}%)</span>
                                                <span v-else class="text-gray-400">&mdash;</span>
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                {{ item.formatted_total }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Conditions -->
                    <div v-if="props.jobcard.terms_conditions" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Terms & Conditions</h2>
                            <p class="text-sm text-gray-600">Legal terms and conditions for this jobcard</p>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-900 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.jobcard.terms_conditions }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Job Information -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Job Information</h2>
                            <p class="text-sm text-gray-600">Jobcard details and timeline</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Job Number</label>
                                    <p class="text-sm text-gray-900 font-mono">{{ props.jobcard.job_number }}</p>
                                </div>
                                <div v-if="props.jobcard.order_number">
                                    <label class="block text-sm font-medium text-gray-700">Order Number</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.order_number }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <span
                                        :class="getStatusBadgeClass(props.jobcard.status)"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                    >
                                        {{ formatStatus(props.jobcard.status) }}
                                    </span>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Assigned To</label>
                                    <p class="text-sm text-gray-900">
                                        <span v-if="props.jobcard.assigned_user" class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-700">
                                                {{ props.jobcard.assigned_user.name.charAt(0).toUpperCase() }}
                                            </span>
                                            {{ props.jobcard.assigned_user.name }}
                                        </span>
                                        <span v-else-if="props.jobcard.assigned_team" class="inline-flex items-center gap-1.5">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-purple-100 text-xs font-medium text-purple-700">
                                                {{ props.jobcard.assigned_team.name.charAt(0).toUpperCase() }}
                                            </span>
                                            {{ props.jobcard.assigned_team.name }}
                                            <span class="text-xs text-gray-400">(Team)</span>
                                        </span>
                                        <span v-else class="text-gray-400">Unassigned</span>
                                    </p>
                                </div>
                                <div v-if="props.jobcard.start_date">
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <p class="text-sm text-gray-900">{{ formatDate(props.jobcard.start_date) }}</p>
                                </div>
                                <div v-if="props.jobcard.due_date">
                                    <label class="block text-sm font-medium text-gray-700">Due Date</label>
                                    <p class="text-sm text-gray-900">{{ formatDate(props.jobcard.due_date) }}</p>
                                </div>
                                <div v-if="props.jobcard.completed_date">
                                    <label class="block text-sm font-medium text-gray-700">Completed Date</label>
                                    <p class="text-sm text-gray-900">{{ formatDate(props.jobcard.completed_date) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Created</label>
                                    <p class="text-sm text-gray-900">{{ formatDateTime(props.jobcard.created_at) }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                                    <p class="text-sm text-gray-900">{{ formatDateTime(props.jobcard.updated_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing Summary -->
                    <div v-if="!isLimitedUser" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Pricing Summary</h2>
                            <p class="text-sm text-gray-600">Cost breakdown and totals</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Subtotal:</span>
                                    <span class="text-sm font-medium">R{{ (Number(props.jobcard.subtotal) || 0).toFixed(2) }}</span>
                                </div>
                                <div v-if="(Number(props.jobcard.discount_amount) || 0) > 0" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Discount:</span>
                                    <span class="text-sm font-medium text-red-600">-R{{ (Number(props.jobcard.discount_amount) || 0).toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Tax:</span>
                                    <span class="text-sm font-medium">R{{ (Number(props.jobcard.tax_amount) || 0).toFixed(2) }}</span>
                                </div>
                                <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                                    <span class="text-sm text-gray-600">Rounding Adjustment:</span>
                                    <span class="text-sm font-medium">R{{ roundingAdjustment.toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-3">
                                    <span class="text-base font-semibold">Total:</span>
                                    <span class="text-base font-semibold">{{ props.jobcard.formatted_total }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div v-if="!isLimitedUser" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Actions</h2>
                            <p class="text-sm text-gray-600">Manage this jobcard</p>
                        </div>
                        <div class="p-6">
                            <div class="space-y-3">
                                <Link
                                    :href="jobcards.edit(props.jobcard.id).url"
                                    class="block w-full rounded-md bg-blue-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    Edit Jobcard
                                </Link>
                                <button
                                    v-if="canDeleteJobcard"
                                    @click="deleteJobcard"
                                    class="block w-full rounded-md border border-red-300 px-4 py-2 text-center text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                >
                                    Delete Jobcard
                                </button>
                                <span
                                    v-else
                                    class="block w-full rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-medium text-gray-500 cursor-not-allowed"
                                    title="Cannot delete completed jobcards without permission"
                                >
                                    Delete Jobcard
                                </span>
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
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Email Jobcard</h3>
                    <form @submit.prevent="sendEmail">
                        <div class="mb-4">
                            <EmailRecipientsInput
                                v-model="emailRecipients"
                                :customer-id="props.jobcard.customer?.id ?? null"
                                :error="emailRecipientError || emailForm.errors.email"
                            />
                        </div>
                        <div v-if="props.pdfTemplates && props.pdfTemplates.length > 0" class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">PDF Template</label>
                            <select
                                v-model="emailForm.template_id"
                                class="w-full rounded border px-3 py-2"
                            >
                                <option :value="null">Use System Template (Default)</option>
                                <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <textarea
                                v-model="emailForm.message"
                                rows="4"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Optional message to include with the jobcard PDF..."
                            ></textarea>
                            <p class="text-xs text-gray-500 mt-1">The jobcard will be sent as a PDF attachment.</p>
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
                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50"
                            >
                                {{ emailForm.processing ? 'Sending...' : 'Send Email' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email Result Dialog -->
        <div v-if="showResultDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full"
                         :class="emailResult.success ? 'bg-green-100' : 'bg-red-100'">
                        <svg v-if="emailResult.success" class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <svg v-else class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    
                    <h3 class="text-lg font-medium text-gray-900 mb-2 text-center">
                        {{ emailResult.success ? 'Email Sent Successfully!' : 'Email Failed to Send' }}
                    </h3>
                    
                    <div class="text-center">
                        <p v-if="emailResult.success" class="text-sm text-gray-600 mb-4">
                            The jobcard has been sent to <strong>{{ emailResult.email }}</strong>
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
                            <option :value="null">Use System Template (Default)</option>
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
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import EmailRecipientsInput from '@/components/EmailRecipientsInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import jobcards from '@/routes/jobcards';
import invoices from '@/routes/invoices';
import products from '@/routes/products';
import customers from '@/routes/customers';
import TimeTracking from '@/components/TimeTracking.vue';

const page = usePage();
const isLimitedUser = computed(() => (page.props.auth as any)?.user?.user_type === 'limited');

interface Product {
    id: number;
    name: string;
}

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number | null;
    total: number | null;
    product_id?: number | null;
    product?: Product | null;
    formatted_unit_price: string;
    formatted_total: string;
}

interface Customer {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
}

interface TimeEntry {
    id: number;
    date: string;
    start_time: string | null;
    end_time: string | null;
    duration_minutes: number;
    hourly_rate: number | null;
    is_billable: boolean;
    description: string | null;
    status: string;
    formatted_duration: string;
    formatted_total_amount: string;
    user?: {
        id: number;
        name: string;
    };
}

interface AssignedUser {
    id: number;
    name: string;
}

interface AssignedTeam {
    id: number;
    name: string;
}

interface Jobcard {
    id: number;
    invoice_id?: number;
    job_number: string;
    order_number?: string | null;
    email?: string | null;
    phone?: string | null;
    title: string;
    description: string | null;
    status: string;
    assigned_to_user_id: number | null;
    assigned_to_team_id: number | null;
    assigned_user: AssignedUser | null;
    assigned_team: AssignedTeam | null;
    start_date: string | null;
    due_date: string | null;
    completed_date: string | null;
    subtotal: number | null;
    tax_rate: number | null;
    tax_amount: number | null;
    total: number | null;
    formatted_total: string;
    notes: string | null;
    terms_conditions: string | null;
    created_at: string;
    updated_at: string;
    customer: Customer;
    line_items: LineItem[];
    time_entries?: TimeEntry[];
}

interface Props {
    jobcard: Jobcard;
    canEditCompleted: boolean;
    pdfTemplates?: Array<{ id: number; name: string; module: string; is_default: boolean }>;
    defaultTemplateId?: number | null;
    runningTimer?: TimeEntry | null;
    timeSummary?: {
        total_hours: number;
        billable_hours: number;
        total_amount: number;
    };
}

const props = defineProps<Props>();
const roundingAdjustment = computed(() => {
    return (props.jobcard.line_items || []).reduce((sum, item) => {
        if ((item.description || '').trim().toLowerCase() !== 'rounding adjustment') {
            return sum;
        }
        return sum + (Number(item.total) || (Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
    }, 0);
});

// Status options for the status bar
const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'pending', label: 'Pending' },
    { value: 'in_progress', label: 'In Progress' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];

// Computed property to check if user can edit the jobcard
const canEditJobcard = computed(() => {
    if (props.jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
});

// Computed property to check if user can delete the jobcard
const canDeleteJobcard = computed(() => {
    if (props.jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
});

// Function to check if user can update to a specific status
const canUpdateStatus = (status: string) => {
    if (status === props.jobcard.status) {
        return false; // Can't update to the same status
    }
    
    // If changing from completed status, check permission
    if (props.jobcard.status === 'completed' && !props.canEditCompleted) {
        return false;
    }
    
    return true;
};

// Function to update jobcard status
const updateStatus = (status: string) => {
    if (!canUpdateStatus(status)) {
        return;
    }
    
    router.patch(jobcards.updateStatus(props.jobcard.id).url, {
        status: status
    }, {
        onSuccess: () => {
            // Status will be updated via Inertia
        },
        onError: (errors) => {
            console.error('Error updating status:', errors);
        }
    });
};

const deleteJobcard = () => {
    if (confirm(`Are you sure you want to delete jobcard ${props.jobcard.job_number}?`)) {
        router.delete(jobcards.destroy(props.jobcard.id).url);
    }
};

const getStatusBadgeClass = (status: string) => {
    if (!status) return 'bg-gray-100 text-gray-800';
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        pending: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return classes[status as keyof typeof classes] || classes.draft;
};

const formatStatus = (status: string) => {
    if (!status) return '';
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
};

const formatDateTime = (dateTime: string) => {
    if (!dateTime) return '';
    return new Date(dateTime).toLocaleString();
};

// Email functionality
const showEmailModal = ref(false);
const showResultDialog = ref(false);
const showTemplateModal = ref(false);
const selectedTemplateId = ref<number | null>(props.defaultTemplateId ?? null);
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});

function parseInitialEmails(str: string | null | undefined): string[] {
    if (!str) return [];
    return str.split(',').map(s => s.trim()).filter(Boolean);
}

const initialEmailStr = (props.jobcard as any).recipient_email || props.jobcard.email || props.jobcard.customer?.email || '';
const emailRecipients = ref<string[]>(parseInitialEmails(initialEmailStr));
const emailRecipientError = ref('');

watch(showEmailModal, (open) => {
    if (open) {
        const str = (props.jobcard as any).recipient_email || props.jobcard.email || props.jobcard.customer?.email || '';
        emailRecipients.value = parseInitialEmails(str);
        emailRecipientError.value = '';
    }
});

const emailForm = useForm({
    email: initialEmailStr,
    message: '',
    template_id: null as number | null,
});

const downloadPDF = () => {
    const url = new URL(jobcards.print(props.jobcard.id).url, window.location.origin);
    if (selectedTemplateId.value) {
        url.searchParams.set('template_id', selectedTemplateId.value.toString());
    }
    window.open(url.toString(), '_blank');
    showTemplateModal.value = false;
    selectedTemplateId.value = null;
};

const convertToInvoice = () => {
    if (confirm('Are you sure you want to convert this jobcard to an invoice?')) {
        router.post(jobcards.convertToInvoice(props.jobcard.id).url);
    }
};

const sendEmail = () => {
    emailRecipientError.value = '';
    if (emailRecipients.value.length === 0) {
        emailRecipientError.value = 'Please add at least one recipient';
        return;
    }
    emailForm.email = emailRecipients.value.join(', ');
    // Set template_id if default template exists and no template is selected
    if (!emailForm.template_id && props.defaultTemplateId) {
        emailForm.template_id = props.defaultTemplateId;
    }
    
    emailForm.post(jobcards.email(props.jobcard.id).url, {
        onSuccess: (page) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: true,
                email: emailForm.email,
                message: page.props.flash?.success || 'Email sent successfully'
            };
            showResultDialog.value = true;
            emailForm.reset();
            emailForm.template_id = null;
        },
        onError: (errors) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: false,
                email: emailForm.email,
                message: errors.email?.[0] || errors.message?.[0] || 'An error occurred while sending the email'
            };
            showResultDialog.value = true;
        },
        onFinish: () => {
            // Reset form processing state
        }
    });
};
</script>
