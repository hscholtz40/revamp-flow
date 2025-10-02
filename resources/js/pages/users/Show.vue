<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import users from '@/routes/users';

const props = defineProps<{ 
    user: { 
        id: number; 
        name: string; 
        email: string; 
        created_at?: string; 
        updated_at?: string;
        groups?: { id: number; name: string }[];
        companies?: { id: number; name: string }[];
    } 
}>();
</script>

<template>
    <Head :title="`User • ${props.user.name}`" />

    <AppLayout :breadcrumbs="[{ title: 'Users', href: users.index().url }, { title: props.user.name, href: '#' }]">
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ props.user.name }}</h1>
                <div class="flex items-center gap-2">
                    <Link :href="users.edit(props.user.id).url" class="rounded border px-3 py-1">Edit</Link>
                    <Link :href="users.index().url" class="rounded border px-3 py-1">Back</Link>
                </div>
            </div>

            <div class="rounded border p-4">
                <div class="space-y-2">
                    <div><span class="font-medium">Name:</span> {{ props.user.name }}</div>
                    <div><span class="font-medium">Email:</span> {{ props.user.email }}</div>
                </div>
            </div>

            <div v-if="props.user.groups && props.user.groups.length > 0" class="rounded border p-4">
                <div class="mb-2 font-medium">Groups</div>
                <div class="flex flex-wrap gap-2">
                    <span v-for="group in props.user.groups" :key="group.id" 
                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ group.name }}
                    </span>
                </div>
            </div>

            <div v-if="props.user.companies && props.user.companies.length > 0" class="rounded border p-4">
                <div class="mb-2 font-medium">Company Access</div>
                <div class="flex flex-wrap gap-2">
                    <span v-for="company in props.user.companies" :key="company.id" 
                          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        {{ company.name }}
                    </span>
                </div>
            </div>
            <div v-else class="rounded border p-4">
                <div class="mb-2 font-medium">Company Access</div>
                <p class="text-sm text-gray-600">This user has access to all companies.</p>
            </div>

            <div class="text-sm text-gray-500">
                <div>Created: {{ props.user.created_at }}</div>
                <div>Updated: {{ props.user.updated_at }}</div>
            </div>
        </div>
    </AppLayout>
</template>


