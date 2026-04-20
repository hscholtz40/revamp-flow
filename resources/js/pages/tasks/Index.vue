<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface TaskItem {
    id: number;
    title: string;
    status: string;
    description?: string | null;
    assigned_user?: { name: string } | null;
    assigned_team?: { name: string } | null;
    scheduled_start_at?: string | null;
    scheduled_end_at?: string | null;
    notes_count?: number;
    jobcard?: { job_number: string } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    tasks: {
        data: TaskItem[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
    statusOptions: Array<{ value: string; label: string }>;
}>();

const filters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

const statusBadgeClass = (status: string) => {
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'accepted') return 'bg-cyan-100 text-cyan-800';
    if (status === 'scheduled') return 'bg-blue-100 text-blue-800';
    if (status === 'cancelled') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-800';
};

const clearFilters = () => {
    filters.value.search = '';
    filters.value.status = '';
};

watch(
    filters,
    (value) => {
        router.get('/tasks', value, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    },
    { deep: true },
);
</script>

<template>
    <Head title="Tasks" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: '/tasks' }]">
        <div class="p-4 space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tasks</h1>
                    <p class="text-sm text-gray-600">Manage scheduled tasks and assignments for your teams and technicians.</p>
                </div>
                <Link
                    href="/tasks/create"
                    class="inline-flex items-center rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    Create Task
                </Link>
            </div>

            <div class="rounded-lg border bg-white p-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Search title or description"
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="filters.status" class="w-full rounded border px-3 py-2">
                            <option value="">All statuses</option>
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="button" class="rounded border px-3 py-2 text-sm hover:bg-gray-50" @click="clearFilters">
                        Clear filters
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Assignment</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Schedule</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Notes</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="task in tasks.data" :key="task.id">
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    <div class="font-medium">{{ task.title }}</div>
                                    <div v-if="task.jobcard?.job_number" class="text-xs text-gray-500">Jobcard {{ task.jobcard.job_number }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeClass(task.status)">
                                        {{ task.status.replace('_', ' ') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ task.assigned_user?.name || task.assigned_team?.name || 'Unassigned' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div v-if="task.scheduled_start_at || task.scheduled_end_at">
                                        <div>Start: {{ task.scheduled_start_at ?? '-' }}</div>
                                        <div>End: {{ task.scheduled_end_at ?? '-' }}</div>
                                    </div>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ task.notes_count ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <Link :href="`/tasks/${task.id}/edit`" class="text-indigo-600 hover:text-indigo-800">
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="tasks.data.length === 0">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No tasks found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t px-4 py-3">
                    <div class="text-sm text-gray-600">
                        Showing {{ tasks.from ?? 0 }} to {{ tasks.to ?? 0 }} of {{ tasks.total }} tasks
                    </div>
                    <div class="flex gap-2">
                        <Link
                            v-for="link in tasks.links"
                            :key="`${link.label}-${link.url}`"
                            :href="link.url || '#'"
                            :class="[
                                'rounded border px-3 py-1 text-sm',
                                link.active ? 'border-indigo-600 bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                !link.url ? 'pointer-events-none opacity-50' : '',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
