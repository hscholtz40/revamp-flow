<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import companySettings from '@/routes/company-settings';
import { ref, onMounted } from 'vue';

interface Company {
    id: number;
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    vat_number: string;
    website: string;
    logo_path: string;
    description: string;
    invoice_footer: string;
    jobcard_footer: string;
    quote_footer: string;
    default_invoice_terms: string;
    default_quote_terms: string;
    default_jobcard_terms: string;
    is_active: boolean;
    is_default: boolean;
    smtp_host: string | null;
    smtp_port: number | null;
    smtp_username: string | null;
    smtp_password: string | null;
    smtp_encryption: string | null;
    smtp_from_email: string | null;
    smtp_from_name: string | null;
    whatsapp_business_number: string | null;
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_sort_code: string | null;
    created_at: string;
    updated_at: string;
}

interface ReminderSettings {
    id: number;
    company_id: number;
    automation_enabled: boolean;
    overdue_invoice_email_enabled: boolean;
    overdue_invoice_sms_enabled: boolean;
    overdue_invoice_days_after_due: number;
    overdue_invoice_frequency_days: number;
    overdue_invoice_email_template: string | null;
    overdue_invoice_sms_template: string | null;
    overdue_invoice_whatsapp_enabled: boolean;
    overdue_invoice_whatsapp_template: string | null;
    overdue_invoice_whatsapp_template_name: string | null;
    overdue_invoice_whatsapp_template_variables: string[] | null;
    expiring_quote_email_enabled: boolean;
    expiring_quote_sms_enabled: boolean;
    expiring_quote_whatsapp_enabled: boolean;
    expiring_quote_days_before: number;
    expiring_quote_email_template: string | null;
    expiring_quote_sms_template: string | null;
    expiring_quote_whatsapp_template: string | null;
    expiring_quote_whatsapp_template_name: string | null;
    expiring_quote_whatsapp_template_variables: string[] | null;
    payment_received_email_enabled: boolean;
    payment_received_sms_enabled: boolean;
    payment_received_whatsapp_enabled: boolean;
    payment_received_email_template: string | null;
    payment_received_sms_template: string | null;
    payment_received_whatsapp_template: string | null;
    payment_received_whatsapp_template_name: string | null;
    payment_received_whatsapp_template_variables: string[] | null;
    invoice_created_email_enabled: boolean;
    invoice_created_sms_enabled: boolean;
    invoice_created_whatsapp_enabled: boolean;
    invoice_created_email_template: string | null;
    invoice_created_sms_template: string | null;
    invoice_created_whatsapp_template: string | null;
    invoice_created_whatsapp_template_name: string | null;
    invoice_created_whatsapp_template_variables: string[] | null;
    quote_created_email_enabled: boolean;
    quote_created_sms_enabled: boolean;
    quote_created_whatsapp_enabled: boolean;
    quote_created_email_template: string | null;
    quote_created_sms_template: string | null;
    quote_created_whatsapp_template: string | null;
    quote_created_whatsapp_template_name: string | null;
    quote_created_whatsapp_template_variables: string[] | null;
    jobcard_created_email_enabled: boolean;
    jobcard_created_sms_enabled: boolean;
    jobcard_created_whatsapp_enabled: boolean;
    jobcard_created_email_template: string | null;
    jobcard_created_sms_template: string | null;
    jobcard_created_whatsapp_template: string | null;
    jobcard_created_whatsapp_template_name: string | null;
    jobcard_created_whatsapp_template_variables: string[] | null;
    jobcard_status_updated_email_enabled: boolean;
    jobcard_status_updated_sms_enabled: boolean;
    jobcard_status_updated_whatsapp_enabled: boolean;
    jobcard_status_updated_email_template: string | null;
    jobcard_status_updated_sms_template: string | null;
    jobcard_status_updated_whatsapp_template: string | null;
    jobcard_status_updated_whatsapp_template_name: string | null;
    jobcard_status_updated_whatsapp_template_variables: string[] | null;
}

interface Props {
    company: Company;
    reminderSettings?: ReminderSettings;
}

const props = defineProps<Props>();

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    vat_number: '',
    website: '',
    description: '',
    invoice_footer: '',
    jobcard_footer: '',
    quote_footer: '',
    default_invoice_terms: '',
    default_quote_terms: '',
    default_jobcard_terms: '',
    is_active: true,
    is_default: false,
    logo: null as File | null,
    smtp_host: '',
    smtp_port: 587,
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    smtp_from_email: '',
    smtp_from_name: '',
    whatsapp_business_number: '',
    bank_name: '',
    bank_account_name: '',
    bank_account_number: '',
    bank_sort_code: '',
});

// Initialize form with company data
onMounted(() => {
    form.name = props.company.name || '';
    form.email = props.company.email || '';
    form.phone = props.company.phone || '';
    form.address = props.company.address || '';
    form.city = props.company.city || '';
    form.state = props.company.state || '';
    form.postal_code = props.company.postal_code || '';
    form.country = props.company.country || '';
    form.vat_number = props.company.vat_number || '';
    form.website = props.company.website || '';
    form.description = props.company.description || '';
    form.invoice_footer = props.company.invoice_footer || '';
    form.jobcard_footer = props.company.jobcard_footer || '';
    form.quote_footer = props.company.quote_footer || '';
    form.default_invoice_terms = props.company.default_invoice_terms || '';
    form.default_quote_terms = props.company.default_quote_terms || '';
    form.default_jobcard_terms = props.company.default_jobcard_terms || '';
    form.is_active = props.company.is_active ?? true;
    form.is_default = props.company.is_default ?? false;
    form.smtp_host = props.company.smtp_host || '';
    form.smtp_port = props.company.smtp_port ?? 587;
    form.smtp_username = props.company.smtp_username || '';
    form.smtp_password = ''; // Don't populate password field for security
    form.smtp_encryption = props.company.smtp_encryption || 'tls';
    form.smtp_from_email = props.company.smtp_from_email || '';
    form.smtp_from_name = props.company.smtp_from_name || '';
    form.whatsapp_business_number = props.company.whatsapp_business_number || '';
    form.bank_name = props.company.bank_name || '';
    form.bank_account_name = props.company.bank_account_name || '';
    form.bank_account_number = props.company.bank_account_number || '';
    form.bank_sort_code = props.company.bank_sort_code || '';

});

const logoPreview = ref<string | null>(props.company.logo_path ? `/storage/${props.company.logo_path}` : null);

function handleLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
        
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(target.files[0]);
    }
}

function submit() {
    
    // Ensure all form fields are properly set before submission
    if (!form.name || form.name.trim() === '') {
        console.error('Name field is empty!');
        return;
    }
    
    // Submit the form without logo first
    const formWithoutLogo = useForm({
        name: form.name,
        email: form.email,
        phone: form.phone,
        address: form.address,
        city: form.city,
        state: form.state,
        postal_code: form.postal_code,
        country: form.country,
        vat_number: form.vat_number,
        website: form.website,
        description: form.description,
        invoice_footer: form.invoice_footer,
        jobcard_footer: form.jobcard_footer,
        quote_footer: form.quote_footer,
        default_invoice_terms: form.default_invoice_terms,
        default_quote_terms: form.default_quote_terms,
        default_jobcard_terms: form.default_jobcard_terms,
        is_active: form.is_active,
        is_default: form.is_default,
        smtp_host: form.smtp_host,
        smtp_port: form.smtp_port,
        smtp_username: form.smtp_username,
        smtp_password: form.smtp_password,
        smtp_encryption: form.smtp_encryption,
        smtp_from_email: form.smtp_from_email,
        smtp_from_name: form.smtp_from_name,
        whatsapp_business_number: form.whatsapp_business_number,
        bank_name: form.bank_name,
        bank_account_name: form.bank_account_name,
        bank_account_number: form.bank_account_number,
        bank_sort_code: form.bank_sort_code,
    });
    
    formWithoutLogo.put(companySettings.update(props.company.id).url, { 
        preserveScroll: true,
        onSuccess: () => {
            // If there's a logo, upload it separately
            if (form.logo) {
                uploadLogo();
            }
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        }
    });
}

function uploadLogo() {
    if (!form.logo) return;
        
    const logoForm = useForm({
        logo: form.logo,
    });
    
    logoForm.post(companySettings.uploadLogo(props.company.id).url, {
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (errors) => {
            console.error('Logo upload errors:', errors);
        }
    });
}

// Reminder Settings Form
const reminderForm = useForm({
    automation_enabled: false,
    overdue_invoice_email_enabled: false,
    overdue_invoice_sms_enabled: false,
    overdue_invoice_days_after_due: 1,
    overdue_invoice_frequency_days: 7,
    overdue_invoice_email_template: '',
    overdue_invoice_sms_template: '',
    overdue_invoice_whatsapp_enabled: false,
    overdue_invoice_whatsapp_template: '',
    overdue_invoice_whatsapp_template_name: '',
    overdue_invoice_whatsapp_template_variables: [] as string[],
    expiring_quote_email_enabled: false,
    expiring_quote_sms_enabled: false,
    expiring_quote_whatsapp_enabled: false,
    expiring_quote_days_before: 3,
    expiring_quote_email_template: '',
    expiring_quote_sms_template: '',
    expiring_quote_whatsapp_template: '',
    expiring_quote_whatsapp_template_name: '',
    expiring_quote_whatsapp_template_variables: [] as string[],
    payment_received_email_enabled: false,
    payment_received_sms_enabled: false,
    payment_received_whatsapp_enabled: false,
    payment_received_email_template: '',
    payment_received_sms_template: '',
    payment_received_whatsapp_template: '',
    payment_received_whatsapp_template_name: '',
    payment_received_whatsapp_template_variables: [] as string[],
    invoice_created_email_enabled: false,
    invoice_created_sms_enabled: false,
    invoice_created_whatsapp_enabled: false,
    invoice_created_email_template: '',
    invoice_created_sms_template: '',
    invoice_created_whatsapp_template: '',
    invoice_created_whatsapp_template_name: '',
    invoice_created_whatsapp_template_variables: [] as string[],
    quote_created_email_enabled: false,
    quote_created_sms_enabled: false,
    quote_created_whatsapp_enabled: false,
    quote_created_email_template: '',
    quote_created_sms_template: '',
    quote_created_whatsapp_template: '',
    quote_created_whatsapp_template_name: '',
    quote_created_whatsapp_template_variables: [] as string[],
    jobcard_created_email_enabled: false,
    jobcard_created_sms_enabled: false,
    jobcard_created_whatsapp_enabled: false,
    jobcard_created_email_template: '',
    jobcard_created_sms_template: '',
    jobcard_created_whatsapp_template: '',
    jobcard_created_whatsapp_template_name: '',
    jobcard_created_whatsapp_template_variables: [] as string[],
    jobcard_status_updated_email_enabled: false,
    jobcard_status_updated_sms_enabled: false,
    jobcard_status_updated_whatsapp_enabled: false,
    jobcard_status_updated_email_template: '',
    jobcard_status_updated_sms_template: '',
    jobcard_status_updated_whatsapp_template: '',
    jobcard_status_updated_whatsapp_template_name: '',
    jobcard_status_updated_whatsapp_template_variables: [] as string[],
});

// Initialize reminder form with settings data
onMounted(() => {
    if (props.reminderSettings) {
        reminderForm.automation_enabled = props.reminderSettings.automation_enabled ?? false;
        reminderForm.overdue_invoice_email_enabled = props.reminderSettings.overdue_invoice_email_enabled ?? false;
        reminderForm.overdue_invoice_sms_enabled = props.reminderSettings.overdue_invoice_sms_enabled ?? false;
        reminderForm.overdue_invoice_days_after_due = props.reminderSettings.overdue_invoice_days_after_due ?? 1;
        reminderForm.overdue_invoice_frequency_days = props.reminderSettings.overdue_invoice_frequency_days ?? 7;
        reminderForm.overdue_invoice_email_template = props.reminderSettings.overdue_invoice_email_template || '';
        reminderForm.overdue_invoice_sms_template = props.reminderSettings.overdue_invoice_sms_template || '';
        reminderForm.overdue_invoice_whatsapp_enabled = props.reminderSettings.overdue_invoice_whatsapp_enabled ?? false;
        reminderForm.overdue_invoice_whatsapp_template = props.reminderSettings.overdue_invoice_whatsapp_template || '';
        reminderForm.overdue_invoice_whatsapp_template_name = props.reminderSettings.overdue_invoice_whatsapp_template_name || '';
        reminderForm.overdue_invoice_whatsapp_template_variables = props.reminderSettings.overdue_invoice_whatsapp_template_variables || [];
        reminderForm.expiring_quote_email_enabled = props.reminderSettings.expiring_quote_email_enabled ?? false;
        reminderForm.expiring_quote_sms_enabled = props.reminderSettings.expiring_quote_sms_enabled ?? false;
        reminderForm.expiring_quote_whatsapp_enabled = props.reminderSettings.expiring_quote_whatsapp_enabled ?? false;
        reminderForm.expiring_quote_days_before = props.reminderSettings.expiring_quote_days_before ?? 3;
        reminderForm.expiring_quote_email_template = props.reminderSettings.expiring_quote_email_template || '';
        reminderForm.expiring_quote_sms_template = props.reminderSettings.expiring_quote_sms_template || '';
        reminderForm.expiring_quote_whatsapp_template = props.reminderSettings.expiring_quote_whatsapp_template || '';
        reminderForm.expiring_quote_whatsapp_template_name = props.reminderSettings.expiring_quote_whatsapp_template_name || '';
        reminderForm.expiring_quote_whatsapp_template_variables = props.reminderSettings.expiring_quote_whatsapp_template_variables || [];
        reminderForm.payment_received_email_enabled = props.reminderSettings.payment_received_email_enabled ?? false;
        reminderForm.payment_received_sms_enabled = props.reminderSettings.payment_received_sms_enabled ?? false;
        reminderForm.payment_received_whatsapp_enabled = props.reminderSettings.payment_received_whatsapp_enabled ?? false;
        reminderForm.payment_received_email_template = props.reminderSettings.payment_received_email_template || '';
        reminderForm.payment_received_sms_template = props.reminderSettings.payment_received_sms_template || '';
        reminderForm.payment_received_whatsapp_template = props.reminderSettings.payment_received_whatsapp_template || '';
        reminderForm.payment_received_whatsapp_template_name = props.reminderSettings.payment_received_whatsapp_template_name || '';
        reminderForm.payment_received_whatsapp_template_variables = props.reminderSettings.payment_received_whatsapp_template_variables || [];
        reminderForm.invoice_created_email_enabled = props.reminderSettings.invoice_created_email_enabled ?? false;
        reminderForm.invoice_created_sms_enabled = props.reminderSettings.invoice_created_sms_enabled ?? false;
        reminderForm.invoice_created_whatsapp_enabled = props.reminderSettings.invoice_created_whatsapp_enabled ?? false;
        reminderForm.invoice_created_email_template = props.reminderSettings.invoice_created_email_template || '';
        reminderForm.invoice_created_sms_template = props.reminderSettings.invoice_created_sms_template || '';
        reminderForm.invoice_created_whatsapp_template = props.reminderSettings.invoice_created_whatsapp_template || '';
        reminderForm.invoice_created_whatsapp_template_name = props.reminderSettings.invoice_created_whatsapp_template_name || '';
        reminderForm.invoice_created_whatsapp_template_variables = props.reminderSettings.invoice_created_whatsapp_template_variables || [];
        reminderForm.quote_created_email_enabled = props.reminderSettings.quote_created_email_enabled ?? false;
        reminderForm.quote_created_sms_enabled = props.reminderSettings.quote_created_sms_enabled ?? false;
        reminderForm.quote_created_whatsapp_enabled = props.reminderSettings.quote_created_whatsapp_enabled ?? false;
        reminderForm.quote_created_email_template = props.reminderSettings.quote_created_email_template || '';
        reminderForm.quote_created_sms_template = props.reminderSettings.quote_created_sms_template || '';
        reminderForm.quote_created_whatsapp_template = props.reminderSettings.quote_created_whatsapp_template || '';
        reminderForm.quote_created_whatsapp_template_name = props.reminderSettings.quote_created_whatsapp_template_name || '';
        reminderForm.quote_created_whatsapp_template_variables = props.reminderSettings.quote_created_whatsapp_template_variables || [];
        reminderForm.jobcard_created_email_enabled = props.reminderSettings.jobcard_created_email_enabled ?? false;
        reminderForm.jobcard_created_sms_enabled = props.reminderSettings.jobcard_created_sms_enabled ?? false;
        reminderForm.jobcard_created_whatsapp_enabled = props.reminderSettings.jobcard_created_whatsapp_enabled ?? false;
        reminderForm.jobcard_created_email_template = props.reminderSettings.jobcard_created_email_template || '';
        reminderForm.jobcard_created_sms_template = props.reminderSettings.jobcard_created_sms_template || '';
        reminderForm.jobcard_created_whatsapp_template = props.reminderSettings.jobcard_created_whatsapp_template || '';
        reminderForm.jobcard_created_whatsapp_template_name = props.reminderSettings.jobcard_created_whatsapp_template_name || '';
        reminderForm.jobcard_created_whatsapp_template_variables = props.reminderSettings.jobcard_created_whatsapp_template_variables || [];
        reminderForm.jobcard_status_updated_email_enabled = props.reminderSettings.jobcard_status_updated_email_enabled ?? false;
        reminderForm.jobcard_status_updated_sms_enabled = props.reminderSettings.jobcard_status_updated_sms_enabled ?? false;
        reminderForm.jobcard_status_updated_whatsapp_enabled = props.reminderSettings.jobcard_status_updated_whatsapp_enabled ?? false;
        reminderForm.jobcard_status_updated_email_template = props.reminderSettings.jobcard_status_updated_email_template || '';
        reminderForm.jobcard_status_updated_sms_template = props.reminderSettings.jobcard_status_updated_sms_template || '';
        reminderForm.jobcard_status_updated_whatsapp_template = props.reminderSettings.jobcard_status_updated_whatsapp_template || '';
        reminderForm.jobcard_status_updated_whatsapp_template_name = props.reminderSettings.jobcard_status_updated_whatsapp_template_name || '';
        reminderForm.jobcard_status_updated_whatsapp_template_variables = props.reminderSettings.jobcard_status_updated_whatsapp_template_variables || [];
    }
});

function submitReminderSettings() {
    reminderForm.put(`/company-settings/${props.company.id}/reminder-settings`, {
        preserveScroll: true,
        onSuccess: () => {
        },
        onError: (errors) => {
            console.error('Reminder settings update errors:', errors);
        }
    });
}

</script>

<template>
    <Head :title="`Edit ${props.company.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Company Settings', href: companySettings.index().url },
        { title: props.company.name, href: companySettings.show(props.company.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="companySettings.show(props.company.id).url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Company
                </Link>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Edit Company</h1>
                    <p class="text-gray-600">Update the details for {{ props.company.name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Company Logo -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Company Logo</h2>
                        <div class="flex items-center gap-6">
                            <div v-if="logoPreview" class="h-20 w-20 overflow-hidden rounded-lg border">
                                <img :src="logoPreview" alt="Company Logo" class="h-full w-full object-cover" />
                            </div>
                            <div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleLogoChange"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Company Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Company Information</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Company Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    required
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Phone</label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Website</label>
                                <input
                                    v-model="form.website"
                                    type="url"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.website" class="mt-1 text-sm text-red-600">{{ form.errors.website }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Address Information</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="3"
                                    class="w-full rounded border px-3 py-2"
                                ></textarea>
                                <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                            </div>
                            
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">City</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.city" class="mt-1 text-sm text-red-600">{{ form.errors.city }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">State/Province</label>
                                    <input
                                        v-model="form.state"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.state" class="mt-1 text-sm text-red-600">{{ form.errors.state }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Postal Code</label>
                                    <input
                                        v-model="form.postal_code"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Country</label>
                                <input
                                    v-model="form.country"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.country" class="mt-1 text-sm text-red-600">{{ form.errors.country }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">VAT Number</label>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.vat_number" class="mt-1 text-sm text-red-600">{{ form.errors.vat_number }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Details -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Banking Details</h2>
                        <p class="mb-4 text-sm text-gray-600">These details will appear on your PDF documents (invoices, quotes, jobcards).</p>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Bank Name</label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="e.g. FNB, Standard Bank, ABSA"
                                />
                                <div v-if="form.errors.bank_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_name }}</div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Account Name</label>
                                <input
                                    v-model="form.bank_account_name"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Name on the account"
                                />
                                <div v-if="form.errors.bank_account_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_account_name }}</div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Account Number</label>
                                <input
                                    v-model="form.bank_account_number"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Bank account number"
                                />
                                <div v-if="form.errors.bank_account_number" class="mt-1 text-sm text-red-600">{{ form.errors.bank_account_number }}</div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">Branch Code</label>
                                <input
                                    v-model="form.bank_sort_code"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="e.g. 250655"
                                />
                                <div v-if="form.errors.bank_sort_code" class="mt-1 text-sm text-red-600">{{ form.errors.bank_sort_code }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Company Description</label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Tell us about your company..."
                            ></textarea>
                            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
                        </div>
                    </div>

                    <!-- Document Footers -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Document Footers</h2>
                        <p class="mb-4 text-sm text-gray-600">These footer texts will appear at the bottom of the respective PDF documents.</p>
                        
                        <div class="space-y-6">
                            <!-- Invoice Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Invoice Footer</label>
                                <textarea
                                    v-model="form.invoice_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on invoice PDFs (e.g., payment terms, thank you message, etc.)"
                                ></textarea>
                                <div v-if="form.errors.invoice_footer" class="mt-1 text-sm text-red-600">{{ form.errors.invoice_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all invoice PDFs generated for this company.</p>
                            </div>

                            <!-- Quote Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Quote Footer</label>
                                <textarea
                                    v-model="form.quote_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on quote PDFs (e.g., validity period, terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.quote_footer" class="mt-1 text-sm text-red-600">{{ form.errors.quote_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all quote PDFs generated for this company.</p>
                            </div>

                            <!-- Jobcard Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Jobcard Footer</label>
                                <textarea
                                    v-model="form.jobcard_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on jobcard PDFs (e.g., completion notes, warranty info, etc.)"
                                ></textarea>
                                <div v-if="form.errors.jobcard_footer" class="mt-1 text-sm text-red-600">{{ form.errors.jobcard_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all jobcard PDFs generated for this company.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Default Terms & Conditions -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Default Terms & Conditions</h2>
                        <p class="mb-4 text-sm text-gray-600">These terms will be automatically populated when creating new documents. They can be customized for each individual document.</p>
                        
                        <div class="space-y-6">
                            <!-- Invoice Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Invoice Terms</label>
                                <textarea
                                    v-model="form.default_invoice_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for invoices (e.g., payment terms, late fees, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_invoice_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_invoice_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new invoices.</p>
                            </div>

                            <!-- Quote Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Quote Terms</label>
                                <textarea
                                    v-model="form.default_quote_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for quotes (e.g., validity period, acceptance terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_quote_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_quote_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new quotes.</p>
                            </div>

                            <!-- Jobcard Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Jobcard Terms</label>
                                <textarea
                                    v-model="form.default_jobcard_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for jobcards (e.g., warranty info, completion terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_jobcard_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_jobcard_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new jobcards.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for selection)</span>
                                </label>
                            </div>

                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Set as default company</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Email Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">SMTP Email Settings</h2>
                        <p class="mb-4 text-sm text-gray-600">Configure SMTP settings for automated notifications and reminder emails. These settings will be used for all automated emails sent by this company.</p>
                        
                        <div class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">SMTP Host *</label>
                                    <input
                                        v-model="form.smtp_host"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="smtp.example.com"
                                    />
                                    <div v-if="form.errors.smtp_host" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_host }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">SMTP Port *</label>
                                    <input
                                        v-model.number="form.smtp_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="587"
                                    />
                                    <div v-if="form.errors.smtp_port" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_port }}</div>
                                    <p class="mt-1 text-xs text-gray-500">Common ports: 587 (TLS), 465 (SSL), 25</p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">SMTP Username *</label>
                                    <input
                                        v-model="form.smtp_username"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="your-email@example.com"
                                    />
                                    <div v-if="form.errors.smtp_username" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_username }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">SMTP Password *</label>
                                    <input
                                        v-model="form.smtp_password"
                                        type="password"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="Leave blank to keep current password"
                                    />
                                    <div v-if="form.errors.smtp_password" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_password }}</div>
                                    <p class="mt-1 text-xs text-gray-500">Leave blank if you don't want to change the password</p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Encryption</label>
                                    <select
                                        v-model="form.smtp_encryption"
                                        class="w-full rounded border px-3 py-2"
                                    >
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                    </select>
                                    <div v-if="form.errors.smtp_encryption" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_encryption }}</div>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">From Email</label>
                                    <input
                                        v-model="form.smtp_from_email"
                                        type="email"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="noreply@example.com"
                                    />
                                    <div v-if="form.errors.smtp_from_email" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_from_email }}</div>
                                    <p class="mt-1 text-xs text-gray-500">Email address to send from (defaults to SMTP username if not set)</p>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">From Name</label>
                                    <input
                                        v-model="form.smtp_from_name"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                        placeholder="Company Name"
                                    />
                                    <div v-if="form.errors.smtp_from_name" class="mt-1 text-sm text-red-600">{{ form.errors.smtp_from_name }}</div>
                                    <p class="mt-1 text-xs text-gray-500">Display name for sent emails (defaults to company name if not set)</p>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium">WhatsApp Business Number</label>
                                <input
                                    v-model="form.whatsapp_business_number"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="+27123456789"
                                />
                                <div v-if="form.errors.whatsapp_business_number" class="mt-1 text-sm text-red-600">{{ form.errors.whatsapp_business_number }}</div>
                                <p class="mt-1 text-xs text-gray-500">WhatsApp Business number for this company (format: +[country code][number])</p>
                            </div>
                        </div>
                    </div>

                    <!-- Automated Reminders -->
                    <div class="rounded-lg border bg-white p-6">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Automated Reminders</h2>
                                <p class="text-sm text-gray-600">Configure automated email and SMS reminders for your business</p>
                            </div>
                        </div>

                        <form @submit.prevent="submitReminderSettings" class="space-y-8">
                            <!-- General Automation Toggle -->
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="reminderForm.automation_enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-base font-semibold text-gray-900">Enable Automation</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-600">Master switch to enable/disable all automated reminders</p>
                            </div>

                            <!-- Overdue Invoice Reminders -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Overdue Invoice Reminders</h3>
                                
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-medium">Days After Due Date</label>
                                        <input
                                            v-model.number="reminderForm.overdue_invoice_days_after_due"
                                            type="number"
                                            min="0"
                                            class="w-full rounded border px-3 py-2"
                                            :disabled="!reminderForm.automation_enabled"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-medium">Frequency (Days)</label>
                                        <input
                                            v-model.number="reminderForm.overdue_invoice_frequency_days"
                                            type="number"
                                            min="1"
                                            class="w-full rounded border px-3 py-2"
                                            :disabled="!reminderForm.automation_enabled"
                                        />
                                        <p class="mt-1 text-xs text-gray-500">How often to send reminders</p>
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.overdue_invoice_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.overdue_invoice_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.overdue_invoice_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{invoice_number}}, {{invoice_total}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.overdue_invoice_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.overdue_invoice_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.overdue_invoice_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{invoice_number}}, {{invoice_total}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                        <p class="mt-1 text-xs text-gray-500">Keep SMS messages short (160 characters recommended)</p>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.overdue_invoice_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.overdue_invoice_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.overdue_invoice_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., overdue_invoice_reminder"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.overdue_invoice_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="invoice_number">Invoice Number</option>
                                                <option value="invoice_total">Invoice Total</option>
                                                <option value="due_date">Due Date</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Expiring Quote Reminders -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Expiring Quote Reminders</h3>
                                
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Days Before Expiry</label>
                                    <input
                                        v-model.number="reminderForm.expiring_quote_days_before"
                                        type="number"
                                        min="0"
                                        class="w-full rounded border px-3 py-2"
                                        :disabled="!reminderForm.automation_enabled"
                                    />
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.expiring_quote_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.expiring_quote_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.expiring_quote_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{quote_number}}, {{quote_total}}, {{expiry_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.expiring_quote_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.expiring_quote_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.expiring_quote_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{quote_number}}, {{quote_total}}, {{expiry_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.expiring_quote_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Reminders</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.expiring_quote_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.expiring_quote_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., expiring_quote_reminder"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.expiring_quote_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="quote_number">Quote Number</option>
                                                <option value="quote_total">Quote Total</option>
                                                <option value="expiry_date">Expiry Date</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Received Confirmation -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Payment Received Confirmation</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.payment_received_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.payment_received_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.payment_received_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{invoice_number}}, {{payment_amount}}, {{payment_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.payment_received_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.payment_received_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template (Not recommended as networks may block payment SMS's)</label>
                                        <textarea
                                            v-model="reminderForm.payment_received_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Example: {{company_name}}: Thank you. Ref {{invoice_number}}&#10;&#10;Available variables: {{customer_name}}, {{invoice_number}}, {{payment_amount}}, {{payment_date}}, {{payment_method}}, {{company_name}}&#10;&#10;Tip: Keep it simple. Avoid words like 'Payment', 'Received', 'Allocated', 'Account', or amounts to prevent SMS filtering."
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.payment_received_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.payment_received_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.payment_received_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., payment_received"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.payment_received_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="invoice_number">Invoice Number</option>
                                                <option value="payment_amount">Payment Amount</option>
                                                <option value="payment_date">Payment Date</option>
                                                <option value="payment_method">Payment Method</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Invoice Created Confirmation -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Invoice Created Confirmation</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.invoice_created_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.invoice_created_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.invoice_created_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{invoice_number}}, {{invoice_total}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.invoice_created_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.invoice_created_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.invoice_created_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{invoice_number}}, {{invoice_total}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.invoice_created_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.invoice_created_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.invoice_created_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., invoice_created"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.invoice_created_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="invoice_number">Invoice Number</option>
                                                <option value="invoice_total">Invoice Total</option>
                                                <option value="due_date">Due Date</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Quote Created Confirmation -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Quote Created Confirmation</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.quote_created_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.quote_created_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.quote_created_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{quote_number}}, {{quote_total}}, {{expiry_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.quote_created_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.quote_created_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.quote_created_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{quote_number}}, {{quote_total}}, {{expiry_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.quote_created_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.quote_created_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.quote_created_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., quote_created"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.quote_created_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="quote_number">Quote Number</option>
                                                <option value="quote_total">Quote Total</option>
                                                <option value="expiry_date">Expiry Date</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jobcard Created Confirmation -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Jobcard Created Confirmation</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_created_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_created_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.jobcard_created_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{job_number}}, {{jobcard_title}}, {{status}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_created_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_created_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.jobcard_created_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{job_number}}, {{jobcard_title}}, {{status}}, {{due_date}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_created_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_created_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.jobcard_created_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., jobcard_created"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.jobcard_created_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="job_number">Job Number</option>
                                                <option value="jobcard_title">Jobcard Title</option>
                                                <option value="status">Status</option>
                                                <option value="due_date">Due Date</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Jobcard Status Updated Confirmation -->
                            <div class="space-y-4 rounded-lg border border-gray-200 p-4">
                                <h3 class="text-base font-semibold text-gray-900">Jobcard Status Updated Confirmation</h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_status_updated_email_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable Email Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_status_updated_email_enabled">
                                        <label class="mb-1 block text-sm font-medium">Email Template</label>
                                        <textarea
                                            v-model="reminderForm.jobcard_status_updated_email_template"
                                            rows="6"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{job_number}}, {{jobcard_title}}, {{old_status}}, {{new_status}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_status_updated_sms_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable SMS Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_status_updated_sms_enabled">
                                        <label class="mb-1 block text-sm font-medium">SMS Template</label>
                                        <textarea
                                            v-model="reminderForm.jobcard_status_updated_sms_template"
                                            rows="3"
                                            class="w-full rounded border px-3 py-2 font-mono text-sm"
                                            placeholder="Available variables: {{customer_name}}, {{job_number}}, {{jobcard_title}}, {{old_status}}, {{new_status}}, {{company_name}}"
                                            :disabled="!reminderForm.automation_enabled"
                                        ></textarea>
                                    </div>

                                    <div>
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="reminderForm.jobcard_status_updated_whatsapp_enabled"
                                                type="checkbox"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                            <span class="text-sm font-medium text-gray-700">Enable WhatsApp Confirmation</span>
                                        </label>
                                    </div>
                                    <div v-if="reminderForm.jobcard_status_updated_whatsapp_enabled" class="space-y-3">
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Template Name *</label>
                                            <input
                                                v-model="reminderForm.jobcard_status_updated_whatsapp_template_name"
                                                type="text"
                                                class="w-full rounded border px-3 py-2"
                                                placeholder="e.g., jobcard_status_updated"
                                                :disabled="!reminderForm.automation_enabled"
                                            />
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-sm font-medium">Variables to Send</label>
                                            <select
                                                v-model="reminderForm.jobcard_status_updated_whatsapp_template_variables"
                                                multiple
                                                class="w-full rounded border px-3 py-2"
                                                :disabled="!reminderForm.automation_enabled"
                                            >
                                                <option value="customer_name">Customer Name</option>
                                                <option value="job_number">Job Number</option>
                                                <option value="jobcard_title">Jobcard Title</option>
                                                <option value="old_status">Old Status</option>
                                                <option value="new_status">New Status</option>
                                                <option value="company_name">Company Name</option>
                                            </select>
                                            <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple variables</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reminder Settings Actions -->
                            <div class="flex items-center justify-end gap-4">
                                <button
                                    type="submit"
                                    :disabled="reminderForm.processing || !reminderForm.automation_enabled"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ reminderForm.processing ? 'Saving...' : 'Save Reminder Settings' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="companySettings.show(props.company.id).url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Updating...' : 'Update Company' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
