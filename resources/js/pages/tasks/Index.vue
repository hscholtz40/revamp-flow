<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

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
}>();
</script>

<template>
    <Head title="Tasks" />

    <AppLayout :breadcrumbs="[{ title: 'Tasks', href: '/tasks' }]">
        <div class="p-6 space-y-4">
            <h1 class="text-2xl font-semibold">Tasks Module</h1>
            <p class="text-sm text-muted-foreground">Tasks can be scheduled, assigned to users/teams, and updated from web or mobile.</p>

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
