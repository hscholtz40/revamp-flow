<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import contacts from '@/routes/contacts';

interface Customer {
    id: number;
    name: string;
}

const props = defineProps<{
    customers: Customer[];
    selectedCustomerId?: number;
}>();

const form = useForm({
    customer_id: props.selectedCustomerId || '',
    name: '',
    email: '',
    phone: '',
    position: '',
    notes: '',
    is_primary: false,
});

function submit() {
    form.post(contacts.store().url);
}
</script>

<template>
    <Head title="New Contact" />

    <AppLayout :breadcrumbs="[
        { title: 'Contacts', href: contacts.index().url },
        { title: 'Create', href: '#' }
    ]">
        <form @submit.prevent="submit" class="space-y-4 p-4">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block">Customer *</span>
                    <select v-model="form.customer_id" class="w-full rounded border px-3 py-2" required>
                        <option value="">Select a customer</option>
                        <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                    <div v-if="form.errors.customer_id" class="text-sm text-red-600">{{ form.errors.customer_id }}</div>
                </label>

                <label class="block">
                    <span class="mb-1 block">Name *</span>
                    <input v-model="form.name" class="w-full rounded border px-3 py-2" required />
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
                    <div v-if="form.errors.phone" class="text-sm text-red-600">{{ form.errors.phone }}</div>
                </label>

                <label class="block">
                    <span class="mb-1 block">Position</span>
                    <input v-model="form.position" class="w-full rounded border px-3 py-2" />
                    <div v-if="form.errors.position" class="text-sm text-red-600">{{ form.errors.position }}</div>
                </label>

                <label class="block">
                    <span class="mb-1 block">Primary Contact</span>
                    <label class="flex items-center">
                        <input v-model="form.is_primary" type="checkbox" class="mr-2" />
                        <span class="text-sm">Mark as primary contact for this customer</span>
                    </label>
                </label>
            </div>

            <label class="block">
                <span class="mb-1 block">Notes</span>
                <textarea v-model="form.notes" rows="3" class="w-full rounded border px-3 py-2"></textarea>
                <div v-if="form.errors.notes" class="text-sm text-red-600">{{ form.errors.notes }}</div>
            </label>

            <div class="flex items-center gap-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50"
                >
                    {{ form.processing ? 'Creating...' : 'Create Contact' }}
                </button>
                <Link :href="contacts.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
