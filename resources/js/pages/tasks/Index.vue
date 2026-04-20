<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    tasks: {
        data: Array<{
            id: number;
            title: string;
            status: string;
            assigned_user?: { name: string } | null;
            assigned_team?: { name: string } | null;
            scheduled_start_at?: string | null;
        }>;
    };
    assignableUsers: Array<{ id: number; name: string }>;
    assignableTeams: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    title: '',
    description: '',
    assigned_to_user_id: '',
    assigned_to_team_id: '',
    scheduled_start_at: '',
    scheduled_end_at: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        assigned_to_user_id: data.assigned_to_user_id ? Number(data.assigned_to_user_id) : null,
        assigned_to_team_id: data.assigned_to_team_id ? Number(data.assigned_to_team_id) : null,
        scheduled_start_at: data.scheduled_start_at || null,
        scheduled_end_at: data.scheduled_end_at || null,
    })).post('/tasks', {
        onSuccess: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Tasks" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: '/tasks' }]">
        <div class="p-6 space-y-4">
            <h1 class="text-2xl font-semibold">Tasks Module</h1>
            <p class="text-sm text-muted-foreground">Tasks can be scheduled, assigned to users/teams, and updated from web or mobile.</p>

            <form class="rounded border p-4 space-y-3" @submit.prevent="submit">
                <div class="text-sm font-medium">Create Task</div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label for="task-title" class="text-xs text-muted-foreground">Title</label>
                        <input id="task-title" v-model="form.title" type="text" class="w-full rounded border px-3 py-2 text-sm" />
                        <div v-if="form.errors.title" class="text-xs text-red-600">{{ form.errors.title }}</div>
                    </div>
                    <div class="space-y-1">
                        <label for="task-user" class="text-xs text-muted-foreground">Assign User</label>
                        <select id="task-user" v-model="form.assigned_to_user_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">Unassigned</option>
                            <option v-for="user in assignableUsers" :key="user.id" :value="String(user.id)">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label for="task-team" class="text-xs text-muted-foreground">Assign Team</label>
                        <select id="task-team" v-model="form.assigned_to_team_id" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">No Team</option>
                            <option v-for="team in assignableTeams" :key="team.id" :value="String(team.id)">
                                {{ team.name }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label for="task-start" class="text-xs text-muted-foreground">Start</label>
                        <input id="task-start" v-model="form.scheduled_start_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div class="space-y-1">
                        <label for="task-end" class="text-xs text-muted-foreground">End</label>
                        <input id="task-end" v-model="form.scheduled_end_at" type="datetime-local" class="w-full rounded border px-3 py-2 text-sm" />
                        <div v-if="form.errors.scheduled_end_at" class="text-xs text-red-600">{{ form.errors.scheduled_end_at }}</div>
                    </div>
                    <div class="space-y-1">
                        <label for="task-description" class="text-xs text-muted-foreground">Description</label>
                        <input id="task-description" v-model="form.description" type="text" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                </div>

                <button type="submit" class="rounded bg-primary px-3 py-2 text-sm text-primary-foreground" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create Task' }}
                </button>
            </form>

            <div class="rounded border">
                <div v-for="task in tasks.data" :key="task.id" class="border-b p-3 last:border-b-0">
                    <div class="font-medium">{{ task.title }}</div>
                    <div class="text-xs text-muted-foreground mt-1">
                        Status: {{ task.status }} |
                        Assigned: {{ task.assigned_user?.name || task.assigned_team?.name || 'Unassigned' }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
