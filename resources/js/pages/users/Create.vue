<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import users from '@/routes/users';

const props = defineProps<{ 
    groups: { id: number; name: string }[];
    companies: { id: number; name: string }[];
}>();
const form = useForm({ 
    name: '', 
    email: '', 
    user_type: 'standard' as 'standard' | 'limited' | 'info' | 'client',
    hourly_rate: null as number | null,
    password: '', 
    groups: [] as number[],
    companies: [] as number[]
});
function submit() { form.post(users.store().url); }
</script>

<template>
    <Head title="New User" />
    <AppLayout :breadcrumbs="[{ title: 'Users', href: users.index().url }, { title: 'Create', href: '#' }]">
        <form @submit.prevent="submit" class="space-y-4 p-4">
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
                <span class="mb-1 block">User Type</span>
                <select v-model="form.user_type" class="w-full rounded border px-3 py-2">
                    <option value="standard">Standard</option>
                    <option value="limited">Limited</option>
                    <option value="info">Info</option>
                    <option value="client">Client</option>
                </select>
                <p v-if="form.user_type === 'limited'" class="mt-1 text-xs text-gray-500">Limited users can only view jobcards, change jobcard status, and create booked time.</p>
                <p v-if="form.user_type === 'info'" class="mt-1 text-xs text-gray-500">Info users are for reference only and will not have login access.</p>
                <p v-if="form.user_type === 'client'" class="mt-1 text-xs text-gray-500">Client users must be linked to a customer account for portal access.</p>
                <div v-if="form.errors.user_type" class="text-sm text-red-600">{{ form.errors.user_type }}</div>
            </label>
            <label class="block">
                <span class="mb-1 block">Hourly Rate (R)</span>
                <input v-model.number="form.hourly_rate" type="number" step="0.01" min="0" class="w-full rounded border px-3 py-2" placeholder="0.00" />
                <p class="mt-1 text-xs text-gray-500">Default hourly rate used when logging time on jobcards.</p>
                <div v-if="form.errors.hourly_rate" class="text-sm text-red-600">{{ form.errors.hourly_rate }}</div>
            </label>
            <label v-if="form.user_type !== 'info'" class="block">
                <span class="mb-1 block">Password</span>
                <input v-model="form.password" type="password" class="w-full rounded border px-3 py-2" />
                <div v-if="form.errors.password" class="text-sm text-red-600">{{ form.errors.password }}</div>
            </label>
            
            <div class="rounded border p-4">
                <div class="mb-2 font-medium">Groups</div>
                <div class="grid gap-2 md:grid-cols-3">
                    <label v-for="g in props.groups" :key="g.id" class="flex items-center gap-2">
                        <input type="checkbox" :value="g.id" v-model="form.groups" />
                        <span>{{ g.name }}</span>
                    </label>
                </div>
            </div>

            <div class="rounded border p-4">
                <div class="mb-2 font-medium">Company Access</div>
                <p class="mb-3 text-sm text-gray-600">Select which companies this user can access. If no companies are selected, the user will have access to all companies.</p>
                <div class="grid gap-2 md:grid-cols-2">
                    <label v-for="company in props.companies" :key="company.id" class="flex items-center gap-2">
                        <input type="checkbox" :value="company.id" v-model="form.companies" />
                        <span>{{ company.name }}</span>
                    </label>
                </div>
                <div v-if="form.errors.companies" class="text-sm text-red-600 mt-2">{{ form.errors.companies }}</div>
            </div>

            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Create</button>
                <Link :href="users.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>


