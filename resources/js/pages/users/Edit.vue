<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import users from '@/routes/users';
import { computed } from 'vue';

const props = defineProps<{ 
    user: any; 
    groups: { id: number; name: string }[];
    companies: { id: number; name: string }[];
}>();

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    password: '',
    groups: (props.user.groups ?? []).map((g: any) => g.id) as number[],
    companies: (props.user.companies ?? []).map((c: any) => c.id) as number[],
    smtp_host: props.user.smtp_host ?? '',
    smtp_port: props.user.smtp_port ?? 587,
    smtp_username: props.user.smtp_username ?? '',
    smtp_password: '',
    smtp_encryption: props.user.smtp_encryption ?? 'tls',
    smtp_from_email: props.user.smtp_from_email ?? '',
    smtp_from_name: props.user.smtp_from_name ?? '',
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

            <!-- SMTP Settings Section -->
            <div class="rounded border p-4">
                <div class="mb-4 font-medium">SMTP Settings (Optional)</div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block">SMTP Host</span>
                        <input v-model="form.smtp_host" type="text" class="w-full rounded border px-3 py-2" placeholder="smtp.gmail.com" />
                        <div v-if="form.errors.smtp_host" class="text-sm text-red-600">{{ form.errors.smtp_host }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block">SMTP Port</span>
                        <input v-model="form.smtp_port" type="number" class="w-full rounded border px-3 py-2" placeholder="587" />
                        <div v-if="form.errors.smtp_port" class="text-sm text-red-600">{{ form.errors.smtp_port }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block">SMTP Username</span>
                        <input v-model="form.smtp_username" type="text" class="w-full rounded border px-3 py-2" placeholder="your-email@gmail.com" />
                        <div v-if="form.errors.smtp_username" class="text-sm text-red-600">{{ form.errors.smtp_username }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block">SMTP Password (leave blank to keep current)</span>
                        <input v-model="form.smtp_password" type="password" class="w-full rounded border px-3 py-2" placeholder="App password or account password" />
                        <div v-if="form.errors.smtp_password" class="text-sm text-red-600">{{ form.errors.smtp_password }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block">Encryption</span>
                        <select v-model="form.smtp_encryption" class="w-full rounded border px-3 py-2">
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                        <div v-if="form.errors.smtp_encryption" class="text-sm text-red-600">{{ form.errors.smtp_encryption }}</div>
                    </label>
                    <label class="block">
                        <span class="mb-1 block">From Email</span>
                        <input v-model="form.smtp_from_email" type="email" class="w-full rounded border px-3 py-2" placeholder="noreply@company.com" />
                        <div v-if="form.errors.smtp_from_email" class="text-sm text-red-600">{{ form.errors.smtp_from_email }}</div>
                    </label>
                    <label class="block md:col-span-2">
                        <span class="mb-1 block">From Name</span>
                        <input v-model="form.smtp_from_name" type="text" class="w-full rounded border px-3 py-2" placeholder="Company Name" />
                        <div v-if="form.errors.smtp_from_name" class="text-sm text-red-600">{{ form.errors.smtp_from_name }}</div>
                    </label>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save</button>
                <Link :href="users.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>
