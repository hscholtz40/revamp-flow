<script setup lang="ts">
import AddressAutocompleteInput from '@/components/AddressAutocompleteInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import customers from '@/routes/customers';

const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    country: '',
    terms: 'COD',
    vat_number: '',
    account_code: '',
    notes: '',
    is_default_sales: false,
});

// Generate preview of account code based on name
const accountCodePreview = computed(() => {
    if (!form.name || form.name.trim().length === 0) {
        return '';
    }
    
    // Extract first 2 letters (uppercase, remove spaces and special chars)
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
        <form @submit.prevent="submit" class="space-y-4 p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block">Name</span>
                    <input v-model="form.name" class="w-full rounded border px-3 py-2" />
                    <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
                </label>
                <label class="block">
                    <span class="mb-1 block">Email</span>
                    <input v-model="form.email" type="email" class="w-full rounded border px-3 py-2" />
                    <div v-if="form.errors.email" class="text-sm text-red-600">{{ form.errors.email }}</div>
                </label>
                <label class="block">
                    <span class="mb-1 block">Phone</span>
                    <input v-model="form.phone" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">Address</span>
                    <AddressAutocompleteInput v-model="form.address" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">City</span>
                    <input v-model="form.city" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">Country</span>
                    <input v-model="form.country" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">Payment Terms</span>
                    <select v-model="form.terms" class="w-full rounded border px-3 py-2">
                        <option v-for="term in paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                    </select>
                    <div v-if="form.errors.terms" class="text-sm text-red-600">{{ form.errors.terms }}</div>
                </label>
                <label class="block">
                    <span class="mb-1 block">VAT Number</span>
                    <input v-model="form.vat_number" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">Account Code</span>
                    <input 
                        :value="accountCodePreview || 'Auto-generated'"
                        class="w-full rounded border px-3 py-2 bg-gray-50 cursor-not-allowed text-gray-600" 
                        placeholder="Auto-generated"
                        readonly
                        disabled
                    />
                    <div class="text-xs text-gray-500 mt-1">
                        <span v-if="form.name">Preview: <span class="font-mono font-semibold">{{ accountCodePreview }}</span></span>
                        <span v-else>Will be auto-generated based on customer name</span>
                        <span v-if="form.name"> (or next available number if code exists)</span>
                    </div>
                    <div v-if="form.errors.account_code" class="text-sm text-red-600">{{ form.errors.account_code }}</div>
                </label>
            </div>
            <label class="block">
                <span class="mb-1 block">Notes</span>
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


