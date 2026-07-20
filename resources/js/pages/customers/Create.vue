<script setup lang="ts">
import AddressAutocompleteInput from '@/components/AddressAutocompleteInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import customers from '@/routes/customers';

const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];

const form = useForm({
    name: '',
    registration_number: '',
    email: '',
    company_cell: '',
    company_tel: '',
    address: '',
    city: '',
    country: '',
    contact_first_name: '',
    contact_last_name: '',
    contact_cell: '',
    contact_email: '',
    terms: 'COD',
    vat_number: '',
    account_code: '',
    notes: '',
    is_default_sales: false,
});

const accountCodePreview = computed(() => {
    if (!form.name || form.name.trim().length === 0) {
        return '';
    }

    const cleanedName = form.name.replace(/[^a-zA-Z]/g, '');

    if (cleanedName.length === 0) {
        return 'CU01';
    } else if (cleanedName.length === 1) {
        return (cleanedName + cleanedName).toUpperCase() + '01';
    } else {
        return cleanedName.substring(0, 2).toUpperCase() + '01';
    }
});

function submit() {
    form.post(customers.store().url);
}
</script>

<template>
    <Head title="New Customer" />

    <AppLayout :breadcrumbs="[{ title: 'Customers', href: customers.index().url }, { title: 'Create', href: '#' }]">
        <form @submit.prevent="submit" class="space-y-6 p-4">
            <div class="rounded-lg border bg-white p-6">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Company details</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Account Name *</span>
                        <input v-model="form.name" class="w-full rounded border px-3 py-2" required />
                        <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Company registration number</span>
                        <input v-model="form.registration_number" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.registration_number" class="text-sm text-red-600">{{ form.errors.registration_number }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Company email *</span>
                        <input v-model="form.email" type="email" class="w-full rounded border px-3 py-2" required />
                        <div v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</div>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Company address</span>
                        <AddressAutocompleteInput
                            v-model="form.address"
                            v-model:city="form.city"
                            v-model:country="form.country"
                            class="w-full rounded border px-3 py-2"
                        />
                        <div v-if="form.errors.address" class="text-sm text-red-600">{{ form.errors.address }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">City</span>
                        <input v-model="form.city" class="w-full rounded border px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Country</span>
                        <input v-model="form.country" class="w-full rounded border px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Company cell</span>
                        <input v-model="form.company_cell" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.company_cell" class="text-sm text-red-600">{{ form.errors.company_cell }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Company tel</span>
                        <input v-model="form.company_tel" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.company_tel" class="text-sm text-red-600">{{ form.errors.company_tel }}</div>
                    </label>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-6">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Contact person</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                        <input v-model="form.contact_first_name" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.contact_first_name" class="text-sm text-red-600">{{ form.errors.contact_first_name }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Surname</span>
                        <input v-model="form.contact_last_name" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.contact_last_name" class="text-sm text-red-600">{{ form.errors.contact_last_name }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Cell</span>
                        <input v-model="form.contact_cell" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.contact_cell" class="text-sm text-red-600">{{ form.errors.contact_cell }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Email</span>
                        <input v-model="form.contact_email" type="email" class="w-full rounded border px-3 py-2" />
                        <div v-if="form.errors.contact_email" class="text-sm text-red-600">{{ form.errors.contact_email }}</div>
                    </label>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-6">
                <h2 class="mb-4 text-lg font-semibold text-gray-900">Account settings</h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Payment terms</span>
                        <select v-model="form.terms" class="w-full rounded border px-3 py-2">
                            <option v-for="term in paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                        </select>
                        <div v-if="form.errors.terms" class="text-sm text-red-600">{{ form.errors.terms }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">VAT number</span>
                        <input v-model="form.vat_number" class="w-full rounded border px-3 py-2" />
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Account code</span>
                        <input
                            :value="accountCodePreview || 'Auto-generated'"
                            class="w-full rounded border bg-gray-50 px-3 py-2 text-gray-600 cursor-not-allowed"
                            placeholder="Auto-generated"
                            readonly
                            disabled
                        />
                        <div class="mt-1 text-xs text-gray-500">
                            <span v-if="form.name">Preview: <span class="font-mono font-semibold">{{ accountCodePreview }}</span></span>
                            <span v-else>Will be auto-generated based on account name</span>
                        </div>
                        <div v-if="form.errors.account_code" class="text-sm text-red-600">{{ form.errors.account_code }}</div>
                    </label>
                </div>
            </div>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">Notes</span>
                <textarea v-model="form.notes" class="w-full rounded border px-3 py-2"></textarea>
            </label>
            <label class="flex items-center gap-2">
                <input v-model="form.is_default_sales" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                <span class="text-sm text-gray-700">Set as default sales customer</span>
            </label>
            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Create</button>
                <Link :href="customers.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
