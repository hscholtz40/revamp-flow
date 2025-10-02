<template>
    <Head title="Jobcards" />

    <AppLayout :breadcrumbs="[{ title: 'Jobcards', href: jobcards.index().url }]">
        <!-- Company Context -->
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing jobcards for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Jobcards</h1>
                <Link :href="jobcards.create().url" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    New Jobcard
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search jobcards..."
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="status" class="w-full rounded border px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <select v-model="customerId" class="w-full rounded border px-3 py-2">
                            <option value="">All Customers</option>
                            <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded border border-gray-300 px-3 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Jobcards Table -->
            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Job Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Title
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Due Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="jobcard in props.jobcards.data" :key="jobcard.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ jobcard.job_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ jobcard.title }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ jobcard.customer.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="getStatusBadgeClass(jobcard.status)"
                                        class="inline-flex px-2 py-1 text-xs font-semibold rounded-full"
                                    >
                                        {{ formatStatus(jobcard.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ jobcard.due_date ? formatDate(jobcard.due_date) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ jobcard.formatted_total || 'R0.00' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="jobcards.show(jobcard.id).url"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                        >
                                            View
                                        </Link>
                                        <Link
                                            v-if="canEditJobcard(jobcard)"
                                            :href="jobcards.edit(jobcard.id).url"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            Edit
                                        </Link>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-gray-500 bg-gray-100 cursor-not-allowed"
                                            title="Cannot edit completed jobcards without permission"
                                        >
                                            Edit
                                        </span>
                                        <button
                                            v-if="canDeleteJobcard(jobcard)"
                                            @click="deleteJobcard(jobcard)"
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                        >
                                            Delete
                                        </button>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-3 py-1 border border-transparent text-xs font-medium rounded-md text-gray-500 bg-gray-100 cursor-not-allowed"
                                            title="Cannot delete completed jobcards without permission"
                                        >
                                            Delete
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="props.jobcards.links" class="bg-white px-4 py-3 border-t border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ props.jobcards.from }} to {{ props.jobcards.to }} of {{ props.jobcards.total }} results
                        </div>
                        <div class="flex space-x-1">
                            <Link
                                v-for="link in props.jobcards.links"
                                :key="link.label"
                                :href="link.url || '#'"
                                v-html="link.label"
                                :class="[
                                    'px-3 py-2 text-sm border rounded',
                                    link.active
                                        ? 'bg-blue-50 border-blue-500 text-blue-600'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                                    !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                ]"
                            />
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
import { ref, watch, nextTick } from 'vue';
import jobcards from '@/routes/jobcards';

interface Jobcard {
    id: number;
    job_number: string;
    title: string;
    status: string;
    due_date: string | null;
    formatted_total: string | null;
    customer: {
        id: number;
        name: string;
    };
}

interface Customer {
    id: number;
    name: string;
}

interface Props {
    jobcards: {
        data: Jobcard[];
        links: any[];
        from: number;
        to: number;
        total: number;
    };
    customers: Customer[];
    filters: {
        status?: string;
        customer_id?: string;
        search?: string;
    };
    currentCompany: {
        id: number;
        name: string;
    };
    canEditCompleted: boolean;
}

const props = defineProps<Props>();

// Debug: Log the received props
console.log('Jobcards Index Props:', {
    jobcards: props.jobcards,
    customers: props.customers,
    filters: props.filters,
    currentCompany: props.currentCompany
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const customerId = ref(props.filters?.customer_id || '');

// Flag to prevent watch from running during initial setup
let isInitialized = false;

// Mark as initialized after component is mounted
nextTick(() => {
    isInitialized = true;
});

const clearFilters = () => {
    search.value = '';
    status.value = '';
    customerId.value = '';
    
    // Navigate to clean URL without filters
    router.get(jobcards.index().url, {}, {
        preserveState: true,
        replace: true,
    });
};

const canEditJobcard = (jobcard: Jobcard) => {
    if (jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
};

const canDeleteJobcard = (jobcard: Jobcard) => {
    if (jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
};

const deleteJobcard = (jobcard: Jobcard) => {
    if (!jobcard || !jobcard.id) {
        console.error('Invalid jobcard data:', jobcard);
        return;
    }
    
    const jobNumber = jobcard.job_number || `#${jobcard.id}`;
    if (confirm(`Are you sure you want to delete jobcard ${jobNumber}?`)) {
        router.delete(jobcards.destroy(jobcard.id).url, {
            onSuccess: () => {
                // Optional: Show success message
                console.log('Jobcard deleted successfully');
            },
            onError: (errors) => {
                console.error('Error deleting jobcard:', errors);
                alert('Failed to delete jobcard. Please try again.');
            }
        });
    }
};

const getStatusBadgeClass = (status: string) => {
    if (!status) return 'bg-gray-100 text-gray-800';
    const classes = {
        draft: 'bg-gray-100 text-gray-800',
        pending: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return classes[status as keyof typeof classes] || classes.draft;
};

const formatStatus = (status: string) => {
    if (!status) return '';
    return status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
};

// Watch for filter changes and update URL
watch([search, status, customerId], () => {
    // Skip if component is not fully initialized
    if (!isInitialized || !props.filters) return;
    
    const params: Record<string, string> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (status.value && status.value.trim()) params.status = status.value.trim();
    if (customerId.value && customerId.value.trim()) params.customer_id = customerId.value.trim();
    
    router.get(jobcards.index().url, params, {
        preserveState: true,
        replace: true,
    });
}, { immediate: false });
</script>
