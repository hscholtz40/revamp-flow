<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    can_update_status?: boolean;
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
        view?: 'list' | 'kanban';
    };
    statusOptions: Array<{ value: string; label: string }>;
}>();
const canTasksEdit = useAuthAbility('tasks', 'edit');
const canTasksDelete = useAuthAbility('tasks', 'delete');

const filters = ref({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    view: props.filters.view === 'kanban' ? 'kanban' : 'list',
});

const tasksByStatus = computed(() => {
    const grouped = Object.fromEntries(props.statusOptions.map((s) => [s.value, [] as TaskItem[]])) as Record<string, TaskItem[]>;
    for (const task of props.tasks.data) {
        if (!grouped[task.status]) {
            grouped[task.status] = [];
        }
        grouped[task.status].push(task);
    }
    return grouped;
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

const openTask = (taskId: number) => router.visit(`/tasks/${taskId}`);
const deleteTask = (taskId: number) => {
    if (!confirm('Delete this task? This cannot be undone.')) return;
    router.delete(`/tasks/${taskId}`, { preserveScroll: true });
};

const dragTaskId = ref<number | null>(null);
const dragFromStatus = ref<string | null>(null);
const dropStatus = ref<string | null>(null);

const canDragTask = (task: TaskItem) => !!task.can_update_status;

const onTaskDragStart = (task: TaskItem) => {
    if (!canDragTask(task)) return;
    dragTaskId.value = task.id;
    dragFromStatus.value = task.status;
};

const onTaskDragEnd = () => {
    dragTaskId.value = null;
    dragFromStatus.value = null;
    dropStatus.value = null;
};

const onColumnDragOver = (status: string, event: DragEvent) => {
    if (!dragTaskId.value) return;
    event.preventDefault();
    dropStatus.value = status;
};

const onColumnDrop = (status: string, event: DragEvent) => {
    event.preventDefault();
    if (!dragTaskId.value || !dragFromStatus.value) return;
    if (dragFromStatus.value === status) return onTaskDragEnd();

    const taskId = dragTaskId.value;
    onTaskDragEnd();

    router.put(
        `/tasks/${taskId}`,
        { status },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
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
                    class="inline-flex items-center rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    Create Task
                </Link>
            </div>

            <div class="rounded-lg border bg-white p-4">
                <div class="mb-4 inline-flex rounded-md border bg-white p-1">
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="filters.view === 'list' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="filters.view = 'list'"
                    >
                        List
                    </button>
                    <button
                        type="button"
                        class="rounded px-3 py-1.5 text-sm font-medium transition"
                        :class="filters.view === 'kanban' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                        @click="filters.view = 'kanban'"
                    >
                        Kanban
                    </button>
                </div>
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

            <div v-if="filters.view === 'list'" class="overflow-hidden rounded-lg border bg-white">
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
                            <tr
                                v-for="task in tasks.data"
                                :key="task.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="openTask(task.id)"
                                @keydown.enter.prevent="openTask(task.id)"
                                @keydown.space.prevent="openTask(task.id)"
                            >
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
                                <td class="px-4 py-3 text-right text-sm" @click.stop>
                                    <div class="inline-flex items-center gap-2">
                                        <Link
                                            v-if="canTasksEdit"
                                            :href="`/tasks/${task.id}/edit`"
                                            class="inline-flex items-center justify-center rounded bg-gray-200 px-2 py-1.5 md:px-3 md:py-1 text-sm font-medium text-gray-800 hover:bg-gray-300"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="canTasksDelete"
                                            type="button"
                                            class="inline-flex items-center justify-center rounded bg-red-100 px-2 py-1.5 md:px-3 md:py-1 text-sm font-medium text-red-700 hover:bg-red-200"
                                            @click="deleteTask(task.id)"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
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
                                link.active ? 'border-blue-600 bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                                !link.url ? 'pointer-events-none opacity-50' : '',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>

            <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="status in statusOptions"
                    :key="status.value"
                    class="rounded-lg border bg-white"
                    :class="dropStatus === status.value ? 'ring-2 ring-blue-400' : ''"
                    @dragover="onColumnDragOver(status.value, $event)"
                    @dragleave="dropStatus = null"
                    @drop="onColumnDrop(status.value, $event)"
                >
                    <div class="border-b bg-gray-50 px-3 py-2">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-gray-800">{{ status.label }}</h2>
                            <span class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-700">
                                {{ tasksByStatus[status.value]?.length ?? 0 }}
                            </span>
                        </div>
                    </div>
                    <div class="max-h-[65vh] space-y-2 overflow-y-auto p-3">
                        <button
                            v-for="task in tasksByStatus[status.value]"
                            :key="task.id"
                            type="button"
                            :draggable="canDragTask(task)"
                            class="w-full rounded border border-gray-200 bg-white p-3 text-left shadow-sm transition hover:border-blue-300 hover:bg-blue-50/30"
                            :class="canDragTask(task) ? 'cursor-move' : 'cursor-pointer'"
                            @dragstart="onTaskDragStart(task)"
                            @dragend="onTaskDragEnd"
                            @click="openTask(task.id)"
                        >
                            <p class="text-sm font-semibold text-gray-900">{{ task.title }}</p>
                            <p class="mt-1 text-xs text-gray-600">{{ task.assigned_user?.name || task.assigned_team?.name || 'Unassigned' }}</p>
                            <p v-if="task.jobcard?.job_number" class="mt-1 text-xs text-gray-500">Jobcard {{ task.jobcard.job_number }}</p>
                            <p class="mt-2 text-[11px] text-gray-500">Notes: {{ task.notes_count ?? 0 }}</p>
                            <p v-if="!canDragTask(task)" class="mt-1 text-[11px] text-amber-700">No status-change access</p>
                        </button>
                        <div v-if="(tasksByStatus[status.value]?.length ?? 0) === 0" class="rounded border border-dashed border-gray-200 px-3 py-4 text-center text-xs text-gray-500">
                            No tasks
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
