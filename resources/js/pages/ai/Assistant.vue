<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { getCsrfToken } from '@/lib/csrf';

const page = usePage();
const aiAllowed = computed(() => !!(page.props as { ai?: { allowed?: boolean } }).ai?.allowed);
const query = ref('');
const loading = ref(false);
const summary = ref('');
const results = ref<Record<string, Array<Record<string, unknown>>>>({});
const groupLabel: Record<string, string> = {
    jobcards: 'Jobcards',
    quotes: 'Quotes',
    invoices: 'Invoices',
    tasks: 'Tasks',
    customers: 'Customers',
    contacts: 'Contacts',
};

const recordHref = (group: string, row: Record<string, unknown>) => {
    const id = Number(row.id ?? 0);
    if (!id) return '#';
    if (group === 'jobcards') return `/jobcards/${id}`;
    if (group === 'quotes') return `/quotes/${id}`;
    if (group === 'invoices') return `/invoices/${id}`;
    if (group === 'tasks') return `/tasks/${id}/edit`;
    if (group === 'customers') return `/customers/${id}`;
    if (group === 'contacts') return `/contacts/${id}`;
    return '#';
};

const recordTitle = (group: string, row: Record<string, unknown>) => {
    if (group === 'jobcards') return String(row.job_number || row.title || `Jobcard #${row.id}`);
    if (group === 'quotes') return String(row.quote_number || `Quote #${row.id}`);
    if (group === 'invoices') return String(row.invoice_number || `Invoice #${row.id}`);
    if (group === 'tasks') return String(row.title || `Task #${row.id}`);
    if (group === 'customers' || group === 'contacts') return String(row.name || `Record #${row.id}`);
    return `Record #${row.id ?? '?'}`;
};

const recordMeta = (group: string, row: Record<string, unknown>) => {
    if (group === 'jobcards' || group === 'quotes' || group === 'invoices' || group === 'tasks') {
        return String(row.status || 'No status');
    }
    if (group === 'customers' || group === 'contacts') {
        return String(row.email || 'No email');
    }
    return '';
};

const search = async () => {
    if (!query.value.trim() || !aiAllowed.value) return;
    loading.value = true;
    const response = await fetch('/ai/assistant', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ query: query.value.trim() }),
    });
    loading.value = false;
    if (!response.ok) return;
    const data = (await response.json()) as { summary?: string; results?: Record<string, Array<Record<string, unknown>>> };
    summary.value = data.summary || '';
    results.value = data.results || {};
};
</script>

<template>
    <Head title="AI Assistant" />
    <AppLayout :breadcrumbs="[{ title: 'AI Assistant', href: '/ai-assistant' }]">
        <div class="space-y-4 p-4">
            <h1 class="text-2xl font-bold text-gray-900">AI Assistant</h1>
            <p class="text-sm text-gray-600">Read-only assistant over company data (jobcards, quotes, invoices, tasks, customers, contacts).</p>

            <div v-if="!aiAllowed" class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                AI access is currently unavailable for this user or instance.
            </div>

            <div v-else class="rounded-lg border bg-white p-4">
                <div class="flex gap-2">
                    <input v-model="query" type="text" class="flex-1 rounded border px-3 py-2 text-sm" placeholder="Ask about records, references, statuses, customers..." />
                    <button type="button" class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60" :disabled="loading || !query.trim()" @click="search">
                        {{ loading ? 'Searching…' : 'Search' }}
                    </button>
                </div>
                <p v-if="summary" class="mt-3 text-sm text-gray-700">{{ summary }}</p>

                <div v-if="Object.keys(results).length" class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="(rows, group) in results" :key="group" class="rounded border border-gray-200 p-3">
                        <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ groupLabel[group] || group }}</h2>
                        <ul class="space-y-2 text-xs text-gray-700">
                            <li v-for="(row, idx) in rows" :key="`${group}-${idx}`" class="rounded border border-gray-200 bg-gray-50 px-2 py-2">
                                <a
                                    :href="recordHref(group, row)"
                                    class="font-semibold text-indigo-700 hover:underline"
                                >
                                    {{ recordTitle(group, row) }}
                                </a>
                                <p class="mt-0.5 text-gray-600">{{ recordMeta(group, row) }}</p>
                            </li>
                            <li v-if="rows.length === 0" class="text-gray-500">No matches</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
