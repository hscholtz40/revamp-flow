<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
}

interface ClientUserRow {
    id: number;
    name: string;
    email: string;
    created_at: string;
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
    filters: { search?: string; sort_by?: string; sort_dir?: 'asc' | 'desc' };
}>();

const { formatDateTime } = useDateTimeFormat();
const search = ref(props.filters?.search ?? '');
const sortBy = ref(props.filters?.sort_by ?? 'created_at');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir ?? 'desc');

const listUrl = '/registered-users/pending';

watch(search, (value) => {
    router.get(
        listUrl,
        {
            search: value.trim() || undefined,
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
        sortDir.value = field === 'name' || field === 'email' ? 'asc' : 'desc';
    }
    router.get(
        listUrl,
        {
            search: search.value.trim() || undefined,
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
</script>

<template>
    <Head title="Users awaiting approval" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Registered Users', href: '/registered-users' },
            { title: 'Awaiting approval', href: listUrl },
        ]"
    >
        <div class="p-4">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Users awaiting approval</h1>
                    <p class="text-sm text-gray-600">Pending client zone registrations</p>
                </div>
                <Link
                    href="/registered-users"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Overview
                </Link>
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
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                    <button type="button" class="inline-flex items-center gap-1 hover:text-gray-700" @click="toggleSort('created_at')">
                                        Requested {{ sortIndicator('created_at') }}
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
                                @click="router.visit(`/registered-users/pending/${u.id}`)"
                                @keydown.enter.prevent="router.visit(`/registered-users/pending/${u.id}`)"
                            >
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ u.name }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">{{ u.email }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ u.customer?.name ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-700">
                                    {{ formatDateTime(u.created_at) }}
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
