<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailComposerModal from '@/components/EmailComposerModal.vue';
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Edit, Mail, MessageSquare, Trash2 } from 'lucide-vue-next';
import contacts from '@/routes/contacts';
import { computed, ref, watch } from 'vue';

interface Contact {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    position?: string | null;
    is_primary: boolean;
    customer: {
        id: number;
        name: string;
    };
}

interface Company {
    id: number;
    name: string;
}

interface PaginatedContacts {
    data: Contact[];
    links: { url: string | null; label: string; active: boolean }[];
    from?: number;
    to?: number;
    total?: number;
}

const canContactsCreate = useAuthAbility('contacts', 'create');
const canContactsEdit = useAuthAbility('contacts', 'edit');
const canContactsDelete = useAuthAbility('contacts', 'delete');

const props = defineProps<{
    contacts: PaginatedContacts;
    filters: { search?: string; sort_by?: string; sort_dir?: 'asc' | 'desc' };
    currentCompany: Company;
    emailTemplates: {
        id: number;
        name: string;
        subject: string;
        html_template?: string | null;
        css_styles?: string | null;
        is_default?: boolean;
    }[];
}>();

const search = ref(props.filters?.search ?? '');
const sortBy = ref(props.filters?.sort_by ?? 'name');
const sortDir = ref(props.filters?.sort_dir ?? 'asc');

watch(search, (value) => {
    const params: Record<string, string> = {};
    if (value && value.trim()) {
        params.search = value.trim();
    }
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    
    router.get(contacts.index().url, params, {
        preserveState: true,
        replace: true,
    });
});

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }

    const params: Record<string, string> = {};
    if (search.value && search.value.trim()) {
        params.search = search.value.trim();
    }
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    router.get(contacts.index().url, params, { preserveState: true, replace: true });
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const deleteContact = (contact: Contact) => {
    if (!confirm(`Are you sure you want to delete contact "${contact.name}"?`)) {
        return;
    }
    router.delete(contacts.destroy(contact.id).url);
};

// SMS functionality
const showSMSModal = ref(false);
const showSMSResultModal = ref(false);
const selectedContact = ref<Contact | null>(null);
const smsResult = ref<{ success: boolean; message: string } | null>(null);
const showEmailModal = ref(false);

const smsForm = useForm({
    message: '',
});

const openSMSModal = (contact: Contact) => {
    selectedContact.value = contact;
    smsForm.message = '';
    smsForm.clearErrors();
    showSMSModal.value = true;
};

const sendSMS = () => {
    if (!selectedContact.value) return;
    
    smsForm.post(contacts.sms(selectedContact.value.id).url, {
        onSuccess: (page) => {
            showSMSModal.value = false;
            smsResult.value = {
                success: true,
                message: `SMS sent successfully to ${selectedContact.value?.name} at ${selectedContact.value?.phone}`
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
    selectedContact.value = null;
};

const openEmailModal = (contact: Contact) => {
    selectedContact.value = contact;
    showEmailModal.value = true;
};

const emailSendUrl = computed(() => selectedContact.value ? `/contacts/${selectedContact.value.id}/send-email` : null);
const emailModalTitle = computed(() => `Send Email to ${selectedContact.value?.name || 'Contact'}`);
const emailPreviewContext = computed(() => ({
    contact: selectedContact.value ?? {},
    customer: selectedContact.value?.customer ?? {},
}));
</script>

<template>
    <Head title="Contacts" />

    <AppLayout :breadcrumbs="[{ title: 'Contacts', href: contacts.index().url }]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing contacts for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Contacts</h1>
                <Link
                    v-if="canContactsCreate"
                    :href="contacts.create().url"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    New Contact
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search contacts..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="search = ''"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            Clear Search
                        </button>
                    </div>
                </div>
            </div>

            <!-- Contacts Table -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700">Name {{ sortIndicator('name') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">Customer {{ sortIndicator('customer_name') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('email')" class="inline-flex items-center gap-1 hover:text-gray-700">Email {{ sortIndicator('email') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('phone')" class="inline-flex items-center gap-1 hover:text-gray-700">Phone {{ sortIndicator('phone') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('position')" class="inline-flex items-center gap-1 hover:text-gray-700">Position {{ sortIndicator('position') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('is_primary')" class="inline-flex items-center gap-1 hover:text-gray-700">Primary {{ sortIndicator('is_primary') }}</button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="contact in props.contacts.data"
                                :key="contact.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(contacts.show(contact.id).url)"
                                @keydown.enter.prevent="router.visit(contacts.show(contact.id).url)"
                                @keydown.space.prevent="router.visit(contacts.show(contact.id).url)"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ contact.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ contact.customer.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ contact.email || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ contact.phone || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ contact.position || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="contact.is_primary" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Primary
                                    </span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="canContactsEdit"
                                            :href="contacts.edit(contact.id).url"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="contact.email"
                                            @click="openEmailModal(contact)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            <ListTableActionLabel label="Email">
                                                <Mail class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                        <button
                                            v-if="contact.phone"
                                            @click="openSMSModal(contact)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        >
                                            <ListTableActionLabel label="SMS">
                                                <MessageSquare class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                        <button
                                            v-if="canContactsDelete"
                                            type="button"
                                            @click="deleteContact(contact)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.contacts.links" class="bg-white px-4 py-3 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ props.contacts.from || 0 }} to {{ props.contacts.to || 0 }} of {{ props.contacts.total || 0 }} results
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="link in props.contacts.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-2 text-sm border rounded-md',
                                    link.active
                                        ? 'bg-blue-50 border-blue-500 text-blue-600'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                                    !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                ]"
                            />
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
                            <div class="font-medium">{{ selectedContact?.name }}</div>
                            <div class="text-gray-600">{{ selectedContact?.phone }}</div>
                            <div class="text-gray-500">{{ selectedContact?.customer?.name }}</div>
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
            :templates="props.emailTemplates"
            :preview-context="emailPreviewContext"
            @close="showEmailModal = false"
            @sent="selectedContact = null"
        />
    </AppLayout>
</template>
