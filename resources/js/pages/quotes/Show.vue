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
                        <button
                            @click="downloadPDF"
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
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.customer?.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Email</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.customer?.email || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Phone</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.customer?.phone || '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500">Address</label>
                                    <p class="mt-1 text-sm text-gray-900">{{ props.quote.customer?.address || '-' }}</p>
                                </div>
                            </div>
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Quantity
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Unit Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Total
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="item in props.quote.line_items" :key="item.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">{{ item.description }}</div>
                                            <div v-if="item.product" class="text-xs text-gray-500">
                                                Product: {{ item.product.name }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ item.formatted_unit_price }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
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
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Created Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.quote.created_at) }}</p>
                            </div>
                            <div v-if="props.quote.expiry_date">
                                <label class="block text-sm font-medium text-gray-500">Expiry Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(props.quote.expiry_date) }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Description</label>
                                <p class="mt-1 text-sm text-gray-900">{{ props.quote.description || '-' }}</p>
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
                                <span class="text-sm text-gray-500">Tax ({{ props.quote.tax_rate || 0 }}%):</span>
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
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import quotes from '@/routes/quotes';
import invoices from '@/routes/invoices';
import { ref, computed } from 'vue';

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
    formatted_unit_price: string;
    formatted_total: string;
    product?: Product;
}

interface Quote {
    id: number;
    invoice_id?: number;
    quote_number: string;
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

const props = defineProps<{
    quote: Quote;
    canEditCompleted: boolean;
}>();

const showEmailModal = ref(false);
const showResultDialog = ref(false);
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

const emailForm = useForm({
    email: props.quote.customer?.email || '',
    message: '',
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

const downloadPDF = () => {
    window.open(quotes.downloadPdf(props.quote.id).url, '_blank');
};

const sendEmail = () => {
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
