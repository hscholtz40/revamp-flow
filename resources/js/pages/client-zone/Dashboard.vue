<script setup lang="ts">
import { useNumberFormat } from '@/composables/useNumberFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

interface SummaryBlock {
    total: number;
    statuses: Record<string, number>;
}

interface CompanyAccountBalance {
    company_id: number;
    company_name: string;
    outstanding_invoices: number;
    unapplied_credit: number;
    account_balance: number;
}

defineProps<{
    customer: {
        name: string;
        email: string;
    };
    accountBalances: CompanyAccountBalance[];
    summary: {
        jobcards: SummaryBlock;
        quotes: SummaryBlock;
        invoices: SummaryBlock;
        creditNotes: SummaryBlock;
    };
}>();

const { formatCurrency } = useNumberFormat();
</script>

<template>
    <Head title="Client Zone Dashboard" />
    <AppLayout :breadcrumbs="[{ title: 'Client Zone', href: '/client-zone' }]">
        <div class="space-y-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold">Client Zone Dashboard</h1>
                <p class="text-sm text-gray-600">{{ customer.name }} ({{ customer.email }})</p>
            </div>

            <div
                v-if="accountBalances.length > 0"
                class="rounded border border-gray-200 bg-white p-4 shadow-sm"
            >
                <h2 class="text-lg font-medium text-gray-900">Account balance</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Amounts from invoices and credit notes in this portal only — not synced with Xero.
                </p>
                <ul class="mt-4 divide-y divide-gray-100">
                    <li
                        v-for="row in accountBalances"
                        :key="row.company_id"
                        class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0"
                    >
                        <span class="font-medium text-gray-900">{{ row.company_name }}</span>
                        <span class="text-sm text-gray-700">
                            Net balance:
                            <span class="font-semibold tabular-nums">{{ formatCurrency(row.account_balance) }}</span>
                        </span>
                    </li>
                </ul>
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
