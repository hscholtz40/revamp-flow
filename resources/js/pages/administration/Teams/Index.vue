<template>
    <Head title="Teams" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Teams', href: '#' }
    ]">
        <div class="p-4">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Teams</h1>
                <Link href="/administration/teams/create" class="rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors">
                    New Team
                </Link>
            </div>

            <div v-if="props.teams.length === 0" class="bg-white rounded-lg border p-12 text-center">
                <UsersRound class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-4 text-lg font-medium text-gray-900">No teams yet</h3>
                <p class="mt-2 text-sm text-gray-500">Create a team to organize your users and assign jobcards.</p>
                <Link href="/administration/teams/create" class="mt-4 inline-block rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors">
                    Create First Team
                </Link>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="team in props.teams" :key="team.id" class="bg-white rounded-lg border shadow-sm hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900">{{ team.name }}</h3>
                                <p v-if="team.description" class="mt-1 text-sm text-gray-500 line-clamp-2">{{ team.description }}</p>
                            </div>
                            <div class="rounded-full bg-primary/10 p-2">
                                <UsersRound class="h-5 w-5 text-primary" />
                            </div>
                        </div>
                        <div class="mt-4 flex items-center gap-4 text-sm text-gray-600">
                            <span class="flex items-center gap-1">
                                <UserIcon class="h-4 w-4" />
                                {{ team.users_count }} {{ team.users_count === 1 ? 'member' : 'members' }}
                            </span>
                            <span class="flex items-center gap-1">
                                <ClipboardList class="h-4 w-4" />
                                {{ team.jobcards_count }} {{ team.jobcards_count === 1 ? 'jobcard' : 'jobcards' }}
                            </span>
                        </div>
                    </div>
                    <div class="border-t px-6 py-3 flex items-center gap-2">
                        <Link :href="`/administration/teams/${team.id}`" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                            View
                        </Link>
                        <Link :href="`/administration/teams/${team.id}/edit`" class="text-sm font-medium text-indigo-600 hover:text-indigo-800">
                            Edit
                        </Link>
                        <button @click="deleteTeam(team)" class="text-sm font-medium text-red-600 hover:text-red-800">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { UsersRound, User as UserIcon, ClipboardList } from 'lucide-vue-next';

interface Team {
    id: number;
    name: string;
    description: string | null;
    users_count: number;
    jobcards_count: number;
}

const props = defineProps<{
    teams: Team[];
}>();

const deleteTeam = (team: Team) => {
    if (confirm(`Are you sure you want to delete team "${team.name}"?`)) {
        router.delete(`/administration/teams/${team.id}`);
    }
};
</script>
