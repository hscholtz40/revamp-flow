<template>
    <Head :title="`Team: ${props.team.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Teams', href: '/administration/teams' },
        { title: props.team.name, href: '#' }
    ]">
        <div class="p-4 space-y-6">
            <!-- Header -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.team.name }}</h1>
                        <p v-if="props.team.description" class="text-sm text-gray-500 mt-1">{{ props.team.description }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="`/administration/teams/${props.team.id}/edit`"
                            class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90"
                        >
                            Edit
                        </Link>
                        <button
                            @click="deleteTeam"
                            class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Members -->
                <div class="lg:col-span-2">
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Members</h2>
                            <p class="text-sm text-gray-600">{{ props.team.users_count }} {{ props.team.users_count === 1 ? 'member' : 'members' }}</p>
                        </div>
                        <div class="p-6">
                            <div v-if="props.team.users.length === 0" class="text-center py-8 text-gray-500">
                                <p>No members assigned to this team yet.</p>
                                <Link :href="`/administration/teams/${props.team.id}/edit`" class="mt-2 inline-block text-sm font-medium text-blue-600 hover:text-blue-800">
                                    Add Members
                                </Link>
                            </div>
                            <div v-else class="space-y-3">
                                <div v-for="user in props.team.users" :key="user.id" class="flex items-center gap-3 rounded-lg border px-4 py-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                        {{ user.name.charAt(0).toUpperCase() }}
                                    </span>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ user.name }}</p>
                                        <p v-if="user.email" class="text-xs text-gray-500">{{ user.email }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Sidebar -->
                <div class="space-y-6">
                    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Team Info</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Members</label>
                                <p class="text-2xl font-bold text-primary">{{ props.team.users_count }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Assigned Jobcards</label>
                                <p class="text-2xl font-bold text-primary">{{ props.team.jobcards_count }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Created</label>
                                <p class="text-sm text-gray-900">{{ formatDate(props.team.created_at) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Jobcards -->
                    <div v-if="props.team.jobcards && props.team.jobcards.length > 0" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Assigned Jobcards</h2>
                        </div>
                        <div class="p-6 space-y-3">
                            <Link
                                v-for="jobcard in props.team.jobcards.slice(0, 5)"
                                :key="jobcard.id"
                                :href="`/jobcards/${jobcard.id}`"
                                class="block rounded-lg border px-4 py-3 hover:bg-gray-50 transition-colors"
                            >
                                <p class="text-sm font-medium text-gray-900">{{ jobcard.job_number }}</p>
                                <p class="text-xs text-gray-500">{{ jobcard.title }} &middot; {{ jobcard.customer?.name }}</p>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface AppUser {
    id: number;
    name: string;
    email?: string;
}

interface Jobcard {
    id: number;
    job_number: string;
    title: string;
    customer?: { id: number; name: string };
}

interface Team {
    id: number;
    name: string;
    description: string | null;
    users: AppUser[];
    jobcards: Jobcard[];
    users_count: number;
    jobcards_count: number;
    created_at: string;
}

const props = defineProps<{
    team: Team;
}>();

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
};

const deleteTeam = () => {
    if (confirm(`Are you sure you want to delete team "${props.team.name}"?`)) {
        router.delete(`/administration/teams/${props.team.id}`);
    }
};
</script>
