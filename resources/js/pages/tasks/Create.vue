<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{
    assignableUsers: Array<{ id: number; name: string }>;
    assignableTeams: Array<{ id: number; name: string }>;
    jobcards: Array<{ id: number; job_number: string; title: string }>;
    statusOptions: Array<{ value: string; label: string }>;
}>();

const form = useForm({
    title: '',
    description: '',
    status: 'new',
    assigned_to_user_id: '',
    assigned_to_team_id: '',
    jobcard_id: '',
    scheduled_start_at: '',
    scheduled_end_at: '',
});

const assignmentType = ref<'user' | 'team'>('user');
watch(assignmentType, (type) => {
    if (type === 'user') {
        form.assigned_to_team_id = '';
    } else {
        form.assigned_to_user_id = '';
    }
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        assigned_to_user_id: data.assigned_to_user_id ? Number(data.assigned_to_user_id) : null,
        assigned_to_team_id: data.assigned_to_team_id ? Number(data.assigned_to_team_id) : null,
        jobcard_id: data.jobcard_id ? Number(data.jobcard_id) : null,
        scheduled_start_at: data.scheduled_start_at || null,
        scheduled_end_at: data.scheduled_end_at || null,
    })).post('/tasks');
};
</script>

<template>
    <Head title="Create Task" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: '/tasks' }, { title: 'Create', href: '/tasks/create' }]">
        <div class="p-4">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Create Task</h1>
                <p class="mt-1 text-sm text-gray-600">Create a scheduled task and assign it to either a user or a team.</p>
            </div>

            <form class="space-y-5 rounded-lg border bg-white p-5" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Title</label>
                        <input v-model="form.title" type="text" class="w-full rounded border px-3 py-2" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded border px-3 py-2" />
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select v-model="form.status" class="w-full rounded border px-3 py-2">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Related Jobcard</label>
                        <select v-model="form.jobcard_id" class="w-full rounded border px-3 py-2">
                            <option value="">None</option>
                            <option v-for="jobcard in props.jobcards" :key="jobcard.id" :value="String(jobcard.id)">
                                {{ jobcard.job_number }} - {{ jobcard.title }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Assign To</label>
                        <div class="mb-2 flex gap-2">
                            <button
                                type="button"
                                @click="assignmentType = 'user'"
                                :class="assignmentType === 'user' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="rounded px-3 py-1 text-sm font-medium transition-colors"
                            >
                                User
                            </button>
                            <button
                                type="button"
                                @click="assignmentType = 'team'"
                                :class="assignmentType === 'team' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                class="rounded px-3 py-1 text-sm font-medium transition-colors"
                            >
                                Team
                            </button>
                        </div>
                        <select v-if="assignmentType === 'user'" v-model="form.assigned_to_user_id" class="w-full rounded border px-3 py-2">
                            <option value="">Select a user...</option>
                            <option v-for="user in props.assignableUsers" :key="user.id" :value="String(user.id)">
                                {{ user.name }}
                            </option>
                        </select>
                        <select v-else v-model="form.assigned_to_team_id" class="w-full rounded border px-3 py-2">
                            <option value="">Select a team...</option>
                            <option v-for="team in props.assignableTeams" :key="team.id" :value="String(team.id)">
                                {{ team.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.assigned_to_user_id" class="mt-1 text-xs text-red-600">{{ form.errors.assigned_to_user_id }}</p>
                        <p v-if="form.errors.assigned_to_team_id" class="mt-1 text-xs text-red-600">{{ form.errors.assigned_to_team_id }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Scheduled Start</label>
                        <input v-model="form.scheduled_start_at" type="datetime-local" class="w-full rounded border px-3 py-2" />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Scheduled End</label>
                        <input v-model="form.scheduled_end_at" type="datetime-local" class="w-full rounded border px-3 py-2" />
                        <p v-if="form.errors.scheduled_end_at" class="mt-1 text-xs text-red-600">{{ form.errors.scheduled_end_at }}</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ form.processing ? 'Creating...' : 'Create Task' }}
                    </button>
                    <Link href="/tasks" class="rounded border px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
