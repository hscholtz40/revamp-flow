<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailComposerModal from '@/components/EmailComposerModal.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import contacts from '@/routes/contacts';
import { computed, ref, watch } from 'vue';

interface EmailActivity {
    id: number;
    recipient_email: string;
    subject: string;
    email_type: string;
    related_type?: string | null;
    status: 'sent' | 'failed' | string;
    error_message?: string | null;
    sent_at?: string | null;
    created_at: string;
    user?: {
        id: number;
        name: string;
    } | null;
}

const canContactsEdit = useAuthAbility('contacts', 'edit');
const canContactsDelete = useAuthAbility('contacts', 'delete');

const props = defineProps<{
    contact: {
        id: number;
        name: string;
        email?: string | null;
        phone?: string | null;
        position?: string | null;
        notes?: string | null;
        is_primary: boolean;
        customer: {
            id: number;
            name: string;
        };
        created_at: string;
        updated_at: string;
    };
    emailActivities: {
        data: EmailActivity[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters?: {
        email_per_page?: number;
    };
}>();

const deleteContact = () => {
    if (!confirm(`Are you sure you want to delete contact "${props.contact.name}"?`)) {
        return;
    }
    router.delete(contacts.destroy(props.contact.id).url);
};

// SMS functionality
const showSMSModal = ref(false);
const showSMSResultModal = ref(false);
const smsResult = ref<{ success: boolean; message: string } | null>(null);
const showEmailModal = ref(false);

const smsForm = useForm({
    message: '',
});

const openSMSModal = () => {
    smsForm.message = '';
    smsForm.clearErrors();
    showSMSModal.value = true;
};

const sendSMS = () => {
    smsForm.post(contacts.sms(props.contact.id).url, {
        onSuccess: (page) => {
            showSMSModal.value = false;
            smsResult.value = {
                success: true,
                message: `SMS sent successfully to ${props.contact.name} at ${props.contact.phone}`
            };
            showSMSResultModal.value = true;
        },
        onError: (errors) => {
            if (errors.message) {
                smsResult.value = {
                    success: false,
                    message: errors.message
                };
                showSMSModal.value = false;
                showSMSResultModal.value = true;
            }
        }
    });
};

const closeSMSResultModal = () => {
    showSMSResultModal.value = false;
    smsResult.value = null;
};

const openEmailModal = () => {
    showEmailModal.value = true;
};

const emailSendUrl = computed(() => `/contacts/${props.contact.id}/send-email`);
const emailModalTitle = computed(() => `Send Email to ${props.contact.name}`);

const emailPerPage = ref(Number(props.filters?.email_per_page ?? 10));

watch(emailPerPage, () => {
    router.get(contacts.show(props.contact.id).url, {
        email_per_page: emailPerPage.value,
    }, {
        preserveState: true,
        replace: true,
    });
});
</script>

<template>
    <Head :title="`Contact • ${props.contact.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Contacts', href: contacts.index().url },
        { title: props.contact.name, href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.contact.name }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Contact Details</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="props.contact.email"
                            @click="openEmailModal"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Email
                        </button>
                        <button 
                            v-if="props.contact.phone"
                            @click="openSMSModal"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        >
                            Send SMS
                        </button>
                        <Link
                            v-if="canContactsEdit"
                            :href="contacts.edit(props.contact.id).url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Edit
                        </Link>
                        <button
                            v-if="canContactsDelete"
                            type="button"
                            @click="deleteContact"
                            class="rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            Delete
                        </button>
                        <Link 
                            :href="contacts.index().url" 
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Contact Information Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Contact Information</h2>
                    <p class="text-sm text-gray-600">Personal details and contact methods</p>
                </div>
                <div class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Personal Details
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Name:</span>
                                        <span class="text-sm text-gray-900">{{ props.contact.name }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Position:</span>
                                        <span class="text-sm text-gray-900">{{ props.contact.position || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Primary:</span>
                                        <span v-if="props.contact.is_primary" class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">
                                            Yes
                                        </span>
                                        <span v-else class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-800">
                                            No
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    Contact Methods
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Email:</span>
                                        <span class="text-sm text-gray-900">{{ props.contact.email || '-' }}</span>
                                    </div>
                                    <div class="flex items-center">
                                        <span class="text-sm font-medium text-gray-500 w-20">Phone:</span>
                                        <span class="text-sm text-gray-900">{{ props.contact.phone || '-' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Information Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Customer Information</h2>
                    <p class="text-sm text-gray-600">Associated customer details</p>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ props.contact.customer.name }}</div>
                                    <div class="text-sm text-gray-500">Customer</div>
                                </div>
                            </div>
                        </div>
                        <Link 
                            :href="`/customers/${props.contact.customer.id}`" 
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            View Customer
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Notes Section -->
            <div v-if="props.contact.notes" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                    <p class="text-sm text-gray-600">Additional contact information</p>
                </div>
                <div class="p-6">
                    <p class="text-gray-700 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.contact.notes }}</p>
                </div>
            </div>

            <!-- Email Activity Section -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-blue-50 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Email Activity</h2>
                            <p class="text-sm text-gray-600">Direct and document email history for this contact</p>
                        </div>
                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                            {{ props.emailActivities?.total || 0 }} email{{ (props.emailActivities?.total || 0) !== 1 ? 's' : '' }}
                        </span>
                    </div>
                </div>
                <div class="p-6">
                    <div class="mb-4 flex items-center justify-end">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Results per page</label>
                            <select
                                v-model="emailPerPage"
                                class="w-40 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="10">10 per page</option>
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="(props.emailActivities?.data || []).length === 0" class="py-8 text-center text-sm text-gray-500">
                        No emails sent to this contact yet.
                    </div>
                    <div v-else class="space-y-3">
                        <div
                            v-for="activity in props.emailActivities?.data || []"
                            :key="activity.id"
                            class="rounded-lg border border-gray-200 bg-white p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ activity.subject }}</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        To: {{ activity.recipient_email }} •
                                        {{ activity.related_type ? `${activity.related_type} email` : 'direct email' }}
                                    </div>
                                </div>
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                                    :class="activity.status === 'sent' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                >
                                    {{ activity.status }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                {{ new Date(activity.sent_at || activity.created_at).toLocaleString() }}
                                <span v-if="activity.user?.name"> • by {{ activity.user.name }}</span>
                            </div>
                            <div v-if="activity.error_message" class="mt-2 text-xs text-red-600">
                                {{ activity.error_message }}
                            </div>
                        </div>
                    </div>

                    <div v-if="props.emailActivities?.last_page > 1" class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex items-center justify-end gap-1">
                            <Link
                                v-for="link in props.emailActivities?.links || []"
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

            <!-- Footer Information -->
            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                <div class="flex items-center justify-between text-sm text-gray-500">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Created: {{ new Date(props.contact.created_at).toLocaleDateString() }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <span>Updated: {{ new Date(props.contact.updated_at).toLocaleDateString() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Modal -->
        <div v-if="showSMSModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Send SMS</h3>
                    <button @click="showSMSModal = false" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form @submit.prevent="sendSMS">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">To:</label>
                        <div class="text-sm text-gray-900 bg-gray-50 p-3 rounded-md">
                            <div class="font-medium">{{ props.contact.name }}</div>
                            <div class="text-gray-600">{{ props.contact.phone }}</div>
                            <div class="text-gray-500">{{ props.contact.customer?.name }}</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Message:</label>
                        <textarea
                            v-model="smsForm.message"
                            rows="4"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                            placeholder="Type your message here..."
                            maxlength="160"
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

        <EmailComposerModal
            :open="showEmailModal"
            :title="emailModalTitle"
            :send-url="emailSendUrl"
            @close="showEmailModal = false"
        />
    </AppLayout>
</template>
