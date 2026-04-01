<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    customer: {
        id: number;
        name: string;
        email: string;
        phone?: string | null;
        address?: string | null;
        city?: string | null;
        country?: string | null;
        vat_number?: string | null;
        notes?: string | null;
    };
}>();

const updateForm = useForm({
    name: props.customer.name ?? '',
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    address: props.customer.address ?? '',
    city: props.customer.city ?? '',
    country: props.customer.country ?? '',
    vat_number: props.customer.vat_number ?? '',
    notes: props.customer.notes ?? '',
});

const submitRequest = () => {
    updateForm.post('/client-zone/update-request');
};
</script>

<template>
    <Head title="Request Info Update" />
    <AppLayout :breadcrumbs="[{ title: 'Client Zone', href: '/client-zone' }, { title: 'Request Info Update', href: '/client-zone/request-update' }]">
        <div class="space-y-6 p-4">
            <h1 class="text-2xl font-semibold">Request Customer Information Update</h1>
            <div class="rounded border p-4">
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="submitRequest">
                    <input v-model="updateForm.name" class="rounded border px-3 py-2" placeholder="Name" required />
                    <input v-model="updateForm.email" class="rounded border px-3 py-2" placeholder="Email" type="email" required />
                    <input v-model="updateForm.phone" class="rounded border px-3 py-2" placeholder="Phone" />
                    <input v-model="updateForm.vat_number" class="rounded border px-3 py-2" placeholder="VAT Number" />
                    <input v-model="updateForm.address" class="rounded border px-3 py-2 md:col-span-2" placeholder="Address" />
                    <input v-model="updateForm.city" class="rounded border px-3 py-2" placeholder="City" />
                    <input v-model="updateForm.country" class="rounded border px-3 py-2" placeholder="Country" />
                    <textarea v-model="updateForm.notes" class="rounded border px-3 py-2 md:col-span-2" rows="3" placeholder="Notes" />
                    <button type="submit" class="rounded bg-blue-600 px-4 py-2 text-white md:col-span-2" :disabled="updateForm.processing">
                        {{ updateForm.processing ? 'Submitting...' : 'Submit update request' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
