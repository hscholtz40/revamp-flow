<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    task: {
        id: number;
        title: string;
        description?: string | null;
        status: string;
        assigned_user?: { id: number; name: string } | null;
        assigned_team?: { id: number; name: string } | null;
        jobcard?: { id: number; job_number: string; title?: string | null } | null;
        created_by?: { id: number; name: string } | null;
        scheduled_start_at?: string | null;
        scheduled_end_at?: string | null;
        completed_at?: string | null;
    };
    canUpdateStatus: boolean;
    statusOptions: Array<{ value: string; label: string }>;
}>();

const assigneeLabel = computed(() => props.task.assigned_user?.name || props.task.assigned_team?.name || 'Unassigned');
const statusOptions = computed(() => props.statusOptions || []);
const canChangeStatus = computed(() => props.canUpdateStatus);

const statusBadgeClass = (status: string) => {
    if (status === 'completed') return 'bg-green-100 text-green-800';
    if (status === 'accepted') return 'bg-cyan-100 text-cyan-800';
    if (status === 'scheduled') return 'bg-blue-100 text-blue-800';
    if (status === 'cancelled') return 'bg-red-100 text-red-800';
    return 'bg-slate-100 text-slate-800';
};

const formatStatus = (code: string) => {
    if (!code) return '';
    const opt = props.statusOptions?.find((o) => o.value === code);
    if (opt) return opt.label;
    return code.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const canUpdateToStatus = (status: string) => {
    if (!props.canUpdateStatus) {
        return false;
    }
    if (status === props.task.status) {
        return false;
    }
    return true;
};

const deleteTask = () => {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    router.delete(`/tasks/${props.task.id}`);
};

const updateStatus = (value: string) => {
    if (!canUpdateToStatus(value)) {
        return;
    }
    router.put(
        `/tasks/${props.task.id}`,
        { status: value },
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};
</script>

<template>
    <Head :title="`Task ${task.id}`" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: '/tasks' }, { title: task.title || `Task #${task.id}`, href: `/tasks/${task.id}` }]">
        <div class="space-y-4 p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ task.title || `Task #${task.id}` }}</h1>
                    <p class="text-sm text-gray-600">Task details, assignment and timeline.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="`/tasks/${task.id}/edit`" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Edit
                    </Link>
                    <button type="button" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-100" @click="deleteTask">
                        Delete
                    </button>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Overview</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Status</dt>
                            <dd>
                                <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusBadgeClass(task.status)">
                                    {{ formatStatus(task.status) }}
                                </span>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Assigned</dt>
                            <dd class="text-right text-gray-900">{{ assigneeLabel }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Jobcard</dt>
                            <dd class="text-right">
                                <Link v-if="task.jobcard?.id" :href="`/jobcards/${task.jobcard.id}`" class="text-indigo-600 hover:underline">
                                    {{ task.jobcard.job_number }}
                                </Link>
                                <span v-else class="text-gray-900">None</span>
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Scheduled start</dt>
                            <dd class="text-right text-gray-900">{{ task.scheduled_start_at || '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Scheduled end</dt>
                            <dd class="text-right text-gray-900">{{ task.scheduled_end_at || '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-gray-500">Completed at</dt>
                            <dd class="text-right text-gray-900">{{ task.completed_at || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500">Description</h2>
                    <p class="whitespace-pre-wrap text-sm text-gray-800">{{ task.description || 'No description provided.' }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
                    <h2 class="text-base font-semibold text-gray-900">Status Management</h2>
                    <p class="text-sm text-gray-600">Update task status and track progress</p>
                </div>
                <div class="p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <template v-if="canChangeStatus">
                            <button
                                v-for="status in statusOptions"
                                :key="status.value"
                                type="button"
                                :disabled="!canUpdateToStatus(status.value)"
                                :class="[
                                    'rounded-md border px-3 py-1 text-sm font-medium transition-colors',
                                    task.status === status.value
                                        ? 'border-blue-200 bg-blue-100 text-blue-800'
                                        : 'border-gray-200 bg-gray-100 text-gray-700 hover:bg-gray-200',
                                    !canUpdateToStatus(status.value) ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                                ]"
                                @click="updateStatus(status.value)"
                            >
                                {{ status.label }}
                            </button>
                        </template>
                        <template v-else>
                            <span
                                v-for="status in statusOptions"
                                :key="status.value"
                                :class="[
                                    'rounded-md border px-3 py-1 text-sm font-medium',
                                    task.status === status.value
                                        ? 'border-blue-200 bg-blue-100 text-blue-800'
                                        : 'border-gray-200 bg-gray-50 text-gray-400',
                                ]"
                            >
                                {{ status.label }}
                            </span>
                        </template>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border bg-white p-4 text-sm text-gray-600">
                Notes for this task are available in the shared <span class="font-medium text-gray-800">Record Notes</span> panel below, matching other modules.
            </div>
        </div>
    </AppLayout>
</template>
