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
                                    <label class="block text-sm font-medium text-gray-700">Customer</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.customer.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.customer.email }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                                    <p class="text-sm text-gray-900">{{ props.jobcard.customer.phone || '-' }}</p>
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
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Description
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Qty
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Unit Price
                                            </th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr v-for="item in props.jobcard.line_items" :key="item.id">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ item.description }}</div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ item.quantity }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                                                {{ item.formatted_unit_price }}
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
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
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Status</label>
                                    <span
                                        :class="getStatusBadgeClass(props.jobcard.status)"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                    >
                                        {{ formatStatus(props.jobcard.status) }}
                                    </span>
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
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
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
                                    <span class="text-sm text-gray-600">Tax ({{ props.jobcard.tax_rate || 0 }}%):</span>
                                    <span class="text-sm font-medium">R{{ (Number(props.jobcard.tax_amount) || 0).toFixed(2) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-3">
                                    <span class="text-base font-semibold">Total:</span>
                                    <span class="text-base font-semibold">{{ props.jobcard.formatted_total }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
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
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input
                                v-model="emailForm.email"
                                type="email"
                                required
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': emailForm.errors.email }"
                                placeholder="recipient@example.com"
                            />
                            <div v-if="emailForm.errors.email" class="text-red-500 text-sm mt-1">
                                {{ emailForm.errors.email }}
                            </div>
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
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import jobcards from '@/routes/jobcards';
import invoices from '@/routes/invoices';

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number | null;
    total: number | null;
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

interface Jobcard {
    id: number;
    invoice_id?: number;
    job_number: string;
    title: string;
    description: string | null;
    status: string;
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
}

interface Props {
    jobcard: Jobcard;
    canEditCompleted: boolean;
}

const props = defineProps<Props>();

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
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});

const emailForm = useForm({
    email: props.jobcard.customer?.email || '',
    message: '',
});

const downloadPDF = () => {
    window.location.href = jobcards.print(props.jobcard.id).url;
};

const convertToInvoice = () => {
    if (confirm('Are you sure you want to convert this jobcard to an invoice?')) {
        router.post(jobcards.convertToInvoice(props.jobcard.id).url);
    }
};

const sendEmail = () => {
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
