<script setup lang="ts">
import { useNumberFormat } from '@/composables/useNumberFormat';
import AddressAutocompleteInput from '@/components/AddressAutocompleteInput.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import customers from '@/routes/customers';

const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];

const props = defineProps<{
    customer: any
    accountBalance: {
        outstanding_invoices: number
        unapplied_credit: number
        account_balance: number
    }
}>();

const { formatCurrency } = useNumberFormat();

interface CustomerFormData {
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    country: string;
    terms: string;
    vat_number: string;
    account_code: string;
    notes: string;
    is_default_sales: boolean;
}

const form = useForm<CustomerFormData>({
    name: props.customer.name ?? '',
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
    city: props.customer.city ?? '',
    country: props.customer.country ?? '',
    terms: props.customer.terms ?? 'COD',
    vat_number: props.customer.vat_number ?? '',
    account_code: props.customer.account_code ?? '',
    notes: props.customer.notes ?? '',
    is_default_sales: props.customer.is_default_sales ?? false,
});

function submit() {
    form.put(customers.update(props.customer.id).url);
}
</script>

<template>
    <Head title="Edit Customer" />

    <AppLayout :breadcrumbs="[{ title: 'Customers', href: customers.index().url }, { title: 'Edit', href: '#' }]">
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
                    <input v-model="form.account_code" class="w-full rounded border px-3 py-2" />
                    <div v-if="form.errors.account_code" class="text-sm text-red-600">{{ form.errors.account_code }}</div>
                </label>
                <div class="block md:col-span-2 rounded border border-dashed border-gray-200 bg-gray-50 p-4">
                    <span class="mb-2 block text-sm font-medium text-gray-700">Account balance (read-only, JCO only)</span>
                    <p class="mb-3 text-xs text-gray-500">Calculated from open invoices and unapplied credit notes in this app — not synced with Xero.</p>
                    <div class="grid gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <div class="text-gray-500">Outstanding invoices</div>
                            <div class="font-medium">{{ formatCurrency(props.accountBalance.outstanding_invoices) }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Unapplied credit</div>
                            <div class="font-medium">{{ formatCurrency(props.accountBalance.unapplied_credit) }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Net balance</div>
                            <div class="font-semibold">{{ formatCurrency(props.accountBalance.account_balance) }}</div>
                        </div>
                    </div>
                </div>
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
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save</button>
                <Link :href="customers.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>


