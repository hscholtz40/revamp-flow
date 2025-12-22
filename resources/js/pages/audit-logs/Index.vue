<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Calendar, Download, Eye, Search } from 'lucide-vue-next';

interface AuditLog {
    id: number;
    event: string;
    description: string;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    auditable_type: string;
    auditable_id: number;
    created_at: string;
    ip_address: string | null;
    url: string | null;
}

const props = defineProps<{
    auditLogs: {
        data: AuditLog[];
        links: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    users: Array<{
        id: number;
        name: string;
        email: string;
    }>;
    modelTypes: Array<{
        value: string;
        label: string;
    }>;
    filters: {
        event: string;
        user_id: string;
        model_type: string;
        model_id: string;
        date_from: string;
        date_to: string;
        search: string;
    };
}>();

const eventFilter = ref(props.filters.event);
const userFilter = ref(props.filters.user_id);
const modelTypeFilter = ref(props.filters.model_type);
const searchFilter = ref(props.filters.search);
const dateFromFilter = ref(props.filters.date_from);
const dateToFilter = ref(props.filters.date_to);

const eventTypes = [
    { value: '', label: 'All Events' },
    { value: 'created', label: 'Created' },
    { value: 'updated', label: 'Updated' },
    { value: 'deleted', label: 'Deleted' },
    { value: 'restored', label: 'Restored' },
];

function applyFilters() {
    router.get(
        '/audit-logs',
        {
            event: eventFilter.value,
            user_id: userFilter.value,
            model_type: modelTypeFilter.value,
            search: searchFilter.value,
            date_from: dateFromFilter.value,
            date_to: dateToFilter.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        }
    );
}

function exportLogs() {
    const params = new URLSearchParams({
        event: eventFilter.value || '',
        user_id: userFilter.value || '',
        model_type: modelTypeFilter.value || '',
        search: searchFilter.value || '',
        date_from: dateFromFilter.value || '',
        date_to: dateToFilter.value || '',
    });
    window.location.href = `/audit-logs/export?${params.toString()}`;
}

function getEventBadgeVariant(event: string): string {
    const variants: Record<string, string> = {
        created: 'default',
        updated: 'secondary',
        deleted: 'destructive',
        restored: 'outline',
    };
    return variants[event] || 'default';
}

function formatModelType(type: string): string {
    return type.split('\\').pop() || type;
}
</script>

<template>
    <Head title="Audit Logs" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Audit Logs</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Track all changes and activities in the system
                    </p>
                </div>
                <Button @click="exportLogs" variant="outline">
                    <Download class="mr-2 h-4 w-4" />
                    Export CSV
                </Button>
            </div>

            <!-- Filters -->
            <div class="rounded-lg border bg-white p-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-6">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Event Type</label>
                        <select
                            v-model="eventFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option
                                v-for="event in eventTypes"
                                :key="event.value"
                                :value="event.value"
                            >
                                {{ event.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">User</label>
                        <select
                            v-model="userFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">All Users</option>
                            <option
                                v-for="user in users"
                                :key="user.id"
                                :value="user.id.toString()"
                            >
                                {{ user.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Model Type</label>
                        <select
                            v-model="modelTypeFilter"
                            @change="applyFilters"
                            class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">All Models</option>
                            <option
                                v-for="modelType in modelTypes"
                                :key="modelType.value"
                                :value="modelType.value"
                            >
                                {{ modelType.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Date From</label>
                        <Input
                            v-model="dateFromFilter"
                            type="date"
                            @change="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Date To</label>
                        <Input
                            v-model="dateToFilter"
                            type="date"
                            @change="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Search</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                v-model="searchFilter"
                                placeholder="Search..."
                                class="pl-9"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Logs Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Date & Time
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                User
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Event
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Model
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Description
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                IP Address
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-if="auditLogs.data.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No audit logs found
                            </td>
                        </tr>
                        <tr
                            v-for="log in auditLogs.data"
                            :key="log.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <span class="text-sm text-gray-900">
                                        {{ new Date(log.created_at).toLocaleString() }}
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div v-if="log.user">
                                    <div class="text-sm font-medium text-gray-900">{{ log.user.name }}</div>
                                    <div class="text-sm text-gray-500">
                                        {{ log.user.email }}
                                    </div>
                                </div>
                                <span v-else class="text-sm text-gray-500">System</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <Badge :variant="getEventBadgeVariant(log.event)">
                                    {{ log.event }}
                                </Badge>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900">
                                        {{ formatModelType(log.auditable_type) }}
                                    </div>
                                    <div class="text-gray-500">#{{ log.auditable_id }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="max-w-md truncate text-sm text-gray-900">{{ log.description }}</div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span class="text-sm text-gray-500">
                                    {{ log.ip_address || 'N/A' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm">
                                <Link
                                    :href="`/audit-logs/${log.id}`"
                                    class="text-blue-600 hover:text-blue-900 hover:underline"
                                >
                                    <Eye class="h-4 w-4" />
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="auditLogs.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing {{ (auditLogs.current_page - 1) * auditLogs.per_page + 1 }} to
                    {{ Math.min(auditLogs.current_page * auditLogs.per_page, auditLogs.total) }}
                    of {{ auditLogs.total }} results
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="auditLogs.current_page > 1"
                        variant="outline"
                        size="sm"
                        @click="router.get(auditLogs.links[0].url)"
                    >
                        Previous
                    </Button>
                    <Button
                        v-if="auditLogs.current_page < auditLogs.last_page"
                        variant="outline"
                        size="sm"
                        @click="router.get(auditLogs.links[auditLogs.links.length - 1].url)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

