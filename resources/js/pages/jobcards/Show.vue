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
                                <template v-if="hasJobcardEdit">
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
                                </template>
                                <span
                                    v-else
                                    :class="getStatusBadgeClass(props.jobcard.status)"
                                    class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"
                                >
                                    {{ formatStatus(props.jobcard.status) }}
                                </span>
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
                            v-if="canEmailJobcard"
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
                            v-if="hasDeliveryNotesCreate"
                            @click="createDeliveryNote"
                            class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2"
                        >
                            Create Delivery Note
                        </button>
                        <template v-if="!isLimitedUser">
                            <button
                                v-if="hasJobcardEdit && !props.convertedQuoteId"
                                @click="convertToQuote"
                                class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                            >
                                Convert to Quote
                            </button>
                            <Link
                                v-else-if="props.convertedQuoteId != null"
                                :href="quotes.show(props.convertedQuoteId).url"
                                class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                            >
                                View Quote
                            </Link>
                            <button
                                v-if="hasJobcardEdit && !props.jobcard.invoice_id"
                                @click="convertToInvoice"
                                class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                            >
                                Convert to Invoice
                            </button>
                            <Link
                                v-else-if="props.jobcard.invoice_id != null"
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
                    <!-- Status Time Tracker -->
                    <div
                        v-if="props.statusDurations && totalStatusDurationMinutes > 0"
                        class="rounded-lg bg-white border border-gray-200 shadow-sm"
                    >
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Status Time Tracker</h2>
                                    <p class="text-sm text-gray-600">Time spent in each jobcard status</p>
                                </div>
                                <button
                                    type="button"
                                    @click="showStatusTimers = !showStatusTimers"
                                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    {{ showStatusTimers ? 'Hide Status Timers' : 'Show Status Timers' }}
                                </button>
                            </div>
                        </div>
                        <div v-if="showStatusTimers" class="p-6 space-y-4">
                            <div class="flex h-6 rounded-full overflow-hidden shadow-sm">
                                <template v-for="status in statusDurationOrder" :key="`bar-${status}`">
                                    <div
                                        v-if="props.statusDurations[status] && props.statusDurations[status].minutes > 0"
                                        :style="{
                                            width: ((props.statusDurations[status].minutes / totalStatusDurationMinutes) * 100) + '%',
                                            backgroundColor: statusBarColor(status),
                                        }"
                                        :title="`${formatStatus(status)}: ${props.statusDurations[status].formatted}`"
                                        class="h-full transition-all duration-300 hover:opacity-80"
                                    />
                                </template>
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <template v-for="status in statusDurationOrder" :key="`legend-${status}`">
                                    <div
                                        v-if="props.statusDurations[status] && props.statusDurations[status].minutes > 0"
                                        class="flex items-center gap-1.5 text-xs"
                                    >
                                        <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: statusBarColor(status) }"></span>
                                        <span class="text-gray-600 font-medium">{{ formatStatus(status) }}:</span>
                                        <span class="text-gray-900 font-semibold">{{ props.statusDurations[status].formatted }}</span>
                                    </div>
                                </template>
                            </div>

                            <div v-if="props.statusTransitions && props.statusTransitions.length > 0" class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-100 text-left">
                                            <th class="px-3 py-2 text-xs font-medium text-gray-500">From</th>
                                            <th class="px-3 py-2 text-xs font-medium text-gray-500">To</th>
                                            <th class="px-3 py-2 text-xs font-medium text-gray-500">Date / Time</th>
                                            <th class="px-3 py-2 text-xs font-medium text-gray-500">Changed By</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(transition, idx) in props.statusTransitions" :key="idx" class="border-t">
                                            <td class="px-3 py-2">
                                                <span
                                                    v-if="transition.from_status"
                                                    :class="getStatusBadgeClass(transition.from_status)"
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                >
                                                    {{ formatStatus(transition.from_status) }}
                                                </span>
                                                <span v-else class="text-gray-400 text-xs">Created</span>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span
                                                    :class="getStatusBadgeClass(transition.to_status)"
                                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                >
                                                    {{ formatStatus(transition.to_status) }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 font-mono text-xs">{{ transition.transitioned_at ? formatDateTime(transition.transitioned_at) : '-' }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ transition.user_name }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div v-else class="px-6 py-4 text-sm text-gray-500">
                            Status timers are hidden in normal view.
                        </div>
                    </div>
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

                    <!-- Photos & Videos -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">Photos & Videos</h2>
                                    <p class="text-sm text-gray-600">Site photos and video evidence for this jobcard</p>
                                </div>
                                <div v-if="canEditJobcard" class="flex items-center gap-2">
                                    <input
                                        ref="attachmentInput"
                                        type="file"
                                        class="hidden"
                                        accept="image/*,video/mp4,video/quicktime,video/webm,video/x-msvideo,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm"
                                        multiple
                                        @change="onAttachmentFilesSelected"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                                        :disabled="uploadingAttachments"
                                        @click="attachmentInput?.click()"
                                    >
                                        {{ uploadingAttachments ? 'Uploading…' : 'Add photo/video' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="p-6">
                            <p v-if="attachmentUploadError" class="mb-3 text-sm text-red-600">{{ attachmentUploadError }}</p>
                            <div v-if="attachments.length === 0" class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                                No photos or videos attached yet.
                            </div>
                            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div v-for="attachment in attachments" :key="attachment.id" class="space-y-2 rounded-md border border-gray-200 p-3">
                                    <video
                                        v-if="attachmentKind(attachment.type) === 'video'"
                                        :src="attachment.url"
                                        controls
                                        class="max-h-[280px] w-full rounded-md bg-black"
                                    />
                                    <img
                                        v-else
                                        :src="attachment.url"
                                        :alt="attachment.original_name || 'Jobcard attachment'"
                                        class="max-h-[280px] w-full rounded-md object-contain bg-gray-50"
                                    />
                                    <div class="flex items-center justify-between gap-2">
                                        <a :href="attachment.url" target="_blank" class="truncate text-sm text-blue-600 hover:underline">
                                            {{ attachment.original_name || 'Open' }}
                                        </a>
                                        <button
                                            v-if="canEditJobcard"
                                            type="button"
                                            class="shrink-0 text-xs font-medium text-red-600 hover:text-red-800"
                                            @click="deleteAttachment(attachment.id)"
                                        >
                                            Remove
                                        </button>
                                    </div>
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
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Supplier</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Cost</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Price</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Discount</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">Tax</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Profit</th>
                                            <th v-if="!isLimitedUser" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <template v-for="group in groupedVisibleJobcardLineItems" :key="`group-${group.groupId}`">
                                            <tr class="bg-gray-100">
                                                <td :colspan="isLimitedUser ? 2 : 9" class="px-3 py-2 text-xs font-semibold uppercase tracking-wide text-gray-700">
                                                    {{ group.groupName }}
                                                </td>
                                            </tr>
                                            <tr v-for="item in group.items" :key="item.id">
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
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <span v-if="item.supplier?.name">{{ item.supplier.name }}</span>
                                                <span v-else class="text-gray-400">&mdash;</span>
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-gray-900 text-right">
                                                R{{ (Number(item.cost) || 0).toFixed(2) }}
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
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm text-right" :class="calculateLineProfit(item) >= 0 ? 'text-green-700' : 'text-red-700'">
                                                R{{ calculateLineProfit(item).toFixed(2) }}
                                            </td>
                                            <td v-if="!isLimitedUser" class="px-3 py-3 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                {{ item.formatted_total }}
                                            </td>
                                            </tr>
                                        </template>
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

                    <div v-if="props.signatures && props.signatures.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Signatures</h2>
                            <p class="text-sm text-gray-600">Captured signatures for this jobcard</p>
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
                                    <label class="block text-sm font-medium text-gray-700">Priority</label>
                                    <span
                                        :class="getPriorityBadgeClass(props.jobcard.priority)"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                    >
                                        {{ formatPriorityLabel(props.jobcard.priority) }}
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

                    <div v-if="props.jobcard.source || props.jobcard.source_type" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Source</h2>
                            <p class="text-sm text-gray-600">Original document this jobcard was created from</p>
                        </div>
                        <div class="p-6">
                            <div class="text-sm">
                                <div><span class="font-medium">Type:</span> {{ props.jobcard.source_type || '-' }}</div>
                                <div v-if="props.jobcard.source_type === 'quote' && props.jobcard.source_id">
                                    <Link
                                        :href="quotes.show(props.jobcard.source_id).url"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View Original Quote
                                    </Link>
                                    <span v-if="props.jobcard.source?.quote_number" class="ml-2 text-gray-500">
                                        ({{ props.jobcard.source.quote_number }})
                                    </span>
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
                                <div class="flex justify-between">
                                    <span class="text-sm text-gray-600">Total Profit:</span>
                                    <span class="text-sm font-medium" :class="totalProfit >= 0 ? 'text-green-700' : 'text-red-700'">
                                        R{{ totalProfit.toFixed(2) }}
                                    </span>
                                </div>
                                <template v-if="hasPurchaseOrdersList">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Purchasing Total:</span>
                                        <span class="text-sm font-medium">R{{ purchaseOrdersTotal.toFixed(2) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600">Total vs Purchasing:</span>
                                        <span class="text-sm font-medium" :class="purchaseVariance >= 0 ? 'text-green-700' : 'text-red-700'">
                                            R{{ purchaseVariance.toFixed(2) }}
                                        </span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div v-if="!isLimitedUser && hasPurchaseOrdersList && props.relatedPurchaseOrders && props.relatedPurchaseOrders.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Related Purchase Orders</h2>
                            <p class="text-sm text-gray-600">Purchase orders linked to this jobcard</p>
                        </div>
                        <div class="p-6 space-y-2">
                            <div v-for="po in props.relatedPurchaseOrders" :key="po.id" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <Link :href="purchaseOrders.show(po.id).url" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ po.po_number }}
                                    </Link>
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 capitalize">{{ po.status }}</span>
                                </div>
                                <span class="text-sm font-medium text-gray-900">R{{ Number(po.total || 0).toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!isLimitedUser && hasDeliveryNotesView && props.relatedDeliveryNotes && props.relatedDeliveryNotes.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Related Delivery Notes</h2>
                            <p class="text-sm text-gray-600">Delivery notes linked to this jobcard</p>
                        </div>
                        <div class="p-6 space-y-2">
                            <div v-for="note in props.relatedDeliveryNotes" :key="note.id" class="flex items-center justify-between rounded border border-gray-200 px-3 py-2">
                                <div class="flex items-center gap-3">
                                    <Link :href="deliveryNotes.show(note.id).url" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ note.delivery_note_number }}
                                    </Link>
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-700 capitalize">{{ note.status }}</span>
                                </div>
                                <span class="text-sm text-gray-600">{{ note.delivery_date || '—' }}</span>
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
                                    v-if="canEditJobcard"
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
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailRecipientsInput from '@/components/EmailRecipientsInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import jobcards from '@/routes/jobcards';
import invoices from '@/routes/invoices';
import quotes from '@/routes/quotes';
import purchaseOrders from '@/routes/purchase-orders';
import deliveryNotes from '@/routes/delivery-notes';
import products from '@/routes/products';
import customers from '@/routes/customers';
import TimeTracking from '@/components/TimeTracking.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { getCsrfToken } from '@/lib/csrf';
import { toast } from 'vue-sonner';
import {
    JOBCARD_STATUS_KEYS,
    jobcardStatusBadgeClass,
    jobcardStatusBarColor,
} from '@/lib/jobcardStatuses';

const page = usePage();
const { formatDate: formatLocalizedDate, formatDateTime: formatLocalizedDateTime } = useDateTimeFormat();
const isLimitedUser = computed(() => (page.props.auth as any)?.user?.user_type === 'limited');
const hasJobcardEdit = useAuthAbility('jobcards', 'edit');
const hasJobcardDelete = useAuthAbility('jobcards', 'delete');
const canEmailJobcard = useAuthAbility('jobcards', 'view');
const hasPurchaseOrdersCreate = useAuthAbility('purchase-orders', 'create');
const hasPurchaseOrdersList = useAuthAbility('purchase-orders', 'list');
const hasDeliveryNotesCreate = useAuthAbility('delivery-notes', 'create');
const hasDeliveryNotesView = useAuthAbility('delivery-notes', 'view');

interface JobcardAttachment {
    id: number;
    url: string;
    type: string | null;
    original_name: string | null;
    created_at?: string | null;
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
    unit_price: number | null;
    cost?: number | null;
    total: number | null;
    product_id?: number | null;
    supplier_id?: number | null;
    line_group_id?: number | null;
    product?: Product | null;
    supplier?: { id: number; name: string } | null;
    formatted_unit_price: string;
    formatted_total: string;
    discount_amount?: number;
    discount_percentage?: number;
    tax_rate?: { id: number; name: string; rate: number } | null;
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
    source_type?: string | null;
    source_id?: number | null;
    job_number: string;
    order_number?: string | null;
    email?: string | null;
    phone?: string | null;
    title: string;
    description: string | null;
    status: string;
    priority?: string | null;
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
    discount_amount?: number | null;
    formatted_total: string;
    notes: string | null;
    terms_conditions: string | null;
    created_at: string;
    updated_at: string;
    customer: Customer;
    line_items: LineItem[];
    line_groups?: { id: number; name: string; sort_order?: number }[];
    time_entries?: TimeEntry[];
    source?: {
        id: number;
        quote_number?: string;
    } | null;
}

interface Props {
    jobcard: Jobcard;
    canEditCompleted: boolean;
    attachments?: JobcardAttachment[];
    statusOptions: Array<{ value: string; label: string }>;
    pdfTemplates?: Array<{ id: number; name: string; module: string; is_default: boolean }>;
    defaultTemplateId?: number | null;
    convertedQuoteId?: number | null;
    relatedPurchaseOrders?: Array<{
        id: number;
        po_number: string;
        status: string;
        total: number;
        created_at: string | null;
    }>;
    purchaseOrdersTotal?: number;
    relatedDeliveryNotes?: Array<{
        id: number;
        delivery_note_number: string;
        status: string;
        delivery_date: string | null;
        created_at: string | null;
    }>;
    runningTimer?: TimeEntry | null;
    timeSummary?: {
        total_hours: number;
        billable_hours: number;
        total_amount: number;
    };
    statusDurations?: Record<string, { minutes: number; formatted: string }>;
    statusTransitions?: Array<{
        from_status: string | null;
        to_status: string;
        transitioned_at: string | null;
        user_name: string;
    }>;
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

const attachments = computed(() => props.attachments ?? []);
const attachmentInput = ref<HTMLInputElement | null>(null);
const uploadingAttachments = ref(false);
const attachmentUploadError = ref('');

function attachmentKind(type: string | null): 'video' | 'image' {
    if (!type) return 'image';
    if (type === 'video' || type.endsWith(':video') || type.startsWith('video/')) return 'video';
    return 'image';
}

async function onAttachmentFilesSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files || []);
    input.value = '';
    if (files.length === 0) return;

    uploadingAttachments.value = true;
    attachmentUploadError.value = '';
    try {
        const formData = new FormData();
        files.forEach((file) => formData.append('attachments[]', file));

        const response = await fetch(`/jobcards/${props.jobcard.id}/attachments`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = (data as { message?: string; errors?: Record<string, string[]> }).message
                || Object.values((data as { errors?: Record<string, string[]> }).errors || {}).flat()[0]
                || 'Upload failed.';
            throw new Error(message);
        }

        toast.success('Attachments uploaded');
        router.reload({ only: ['attachments'] });
    } catch (error) {
        attachmentUploadError.value = error instanceof Error ? error.message : 'Upload failed.';
        toast.error(attachmentUploadError.value);
    } finally {
        uploadingAttachments.value = false;
    }
}

function deleteAttachment(attachmentId: number) {
    if (!confirm('Remove this attachment?')) return;

    router.delete(`/jobcards/${props.jobcard.id}/attachments/${attachmentId}`, {
        preserveScroll: true,
        onSuccess: () => toast.success('Attachment removed'),
        onError: () => toast.error('Could not remove attachment'),
    });
}

const isRoundingAdjustmentLine = (item: LineItem) => {
    return (item.description || '').trim().toLowerCase() === 'rounding adjustment';
};

const visibleJobcardLineItems = computed(() => {
    return (props.jobcard.line_items || []).filter((item) => !isRoundingAdjustmentLine(item));
});

const calculateLineProfit = (item: LineItem) => {
    const lineTotal = Number(item.total) || 0;
    const lineCost = (Number(item.quantity) || 0) * (Number(item.cost) || 0);
    return lineTotal - lineCost;
};

const totalProfit = computed(() => {
    return visibleJobcardLineItems.value.reduce((sum, item) => sum + calculateLineProfit(item), 0);
});

const groupedVisibleJobcardLineItems = computed(() => {
    const groups = [...(props.jobcard.line_groups || [])].sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
    const fallbackGroupId = groups[0]?.id ?? 1;
    const baseItems = visibleJobcardLineItems.value;
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
    return (props.jobcard.line_items || []).reduce((sum, item) => {
        if (!isRoundingAdjustmentLine(item)) {
            return sum;
        }
        return sum + (Number(item.total) || (Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
    }, 0);
});

const statusOptions = computed(() => props.statusOptions || []);
const statusDurationOrder = [...JOBCARD_STATUS_KEYS];
const showStatusTimers = ref(false);
const totalStatusDurationMinutes = computed(() =>
    Object.values(props.statusDurations || {}).reduce((sum, duration) => sum + (duration.minutes || 0), 0)
);
const statusBarColor = jobcardStatusBarColor;

// Computed property to check if user can edit the jobcard
const canEditJobcard = computed(() => {
    if (!hasJobcardEdit.value) {
        return false;
    }
    if (props.jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
});
const canSignDocument = computed(() => !!props.documentSigningEnabled && canEditJobcard.value);
const purchaseOrdersTotal = computed(() => Number(props.purchaseOrdersTotal) || 0);
const purchaseVariance = computed(() => (Number(props.jobcard.total) || 0) - purchaseOrdersTotal.value);

// Computed property to check if user can delete the jobcard
const canDeleteJobcard = computed(() => {
    if (!hasJobcardDelete.value) {
        return false;
    }
    if (props.jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
});

// Function to check if user can update to a specific status
const canUpdateStatus = (status: string) => {
    if (!hasJobcardEdit.value) {
        return false;
    }
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

const getPriorityBadgeClass = (priority: string | null | undefined) => {
    const p = priority || 'normal';
    const classes: Record<string, string> = {
        low: 'bg-slate-100 text-slate-800',
        normal: 'bg-gray-100 text-gray-800',
        high: 'bg-orange-100 text-orange-900',
        urgent: 'bg-red-100 text-red-900',
    };
    return classes[p] ?? classes.normal;
};

const formatPriorityLabel = (priority: string | null | undefined) => {
    const p = priority || 'normal';
    const labels: Record<string, string> = {
        low: 'Low',
        normal: 'Normal',
        high: 'High',
        urgent: 'Urgent',
    };
    return labels[p] ?? p;
};

const getStatusBadgeClass = (status: string) => jobcardStatusBadgeClass(status || 'new');

const formatStatus = (code: string) => {
    if (!code) return '';
    const opt = props.statusOptions?.find((o) => o.value === code);
    if (opt) return opt.label;
    return code.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatDate = (date: string) => {
    if (!date) return '';
    return formatLocalizedDate(date);
};

const formatDateTime = (dateTime: string) => {
    if (!dateTime) return '';
    return formatLocalizedDateTime(dateTime);
};

// Email functionality
const showEmailModal = ref(false);
const showResultDialog = ref(false);
const showTemplateModal = ref(false);
const showSignModal = ref(false);
const selectedTemplateId = ref<number | null>(props.defaultTemplateId ?? null);
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

const convertToQuote = () => {
    if (confirm('Are you sure you want to convert this jobcard to a quote?')) {
        router.post(jobcards.convertToQuote(props.jobcard.id).url);
    }
};

const createPurchaseOrder = () => {
    window.location.href = `${purchaseOrders.create().url}?source_type=jobcard&source_id=${props.jobcard.id}`;
};

const createDeliveryNote = () => {
    window.location.href = `${deliveryNotes.create().url}?jobcard_id=${props.jobcard.id}`;
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
    signForm.post(`/jobcards/${props.jobcard.id}/sign`, {
        preserveScroll: true,
        onSuccess: () => {
            showSignModal.value = false;
            router.reload({ only: ['signatures'] });
        },
    });
};
</script>
