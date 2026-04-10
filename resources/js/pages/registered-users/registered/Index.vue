<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
}

interface ClientUserRow {
    id: number;
    name: string;
    email: string;
    approval_status: string;
    approved_at: string | null;
    created_at: string;
    updated_at: string;
    customer?: Customer | null;
}

const props = defineProps<{
    users: {
        data: ClientUserRow[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search?: string;
        account_filter?: 'active' | 'deactivated';
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
}>();

const { formatDateTime } = useDateTimeFormat();
const search = ref(props.filters?.search ?? '');
const accountFilter = ref<'active' | 'deactivated'>(props.filters?.account_filter ?? 'active');
const sortBy = ref(props.filters?.sort_by ?? (accountFilter.value === 'deactivated' ? 'updated_at' : 'approved_at'));
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir ?? 'desc');

const listUrl = '/registered-users/registered';

const isDeactivatedTab = computed(() => accountFilter.value === 'deactivated');

const listParams = () => ({
    search: search.value.trim() || undefined,
    account_filter: accountFilter.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
});

watch(search, () => {
    router.get(listUrl, listParams(), { preserveState: true, replace: true });
});

watch(accountFilter, (tab) => {
    if (tab === 'deactivated' && sortBy.value === 'approved_at') {
        sortBy.value = 'updated_at';
    }
    if (tab === 'active' && sortBy.value === 'updated_at') {
        sortBy.value = 'approved_at';
    }
    router.get(listUrl, listParams(), { preserveState: true, replace: true });
});

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = field === 'name' || field === 'email' ? 'asc' : 'desc';
    }
    router.get(listUrl, listParams(), { preserveState: true, replace: true });
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};
</script>

<template>
    <Head title="Registered users" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Registered Users', href: '/registered-users' },
            { title: 'Registered users', href: listUrl },
        ]"
    >
        <div class="p-4">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Registered users</h1>
                    <p class="text-sm text-gray-600">Client zone accounts you can activate or deactivate</p>
                </div>
                <Link
                    href="/registered-users"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Overview
                </Link>
            </div>

            <div class="mb-4 flex flex-wrap gap-2 border-b border-gray-200 pb-3">
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        accountFilter === 'active'
                            ? 'bg-blue-600 text-white'
                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                    "
                    @click="accountFilter = 'active'"
                >
                    Active
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        accountFilter === 'deactivated'
                            ? 'bg-gray-700 text-white'
                            : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                    "
                    @click="accountFilter = 'deactivated'"
                >
                    Deactivated
                </button>
            </div>

            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Name, email, or customer…"
                        class="min-w-[200px] flex-1 rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    />
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        @click="search = ''"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('name')">
                                        Name {{ sortIndicator('name') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('email')">
                                        Email {{ sortIndicator('email') }}
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                                <th
                                    v-if="!isDeactivatedTab"
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('approved_at')">
                                        Approved {{ sortIndicator('approved_at') }}
                                    </button>
                                </th>
                                <th
                                    v-else
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                >
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('updated_at')">
                                        Last updated {{ sortIndicator('updated_at') }}
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr
                                v-for="u in props.users.data"
                                :key="u.id"
                                class="cursor-pointer hover:bg-gray-50"
                                role="link"
                                tabindex="0"
                                @click="router.visit(`/registered-users/registered/${u.id}`)"
                                @keydown.enter.prevent="router.visit(`/registered-users/registered/${u.id}`)"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                    <span class="inline-flex items-center gap-2">
                                        {{ u.name }}
                                        <span
                                            v-if="u.approval_status === 'deactivated'"
                                            class="rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-800"
                                        >
                                            Deactivated
                                        </span>
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ u.email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ u.customer?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    <template v-if="!isDeactivatedTab">
                                        {{ u.approved_at ? formatDateTime(u.approved_at) : '—' }}
                                    </template>
                                    <template v-else>
                                        {{ u.updated_at ? formatDateTime(u.updated_at) : '—' }}
                                    </template>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.users.links?.length" class="border-t border-gray-200 bg-white px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-sm text-gray-700">
                            Showing {{ props.users.from ?? 0 }} to {{ props.users.to ?? 0 }} of {{ props.users.total }} results
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <Link
                                v-for="link in props.users.links"
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
