<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import customers from '@/routes/customers';
import contacts from '@/routes/contacts';
import VersionHistory from '@/components/VersionHistory.vue';
import { ref, watch } from 'vue';

interface Contact {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    position?: string | null;
    is_primary: boolean;
}

interface SMSActivity {
    id: number;
    phone_number: string;
    message: string;
    status: 'sent' | 'failed' | 'pending';
    error_message?: string | null;
    bulksms_reference?: string | null;
    created_at: string;
    user: {
        id: number;
        name: string;
    };
}

const props = defineProps<{
    customer: {
        id: number
        name: string
        email: string
        phone?: string | null
        address?: string | null
        city?: string | null
        country?: string | null
        terms?: string | null
        account_code?: string | null
        notes?: string | null
        created_at?: string
        updated_at?: string
    }
    contacts: {
        data: Contact[]
        links: { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
    smsActivities: {
        data: SMSActivity[]
        links: { url: string | null; label: string; active: boolean }[]
        current_page: number
        last_page: number
        per_page: number
        total: number
    }
    filters: {
        contact_search?: string
        contacts_per_page?: number
        sms_search?: string
        sms_status?: string
        sms_per_page?: number
    }
}>()

// SMS functionality
const showSMSModal = ref(false);
const showSMSResultModal = ref(false);
const smsResult = ref<{ success: boolean; message: string } | null>(null);

const smsForm = useForm({
    message: '',
});

const openSMSModal = () => {
    smsForm.message = '';
    showSMSModal.value = true;
};

const sendSMS = () => {
    smsForm.post(customers.sendSMS(props.customer.id).url, {
        onSuccess: (page) => {
            showSMSModal.value = false;
            smsForm.reset();
            
            // Show success result
            smsResult.value = {
                success: true,
                message: page.props.flash?.success || 'SMS sent successfully!'
            };
            showSMSResultModal.value = true;
        },
        onError: (errors) => {
            showSMSModal.value = false;
            
            // Show error result
            const errorMessage = errors.message || 'Failed to send SMS. Please try again.';
            smsResult.value = {
                success: false,
                message: errorMessage
            };
            showSMSResultModal.value = true;
        },
    });
};

const closeSMSResultModal = () => {
    showSMSResultModal.value = false;
    smsResult.value = null;
};

// Filtering and pagination
const contactSearch = ref(String(props.filters?.contact_search ?? ''));
const contactsPerPage = ref(Number(props.filters?.contacts_per_page ?? 5));
const smsSearch = ref(String(props.filters?.sms_search ?? ''));
const smsStatus = ref(String(props.filters?.sms_status ?? ''));
const smsPerPage = ref(Number(props.filters?.sms_per_page ?? 5));

const updateFilters = () => {
    // Ensure customer ID is valid
    if (!props.customer?.id) {
        console.warn('Customer ID is not available');
        return;
    }
    
    const params: Record<string, string> = {};
    
    if (contactSearch.value && typeof contactSearch.value === 'string' && contactSearch.value.trim()) {
        params.contact_search = contactSearch.value.trim();
    }
    
    if (contactsPerPage.value && contactsPerPage.value !== 5) {
        params.contacts_per_page = contactsPerPage.value.toString();
    }
    
    if (smsSearch.value && typeof smsSearch.value === 'string' && smsSearch.value.trim()) {
        params.sms_search = smsSearch.value.trim();
    }
    
    if (smsStatus.value && typeof smsStatus.value === 'string' && smsStatus.value.trim()) {
        params.sms_status = smsStatus.value.trim();
    }
    
    if (smsPerPage.value && smsPerPage.value !== 5) {
        params.sms_per_page = smsPerPage.value.toString();
    }
    
    router.get(customers.show(props.customer.id).url, params, {
        preserveState: true,
        replace: true,
    });
};

// Watch for filter changes
watch([contactSearch, contactsPerPage, smsSearch, smsStatus, smsPerPage], () => {
    // Only update filters if customer is available
    if (props.customer?.id) {
        updateFilters();
    }
}, { debounce: 300 });
</script>

<template>
    <Head :title="`Customer • ${props.customer.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Customers', href: customers.index().url },
        { title: props.customer.name, href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
            <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.customer.name }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Customer Details</p>
                    </div>
                <div class="flex items-center gap-2">
                        <button 
                            v-if="props.customer.phone"
                            @click="openSMSModal"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Send SMS
                        </button>
                        <Link 
                            :href="customers.edit(props.customer.id).url" 
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Edit
                        </Link>
                        <Link 
                            :href="customers.index().url" 
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Customer Information Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Customer Information</h2>
                    <p class="text-sm text-gray-600">Basic details and contact information</p>
                </div>
                <div class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    Contact Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Email:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.email }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Phone:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.phone || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Account:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.account_code || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Terms:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.terms || 'COD' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Address Information
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <span class="text-sm font-medium text-gray-500 w-20">Address:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.address || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">City:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.city || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Country:</span>
                                        <span class="text-sm text-gray-900">{{ props.customer.country || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.customer.notes" class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Notes
                        </h3>
                        <p class="text-sm text-gray-700 whitespace-pre-line bg-gray-50 p-4 rounded-md">{{ props.customer.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Contacts Sub-Panel -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-blue-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Contacts
                            </h2>
                            <p class="text-sm text-gray-600">Manage customer contacts and relationships</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link 
                                :href="contacts.create().url + '?customer_id=' + props.customer.id" 
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                Add Contact
                            </Link>
                            <Link 
                                :href="contacts.index().url" 
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                View All
                            </Link>
                        </div>
                    </div>
                </div>
                <div class="p-6">

                    <!-- Contact Search and Per Page -->
                    <div class="mb-6 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Contacts</label>
                            <input
                                v-model="contactSearch"
                                type="text"
                                placeholder="Search by name, email, phone, or position..."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Results per page</label>
                            <select
                                v-model="contactsPerPage"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="5">5 per page</option>
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="(props.contacts?.data || []).length === 0" class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No contacts</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            <span v-if="contactSearch">No contacts found matching "{{ contactSearch }}".</span>
                            <span v-else>Get started by adding a new contact.</span>
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div 
                            v-for="contact in props.contacts?.data || []" 
                            :key="contact.id"
                            class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 p-4 hover:bg-gray-100 transition-colors"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                                        <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900">{{ contact.name }}</span>
                                            <span v-if="contact.is_primary" class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                                Primary
                                            </span>
                                        </div>
                                        <div class="text-sm text-gray-600">
                                            <span v-if="contact.position" class="font-medium">{{ contact.position }}</span>
                                            <span v-if="contact.email" class="ml-2">{{ contact.email }}</span>
                                            <span v-if="contact.phone" class="ml-2">{{ contact.phone }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link 
                                    :href="contacts.show(contact.id).url" 
                                    class="rounded-md border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    View
                                </Link>
                                <Link 
                                    :href="contacts.edit(contact.id).url" 
                                    class="rounded-md bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
                                >
                                    Edit
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Pagination -->
                    <div v-if="props.contacts?.last_page > 1" class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Showing {{ ((props.contacts?.current_page || 1) - 1) * (props.contacts?.per_page || 5) + 1 }} to 
                                {{ Math.min((props.contacts?.current_page || 1) * (props.contacts?.per_page || 5), props.contacts?.total || 0) }} 
                                of {{ props.contacts?.total || 0 }} contacts
                            </div>
                            <div class="flex items-center gap-1">
                                <Link
                                    v-for="link in props.contacts?.links || []"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :preserve-scroll="true"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md',
                                        link.active 
                                            ? 'bg-blue-600 text-white' 
                                            : link.url 
                                                ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' 
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SMS Activities Sub-Panel -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-green-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                SMS Activity
                            </h2>
                            <p class="text-sm text-gray-600">View and manage SMS communication history</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm font-medium text-green-800">
                                {{ props.smsActivities?.total || 0 }} message{{ (props.smsActivities?.total || 0) !== 1 ? 's' : '' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="p-6">

                    <!-- SMS Filters -->
                    <div class="mb-6 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Messages</label>
                            <input
                                v-model="smsSearch"
                                type="text"
                                placeholder="Search by message, phone, or status..."
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                            <select
                                v-model="smsStatus"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                            >
                                <option value="">All Statuses</option>
                                <option value="sent">Sent</option>
                                <option value="failed">Failed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Results per page</label>
                            <select
                                v-model="smsPerPage"
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                            >
                                <option value="5">5 per page</option>
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="(props.smsActivities?.data || []).length === 0" class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No SMS messages</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            <span v-if="smsSearch || smsStatus">No SMS messages found matching your filters.</span>
                            <span v-else>No SMS messages sent to this customer yet.</span>
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <div 
                            v-for="activity in props.smsActivities?.data || []" 
                            :key="activity.id"
                            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:shadow-md transition-shadow"
                        >
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full"
                                         :class="{
                                             'bg-green-100': activity.status === 'sent',
                                             'bg-red-100': activity.status === 'failed',
                                             'bg-yellow-100': activity.status === 'pending'
                                         }"
                                    >
                                        <svg class="h-4 w-4"
                                             :class="{
                                                 'text-green-600': activity.status === 'sent',
                                                 'text-red-600': activity.status === 'failed',
                                                 'text-yellow-600': activity.status === 'pending'
                                             }"
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                        </svg>
                                    </div>
                                    <div>
                                        <span 
                                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                            :class="{
                                                'bg-green-100 text-green-800': activity.status === 'sent',
                                                'bg-red-100 text-red-800': activity.status === 'failed',
                                                'bg-yellow-100 text-yellow-800': activity.status === 'pending'
                                            }"
                                        >
                                            {{ activity.status.charAt(0).toUpperCase() + activity.status.slice(1) }}
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ new Date(activity.created_at).toLocaleString() }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500">
                                    by {{ activity.user.name }}
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <span class="text-sm font-medium text-gray-700">To:</span>
                                <span class="text-sm text-gray-600 ml-1 font-mono">{{ activity.phone_number }}</span>
                            </div>
                            
                            <div class="text-sm text-gray-700 bg-gray-50 p-3 rounded-md border">
                                {{ activity.message }}
                            </div>

                            <div v-if="activity.error_message" class="mt-3 text-sm text-red-600 bg-red-50 p-3 rounded-md border border-red-200">
                                <span class="font-medium">Error:</span> {{ activity.error_message }}
                            </div>

                            <div v-if="activity.bulksms_reference" class="mt-3 text-xs text-gray-500 bg-gray-50 p-2 rounded">
                                <span class="font-medium">Reference:</span> {{ activity.bulksms_reference }}
                            </div>
                        </div>
                    </div>

                    <!-- SMS Pagination -->
                    <div v-if="props.smsActivities?.last_page > 1" class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-500">
                                Showing {{ ((props.smsActivities?.current_page || 1) - 1) * (props.smsActivities?.per_page || 5) + 1 }} to 
                                {{ Math.min((props.smsActivities?.current_page || 1) * (props.smsActivities?.per_page || 5), props.smsActivities?.total || 0) }} 
                                of {{ props.smsActivities?.total || 0 }} messages
                            </div>
                            <div class="flex items-center gap-1">
                                <Link
                                    v-for="link in props.smsActivities?.links || []"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :preserve-scroll="true"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md',
                                        link.active 
                                            ? 'bg-green-600 text-white' 
                                            : link.url 
                                                ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' 
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Version History Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-blue-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Version History
                    </h2>
                    <p class="text-sm text-gray-600">View and restore previous versions of this record</p>
                </div>
                <div class="p-6">
                    <VersionHistory 
                        :model-type="'App\\Models\\Customer'"
                        :model-id="props.customer.id"
                    />
                </div>
            </div>

            <!-- Footer Information -->
            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Created: {{ new Date(props.customer.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>Updated: {{ new Date(props.customer.updated_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Modal -->
        <div v-if="showSMSModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Send SMS to {{ props.customer.name }}</h3>
                
                <form @submit.prevent="sendSMS">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message (max 160 characters)</label>
                        <textarea
                            v-model="smsForm.message"
                            rows="4"
                            class="w-full rounded border px-3 py-2"
                            placeholder="Enter your SMS message..."
                            maxlength="160"
                            required
                        ></textarea>
                        <div class="text-xs text-gray-500 mt-1">{{ smsForm.message.length }}/160 characters</div>
                        <div v-if="smsForm.errors.message" class="text-sm text-red-600">{{ smsForm.errors.message }}</div>
                    </div>
                    
                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="button"
                            @click="showSMSModal = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="smsForm.processing"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50"
                        >
                            {{ smsForm.processing ? 'Sending...' : 'Send SMS' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- SMS Result Modal -->
        <div v-if="showSMSResultModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <div class="flex items-center mb-4">
                    <div v-if="smsResult?.success" class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <div v-else class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center mr-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold" :class="smsResult?.success ? 'text-green-800' : 'text-red-800'">
                        {{ smsResult?.success ? 'SMS Sent Successfully!' : 'SMS Failed to Send' }}
                    </h3>
                </div>
                
                <div class="mb-6">
                    <p class="text-gray-700" :class="smsResult?.success ? 'text-green-700' : 'text-red-700'">
                        {{ smsResult?.message }}
                    </p>
                </div>
                
                <div class="flex justify-end">
                    <button
                        @click="closeSMSResultModal"
                        class="rounded-md px-4 py-2 text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2"
                        :class="smsResult?.success ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' : 'bg-red-600 hover:bg-red-700 focus:ring-red-500'"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>

    </AppLayout>
</template>


