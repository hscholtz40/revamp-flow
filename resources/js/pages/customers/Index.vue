<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import EmailComposerModal from '@/components/EmailComposerModal.vue';
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import customers from '@/routes/customers';
import { Edit, Mail, MessageSquare, Star, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
    email: string;
    phone?: string | null;
    account_code?: string | null;
    is_default_sales?: boolean;
}

interface Company {
    id: number;
    name: string;
}

const canCustomersCreate = useAuthAbility('customers', 'create');
const canCustomersEdit = useAuthAbility('customers', 'edit');
const canCustomersDelete = useAuthAbility('customers', 'delete');

const props = defineProps<{
    customers: {
        data: Customer[];
        links: { url: string | null; label: string; active: boolean }[];
    };
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
const showSMSModal = ref(false);
const showSMSResultModal = ref(false);
const selectedCustomer = ref<Customer | null>(null);
const smsResult = ref<{ success: boolean; message: string } | null>(null);
const showEmailModal = ref(false);

const smsForm = useForm({
    message: '',
});

const openSMSModal = (customer: Customer) => {
    selectedCustomer.value = customer;
    smsForm.message = '';
    showSMSModal.value = true;
};

const sendSMS = () => {
    if (!selectedCustomer.value) return;
    
    smsForm.post(customers.sendSMS(selectedCustomer.value.id).url, {
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
    selectedCustomer.value = null;
};

const openEmailModal = (customer: Customer) => {
    selectedCustomer.value = customer;
    showEmailModal.value = true;
};

const emailSendUrl = computed(() => selectedCustomer.value ? `/customers/${selectedCustomer.value.id}/send-email` : null);
const emailModalTitle = computed(() => `Send Email to ${selectedCustomer.value?.name || 'Customer'}`);
const emailPreviewContext = computed(() => ({
    customer: selectedCustomer.value ?? {},
}));

watch(search, (value) => {
    const params: Record<string, string> = {};
    if (value && value.trim()) {
        params.search = value.trim();
    }
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    
    router.get(customers.index().url, params, {
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

    router.get(customers.index().url, params, {
        preserveState: true,
        replace: true,
    });
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const setDefaultSales = (customer: Customer) => {
    router.post(`/customers/${customer.id}/set-default-sales`);
};

const deleteCustomer = (customer: Customer) => {
    if (!confirm(`Are you sure you want to delete customer "${customer.name}"?`)) {
        return;
    }
    router.delete(customers.destroy(customer.id).url);
};
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="[{ title: 'Customers', href: customers.index().url }]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing customers for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>
        
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
                <Link
                    v-if="canCustomersCreate"
                    :href="customers.create().url"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    New Customer
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
                            placeholder="Search customers..."
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

            <!-- Customers Table -->
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Name {{ sortIndicator('name') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('email')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Email {{ sortIndicator('email') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('phone')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Phone {{ sortIndicator('phone') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button type="button" @click="toggleSort('account_code')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Account # {{ sortIndicator('account_code') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="c in props.customers.data"
                                :key="c.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(customers.show(c.id).url)"
                                @keydown.enter.prevent="router.visit(customers.show(c.id).url)"
                                @keydown.space.prevent="router.visit(customers.show(c.id).url)"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                        <span>{{ c.name }}</span>
                                        <span v-if="c.is_default_sales" class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800">Default Sales</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ c.email }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ c.phone || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ c.account_code || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="canCustomersEdit"
                                            :href="customers.edit(c.id).url"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="c.email"
                                            @click="openEmailModal(c)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            <ListTableActionLabel label="Email">
                                                <Mail class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                        <button
                                            v-if="c.phone"
                                            @click="openSMSModal(c)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                        >
                                            <ListTableActionLabel label="SMS">
                                                <MessageSquare class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                        <button
                                            v-if="canCustomersEdit && !c.is_default_sales"
                                            @click="setDefaultSales(c)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            <ListTableActionLabel label="Set default">
                                                <Star class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                        <button
                                            v-if="canCustomersDelete"
                                            type="button"
                                            @click="deleteCustomer(c)"
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
                <div v-if="props.customers.links" class="bg-white px-4 py-3 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ props.customers.from || 0 }} to {{ props.customers.to || 0 }} of {{ props.customers.total || 0 }} results
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="link in props.customers.links"
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
                <h3 class="text-lg font-semibold mb-4">Send SMS to {{ selectedCustomer?.name }}</h3>
                
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

        <EmailComposerModal
            :open="showEmailModal"
            :title="emailModalTitle"
            :send-url="emailSendUrl"
            :templates="props.emailTemplates"
            :preview-context="emailPreviewContext"
            @close="showEmailModal = false"
            @sent="selectedCustomer = null"
        />
    </AppLayout>
    
</template>


