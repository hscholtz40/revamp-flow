<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import customers from '@/routes/customers';

const props = defineProps<{ customer: any }>();

const form = useForm({
    name: props.customer.name ?? '',
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
    city: props.customer.city ?? '',
    country: props.customer.country ?? '',
    vat_number: props.customer.vat_number ?? '',
    account_code: props.customer.account_code ?? '',
    notes: props.customer.notes ?? '',
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
                    <input v-model="form.address" class="w-full rounded border px-3 py-2" />
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
                    <span class="mb-1 block">VAT Number</span>
                    <input v-model="form.vat_number" class="w-full rounded border px-3 py-2" />
                </label>
                <label class="block">
                    <span class="mb-1 block">Account Code</span>
                    <input v-model="form.account_code" class="w-full rounded border px-3 py-2" />
                    <div v-if="form.errors.account_code" class="text-sm text-red-600">{{ form.errors.account_code }}</div>
                </label>
            </div>
            <label class="block">
                <span class="mb-1 block">Notes</span>
                <textarea v-model="form.notes" class="w-full rounded border px-3 py-2"></textarea>
            </label>
            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save</button>
                <Link :href="customers.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>


