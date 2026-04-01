<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface SummaryBlock {
    total: number;
    statuses: Record<string, number>;
}

defineProps<{
    customer: {
        name: string;
        email: string;
    };
    summary: {
        jobcards: SummaryBlock;
        quotes: SummaryBlock;
        invoices: SummaryBlock;
        creditNotes: SummaryBlock;
    };
}>();
</script>

<template>
    <Head title="Client Zone Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Client Zone', href: '/client-zone' }]">
        <div class="space-y-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Client Zone Dashboard</h1>
                <p class="text-sm text-gray-600">{{ customer.name }} ({{ customer.email }})</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded border p-4">
                    <h2 class="text-lg font-medium">Jobcards</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ summary.jobcards.total }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div v-for="(count, status) in summary.jobcards.statuses" :key="`jc-${status}`">{{ status }}: {{ count }}</div>
                    </div>
                </div>
                <div class="rounded border p-4">
                    <h2 class="text-lg font-medium">Quotes</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ summary.quotes.total }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div v-for="(count, status) in summary.quotes.statuses" :key="`qt-${status}`">{{ status }}: {{ count }}</div>
                    </div>
                </div>
                <div class="rounded border p-4">
                    <h2 class="text-lg font-medium">Invoices</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ summary.invoices.total }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div v-for="(count, status) in summary.invoices.statuses" :key="`inv-${status}`">{{ status }}: {{ count }}</div>
                    </div>
                </div>
                <div class="rounded border p-4">
                    <h2 class="text-lg font-medium">Credit Notes</h2>
                    <p class="mt-1 text-2xl font-semibold">{{ summary.creditNotes.total }}</p>
                    <div class="mt-3 space-y-1 text-sm">
                        <div v-for="(count, status) in summary.creditNotes.statuses" :key="`cn-${status}`">{{ status }}: {{ count }}</div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
