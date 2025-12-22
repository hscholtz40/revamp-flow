<template>
    <Head title="Timesheet" />

    <AppLayout :breadcrumbs="[{ title: 'Timesheet', href: '#' }]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Timesheet</h1>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-sm text-gray-600">Total Hours</p>
                    <p class="text-2xl font-bold text-gray-900">{{ Number(summary.total_hours || 0).toFixed(2) }}h</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-sm text-gray-600">Billable Hours</p>
                    <p class="text-2xl font-bold text-green-600">{{ Number(summary.billable_hours || 0).toFixed(2) }}h</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <p class="text-sm text-gray-600">Total Amount</p>
                    <p class="text-2xl font-bold text-blue-600">R{{ Number(summary.total_amount || 0).toFixed(2) }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jobcard</label>
                        <select v-model="filters.jobcard_id" class="w-full rounded border px-3 py-2">
                            <option value="">All Jobcards</option>
                            <option v-for="jobcard in jobcards" :key="jobcard.id" :value="jobcard.id">
                                {{ jobcard.job_number }} - {{ jobcard.title }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <select v-model="filters.user_id" class="w-full rounded border px-3 py-2">
                            <option value="">All Users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input
                            v-model="filters.start_date"
                            type="date"
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input
                            v-model="filters.end_date"
                            type="date"
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Time Entries Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Jobcard</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="entry in timeEntries.data" :key="entry.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ formatDate(entry.date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <Link :href="`/jobcards/${entry.jobcard.id}`" class="text-blue-600 hover:text-blue-800">
                                        {{ entry.jobcard.job_number }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ entry.user?.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ entry.formatted_duration }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ entry.hourly_rate ? `R${Number(entry.hourly_rate).toFixed(2)}` : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span v-if="entry.is_billable" class="text-green-600 font-medium">
                                        {{ entry.formatted_total_amount }}
                                    </span>
                                    <span v-else class="text-gray-500">Non-billable</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="{
                                            'bg-green-100 text-green-800': entry.status === 'completed',
                                            'bg-blue-100 text-blue-800': entry.status === 'running',
                                            'bg-yellow-100 text-yellow-800': entry.status === 'paused',
                                        }"
                                        class="px-2 py-1 text-xs font-medium rounded-full"
                                    >
                                        {{ entry.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button
                                        @click="deleteEntry(entry.id)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="timeEntries.data.length === 0">
                                <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No time entries found
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="timeEntries.links" class="bg-white px-4 py-3 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ timeEntries.from }} to {{ timeEntries.to }} of {{ timeEntries.total }} entries
                        </div>
                        <div class="flex gap-2">
                            <Link
                                v-for="link in timeEntries.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                :class="{
                                    'bg-blue-600 text-white': link.active,
                                    'bg-white text-gray-700 hover:bg-gray-50': !link.active,
                                    'pointer-events-none opacity-50': !link.url,
                                }"
                                class="px-3 py-2 rounded border text-sm"
                                v-html="link.label"
                            ></Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Props {
    timeEntries: {
        data: any[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    summary: {
        total_hours: number;
        billable_hours: number;
        total_amount: number;
    };
    filters: {
        jobcard_id?: number;
        user_id?: number;
        start_date?: string;
        end_date?: string;
        is_billable?: boolean;
    };
    jobcards?: any[];
    users?: any[];
}

const props = defineProps<Props>();

const filters = ref({
    jobcard_id: props.filters.jobcard_id || '',
    user_id: props.filters.user_id || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    is_billable: props.filters.is_billable,
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const clearFilters = () => {
    filters.value = {
        jobcard_id: '',
        user_id: '',
        start_date: '',
        end_date: '',
        is_billable: undefined,
    };
};

const deleteEntry = (id: number) => {
    if (confirm('Are you sure you want to delete this time entry?')) {
        router.delete(`/time-entries/${id}`, {
            preserveScroll: true,
        });
    }
};

watch(filters, () => {
    router.get('/time-entries', filters.value, {
        preserveScroll: true,
        replace: true,
    });
}, { deep: true });
</script>

