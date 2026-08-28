<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import DashboardQuickActionsFields from '@/components/dashboard/DashboardQuickActionsFields.vue';
import { createDefaultDashboardQuickActions } from '@/composables/useDashboardQuickActions';
import { Head, Link, useForm } from '@inertiajs/vue3';
import users from '@/routes/users';

const props = defineProps<{ 
    user: any; 
    groups: { id: number; name: string }[];
    companies: { id: number; name: string }[];
    dashboardQuickActionOptions: Array<{ key: string; label: string; group: string }>;
}>();

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    user_type: (props.user.user_type ?? 'standard') as 'standard' | 'limited' | 'info' | 'client',
    hourly_rate: props.user.hourly_rate ?? null,
    password: '',
    groups: (props.user.groups ?? []).map((g: any) => g.id) as number[],
    companies: (props.user.companies ?? []).map((c: any) => c.id) as number[],
    dashboard_quick_actions: {
        ...createDefaultDashboardQuickActions(),
        ...(props.user.dashboard_quick_actions ?? {}),
    },
});

function submit() { form.put(users.update(props.user.id).url); }
</script>

<template>
    <Head title="Edit User" />
    <AppLayout :breadcrumbs="[{ title: 'Users', href: users.index().url }, { title: 'Edit', href: '#' }]">
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
                <p v-if="form.user_type === 'client'" class="mt-1 text-xs text-gray-500">Client users should remain linked to a customer record.</p>
                <div v-if="form.errors.user_type" class="text-sm text-red-600">{{ form.errors.user_type }}</div>
            </label>
            <label class="block">
                <span class="mb-1 block">Hourly Rate (R)</span>
                <input v-model.number="form.hourly_rate" type="number" step="0.01" min="0" class="w-full rounded border px-3 py-2" placeholder="0.00" />
                <p class="mt-1 text-xs text-gray-500">Default hourly rate used when logging time on jobcards.</p>
                <div v-if="form.errors.hourly_rate" class="text-sm text-red-600">{{ form.errors.hourly_rate }}</div>
            </label>
            <label v-if="form.user_type !== 'info'" class="block">
                <span class="mb-1 block">Password (leave blank to keep)</span>
                <input v-model="form.password" type="password" class="w-full rounded border px-3 py-2" />
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

            <div class="rounded border p-4">
                <div class="mb-2 font-medium">Dashboard Quick Actions</div>
                <p class="mb-3 text-sm text-gray-600">Choose which quick action buttons appear on this user's dashboard.</p>
                <DashboardQuickActionsFields
                    v-model="form.dashboard_quick_actions"
                    :options="props.dashboardQuickActionOptions"
                />
                <div v-if="form.errors.dashboard_quick_actions" class="mt-2 text-sm text-red-600">{{ form.errors.dashboard_quick_actions }}</div>
            </div>

            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save</button>
                <Link :href="users.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
