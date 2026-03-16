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
                        <div class="relative">
                            <button
                                v-if="!props.pdfTemplates || props.pdfTemplates.length === 0"
                                @click="downloadPDF('quotation')"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Download PDF
                            </button>
                            <div v-else>
                                <button
                                    @click="showDownloadDropdown = !showDownloadDropdown"
                                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex items-center gap-2"
                                >
                                    Download PDF
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <div
                                    v-if="showDownloadDropdown"
                                    @click.stop
                                    class="absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                                >
                                    <div class="py-1">
                                        <button
                                            @click="showTemplateModal = true; selectedPdfType = 'quotation'; showDownloadDropdown = false;"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Download Quotation
                                        </button>
                                        <button
                                            @click="showTemplateModal = true; selectedPdfType = 'proforma-invoice'; showDownloadDropdown = false;"
                                            class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                        >
                                            Download Proforma Invoice
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button
                            @click="showEmailModal = true"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Email
                        </button>
                        <button
                            @click="convertToJobcard"
                            class="rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2"
                        >
                            Convert to Jobcard
                        </button>
                        <button
                            v-if="!props.quote.invoice_id"
                            @click="convertToInvoice"
                            class="rounded-md bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                        >
                            Convert to Invoice
                        </button>
                        <Link
                            v-else
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
                        <span
                            v-else
                            class="rounded-md bg-gray-400 px-4 py-2 text-sm font-medium text-white cursor-not-allowed"
                            title="Cannot edit accepted quotes without permission"
                        >
                            Edit
                        </span>
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
                                    <tr v-for="item in props.quote.line_items" :key="item.id">
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
                            <div class="border-t border-gray-200 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-base font-semibold text-gray-900">Total:</span>
                                    <span class="text-base font-semibold text-gray-900">R{{ formatCurrency(props.quote.total) }}</span>
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
                                <option v-for="template in props.pdfTemplates.filter(t => t.module === emailForm.type)" :key="template.id" :value="template.id">
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
                            @click="downloadPDF(selectedPdfType || 'quotation')"
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
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import products from '@/routes/products';
import customers from '@/routes/customers';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

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
    product?: Product | null;
}

interface Quote {
    id: number;
    invoice_id?: number;
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
}

interface Props {
    quote: Quote;
    canEditCompleted: boolean;
    pdfTemplates?: Array<{ id: number; name: string; module: string; is_default: boolean }>;
    defaultQuoteTemplateId?: number | null;
    defaultProformaTemplateId?: number | null;
}

const props = defineProps<Props>();

const showEmailModal = ref(false);

watch(showEmailModal, (open) => {
    if (open) {
        const str = (props.quote as any).recipient_email || props.quote.email || props.quote.customer?.email || '';
        emailRecipients.value = parseInitialEmails(str);
        emailRecipientError.value = '';
    }
});
const showDownloadDropdown = ref(false);
const showResultDialog = ref(false);
const showTemplateModal = ref(false);
const selectedPdfType = ref<string | null>(null);
const selectedTemplateId = ref<number | null>(null);
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});

// Computed property to check if user can edit the quote
const canEditQuote = computed(() => {
    if (props.quote.status !== 'accepted') {
        return true;
    }
    return props.canEditCompleted;
});

function parseInitialEmails(str: string | null | undefined): string[] {
    if (!str) return [];
    return str.split(',').map(s => s.trim()).filter(Boolean);
}

const initialEmailStr = (props.quote as any).recipient_email || props.quote.email || props.quote.customer?.email || '';
const emailRecipients = ref<string[]>(parseInitialEmails(initialEmailStr));

const emailForm = useForm({
    email: initialEmailStr,
    message: '',
    type: 'quotation',
    template_id: null as number | null,
});

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'expired', label: 'Expired' },
];

const updateStatus = (status: string) => {
    router.patch(quotes.updateStatus(props.quote.id).url, {
        status: status,
    }, {
        preserveScroll: true,
    });
};

// Computed property to filter templates by selected PDF type
const filteredTemplates = computed(() => {
    if (!props.pdfTemplates || !selectedPdfType.value) {
        return [];
    }
    return props.pdfTemplates.filter(t => t.module === selectedPdfType.value);
});

// Initialize selected template ID based on PDF type
watch(selectedPdfType, (newType) => {
    if (newType === 'quotation') {
        selectedTemplateId.value = props.defaultQuoteTemplateId ?? null;
    } else if (newType === 'proforma-invoice') {
        selectedTemplateId.value = props.defaultProformaTemplateId ?? null;
    }
}, { immediate: true });

const downloadPDF = (type: string = 'quotation') => {
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

// Close dropdown when clicking outside
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.relative')) {
        showDownloadDropdown.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

const emailRecipientError = ref('');

const sendEmail = () => {
    emailRecipientError.value = '';
    if (emailRecipients.value.length === 0) {
        emailRecipientError.value = 'Please add at least one recipient';
        return;
    }
    emailForm.email = emailRecipients.value.join(', ');
    // Set template_id based on the selected type if no template is manually selected
    if (!emailForm.template_id) {
        if (emailForm.type === 'quotation' && props.defaultQuoteTemplateId) {
            emailForm.template_id = props.defaultQuoteTemplateId;
        } else if (emailForm.type === 'proforma-invoice' && props.defaultProformaTemplateId) {
            emailForm.template_id = props.defaultProformaTemplateId;
        }
    }
    
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

const formatStatus = (status: string) => {
    return status.replace('_', ' ').toUpperCase();
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const formatCurrency = (value: number | null | undefined) => {
    const numValue = Number(value) || 0;
    return numValue.toFixed(2);
};

const formatDateTime = (dateString: string) => {
    return new Date(dateString).toLocaleString();
};
</script>
