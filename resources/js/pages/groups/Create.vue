<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import groups from '@/routes/groups';

const form = useForm({ 
    name: '', 
    description: '', 
    is_administrator: false 
});
function submit() { form.post(groups.store().url); }
</script>

<template>
    <Head title="New Group" />
    <AppLayout :breadcrumbs="[{ title: 'Groups', href: groups.index().url }, { title: 'Create', href: '#' }]">
        <form @submit.prevent="submit" class="space-y-4 p-4">
            <label class="block">
                <span class="mb-1 block">Name</span>
                <input v-model="form.name" class="w-full rounded border px-3 py-2" required />
                <div v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</div>
            </label>
            
            <label class="block">
                <span class="mb-1 block">Description</span>
                <textarea v-model="form.description" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                <div v-if="form.errors.description" class="text-sm text-red-600">{{ form.errors.description }}</div>
            </label>
            
            <label class="flex items-center space-x-2">
                <input 
                    type="checkbox" 
                    v-model="form.is_administrator" 
                    class="rounded border-gray-300"
                />
                <span class="text-sm font-medium">Administrator Group</span>
                <div v-if="form.errors.is_administrator" class="text-sm text-red-600">{{ form.errors.is_administrator }}</div>
            </label>
            <p class="text-xs text-gray-600">
                Administrator groups have access to all administration functions and system settings.
            </p>
            
            <div class="flex items-center gap-3">
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Create</button>
                <Link :href="groups.index().url" class="rounded border px-4 py-2">Cancel</Link>
            </div>
        </form>
    </AppLayout>
</template>


