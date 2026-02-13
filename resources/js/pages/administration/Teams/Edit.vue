<template>
    <Head :title="`Edit ${props.team.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Teams', href: '/administration/teams' },
        { title: props.team.name, href: `/administration/teams/${props.team.id}` },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Team: {{ props.team.name }}</h1>
            </div>

            <form @submit.prevent="submit" class="max-w-2xl space-y-6">
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Team Details</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.name }"
                                required
                            />
                            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.description }"
                            ></textarea>
                            <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">{{ form.errors.description }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Team Members</h2>
                    <p class="text-sm text-gray-500 mb-4">Select users to add to this team.</p>
                    <div v-if="props.users.length === 0" class="text-sm text-gray-500">No users available.</div>
                    <div v-else class="space-y-2 max-h-64 overflow-y-auto">
                        <label
                            v-for="user in props.users"
                            :key="user.id"
                            class="flex items-center gap-3 rounded-lg border px-4 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                            :class="{ 'border-blue-300 bg-blue-50': form.user_ids.includes(user.id) }"
                        >
                            <input
                                type="checkbox"
                                :value="user.id"
                                v-model="form.user_ids"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                {{ user.name.charAt(0).toUpperCase() }}
                            </span>
                            <span class="text-sm font-medium text-gray-900">{{ user.name }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link :href="`/administration/teams/${props.team.id}`" class="rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Updating...' : 'Update Team' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

interface AppUser {
    id: number;
    name: string;
}

interface Team {
    id: number;
    name: string;
    description: string | null;
    users: AppUser[];
}

const props = defineProps<{
    team: Team;
    users: AppUser[];
}>();

const form = useForm({
    name: props.team.name,
    description: props.team.description || '',
    user_ids: props.team.users.map(u => u.id),
});

const submit = () => {
    form.put(`/administration/teams/${props.team.id}`);
};
</script>
