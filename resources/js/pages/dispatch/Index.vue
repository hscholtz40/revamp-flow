<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    jobcards: Array<any>;
    tasks: Array<any>;
    routes: Array<any>;
}>();
</script>

<template>
    <Head title="Dispatch" />

    <AppLayout :breadcrumbs="[{ title: 'Dispatch', href: '/dispatch' }]">
        <div class="p-6 space-y-6">
            <div>
                <h1 class="text-2xl font-semibold">Dispatch & Scheduling</h1>
                <p class="text-sm text-muted-foreground">Calendar/drag-drop scheduler foundation with route generation support.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded border p-4">
                    <h2 class="font-medium mb-2">Scheduled Jobcards</h2>
                    <div class="text-3xl font-bold">{{ jobcards.length }}</div>
                </div>
                <div class="rounded border p-4">
                    <h2 class="font-medium mb-2">Scheduled Tasks</h2>
                    <div class="text-3xl font-bold">{{ tasks.length }}</div>
                </div>
                <div class="rounded border p-4">
                    <h2 class="font-medium mb-2">Saved Routes</h2>
                    <div class="text-3xl font-bold">{{ routes.length }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
