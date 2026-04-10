<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
}

interface RequestUser {
    id: number;
    name: string;
    email: string;
}

interface UpdateRequestRow {
    id: number;
    status: string;
    created_at: string;
    customer?: Customer | null;
    user?: RequestUser | null;
}

const props = defineProps<{
    updateRequests: {
        data: UpdateRequestRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search?: string; status?: string; sort_by?: string; sort_dir?: 'asc' | 'desc' };
}>();

const { formatDateTime } = useDateTimeFormat();
const search = ref(props.filters?.search ?? '');
const status = ref(props.filters?.status ?? '');
const sortBy = ref(props.filters?.sort_by ?? 'created_at');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir ?? 'desc');

const listUrl = '/registered-users/update-requests';

const statusLabel = (s: string) => {
    if (s === 'pending') return 'Pending';
    if (s === 'approved') return 'Approved';
    if (s === 'rejected') return 'Rejected';
    return s;
};

const statusClass = (s: string) => {
    if (s === 'pending') return 'bg-amber-100 text-amber-900';
    if (s === 'approved') return 'bg-green-100 text-green-800';
    if (s === 'rejected') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
};

watch([search, status], () => {
    router.get(
        listUrl,
        {
            search: search.value.trim() || undefined,
            status: status.value || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        },
        { preserveState: true, replace: true },
    );
});

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'desc';
    }
    router.get(
        listUrl,
        {
            search: search.value.trim() || undefined,
            status: status.value || undefined,
            sort_by: sortBy.value,
            sort_dir: sortDir.value,
        },
        { preserveState: true, replace: true },
    );
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const clearFilters = () => {
    search.value = '';
    status.value = '';
};
</script>

<template>
    <Head title="Information update requests" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Registered Users', href: '/registered-users' },
            { title: 'Update requests', href: listUrl },
        ]"
    >
        <div class="p-4">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Information update requests</h1>
                    <p class="text-sm text-gray-600">Customer profile changes submitted from Client Zone</p>
                </div>
                <Link
                    href="/registered-users"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Overview
                </Link>
            </div>

            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Customer or requester…"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="status"
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option value="">All</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="clearFilters"
                        >
                            Clear filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Requested by</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('status')">
                                        Status {{ sortIndicator('status') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('created_at')">
                                        Submitted {{ sortIndicator('created_at') }}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="r in props.updateRequests.data"
                                :key="r.id"
                                class="cursor-pointer hover:bg-gray-50"
                                role="link"
                                tabindex="0"
                                @click="router.visit(`/registered-users/update-requests/${r.id}`)"
                                @keydown.enter.prevent="router.visit(`/registered-users/update-requests/${r.id}`)"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    {{ r.customer?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    <span v-if="r.user">{{ r.user.name }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="statusClass(r.status)"
                                    >
                                        {{ statusLabel(r.status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ formatDateTime(r.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.updateRequests.links?.length" class="border-t border-gray-200 bg-white px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm text-gray-700">
                            Showing {{ props.updateRequests.from ?? 0 }} to {{ props.updateRequests.to ?? 0 }} of
                            {{ props.updateRequests.total }} results
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Link
                                v-for="link in props.updateRequests.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                class="rounded-md border px-3 py-2 text-sm"
                                :class="[
                                    link.active ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                                    !link.url ? 'cursor-not-allowed opacity-50' : '',
                                ]"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
