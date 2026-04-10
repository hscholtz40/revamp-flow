<template>
    <Head title="Jobcards" />

    <AppLayout :breadcrumbs="[{ title: 'Jobcards', href: jobcardsRoute.index().url }]">
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
                <Link
                    v-if="!isLimitedUser && canJobcardsCreate"
                    :href="jobcardsRoute.create().url"
                    class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    New Jobcard
                </Link>
            </div>

            <div class="mb-4 inline-flex rounded-md border border-gray-300 bg-white p-1">
                <button
                    class="rounded px-3 py-1.5 text-sm"
                    :class="activeTab === 'documents' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                    @click="activeTab = 'documents'"
                >
                    Documents
                </button>
                <button
                    class="rounded px-3 py-1.5 text-sm"
                    :class="activeTab === 'recurring' ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'"
                    @click="activeTab = 'recurring'"
                >
                    Recurring
                </button>
            </div>

            <div v-show="activeTab === 'documents'">

            <!-- Filters -->
            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-7 gap-4">
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
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
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
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned User</label>
                        <select v-model="assignedToUserId" class="w-full rounded border px-3 py-2">
                            <option value="">All Users</option>
                            <option v-for="user in props.users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assigned Team</label>
                        <select v-model="assignedToTeamId" class="w-full rounded border px-3 py-2">
                            <option value="">All Teams</option>
                            <option v-for="team in props.teams" :key="team.id" :value="team.id">
                                {{ team.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="showClosed"
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-700">Show closed</span>
                        </label>
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
                    <table data-list-view-table="true" class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('job_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Job Number
                                        <span>{{ sortIndicator('job_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('title')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Title
                                        <span>{{ sortIndicator('title') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Customer
                                        <span>{{ sortIndicator('customer_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Invoice
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Assigned To
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Status
                                        <span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('due_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Due Date
                                        <span>{{ sortIndicator('due_date') }}</span>
                                    </button>
                                </th>
                                <th v-if="!isLimitedUser" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Total
                                        <span>{{ sortIndicator('total') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Order Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Phone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Start Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Completed Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Tax</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="jobcard in props.jobcards.data"
                                :key="jobcard.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(jobcardsRoute.show(jobcard.id).url)"
                                @keydown.enter.prevent="router.visit(jobcardsRoute.show(jobcard.id).url)"
                                @keydown.space.prevent="router.visit(jobcardsRoute.show(jobcard.id).url)"
                            >
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
                                    <Link
                                        v-if="jobcard.invoice"
                                        :href="invoicesRoute.show(jobcard.invoice.id).url"
                                        @click.stop
                                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ jobcard.invoice.invoice_number }}
                                    </Link>
                                    <span v-else class="text-sm text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div v-if="jobcard.assigned_user" class="flex items-center gap-1.5">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-100 text-xs font-medium text-blue-700">
                                            {{ jobcard.assigned_user.name.charAt(0).toUpperCase() }}
                                        </span>
                                        <span class="text-sm text-gray-900">{{ jobcard.assigned_user.name }}</span>
                                    </div>
                                    <div v-else-if="jobcard.assigned_team" class="flex items-center gap-1.5">
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-purple-100 text-xs font-medium text-purple-700">
                                            {{ jobcard.assigned_team.name.charAt(0).toUpperCase() }}
                                        </span>
                                        <span class="text-sm text-gray-900">{{ jobcard.assigned_team.name }}</span>
                                        <span class="text-xs text-gray-400">(Team)</span>
                                    </div>
                                    <span v-else class="text-sm text-gray-400">Unassigned</span>
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
                                <td v-if="!isLimitedUser" class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ jobcard.formatted_total || 'R0.00' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.order_number || '-' }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900" style="display: none;">
                                    <div class="truncate max-w-xs">{{ jobcard.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.email || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.phone || '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.start_date ? formatDate(jobcard.start_date) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.completed_date ? formatDate(jobcard.completed_date) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    R{{ Number(jobcard.tax_amount || 0).toFixed(2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.created_at ? formatDate(jobcard.created_at) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" style="display: none;">
                                    {{ jobcard.updated_at ? formatDate(jobcard.updated_at) : '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <template v-if="!isLimitedUser">
                                            <Link
                                                v-if="canEditJobcard(jobcard)"
                                                :href="jobcardsRoute.edit(jobcard.id).url"
                                                class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                            >
                                                <ListTableActionLabel label="Edit">
                                                    <Edit class="h-4 w-4" />
                                                </ListTableActionLabel>
                                            </Link>
                                            <button
                                                v-if="canDeleteJobcard(jobcard)"
                                                @click="deleteJobcard(jobcard)"
                                                class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                            >
                                                <ListTableActionLabel label="Delete">
                                                    <Trash2 class="h-4 w-4" />
                                                </ListTableActionLabel>
                                            </button>
                                        </template>
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
                                :class="[
                                    'px-3 py-2 text-sm border rounded',
                                    link.active
                                        ? 'bg-blue-50 border-blue-500 text-blue-600'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50',
                                    !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                                ]"
                            >
                                <span v-html="link.label" />
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
            </div>

            <div v-show="activeTab === 'recurring'" class="space-y-4">
                <div class="bg-white rounded-lg border p-4">
                    <h2 class="mb-3 text-lg font-semibold text-gray-900">Add Recurring Jobcard</h2>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                        <select v-model="recurringForm.source_id" class="rounded border px-3 py-2">
                            <option value="">Select source jobcard</option>
                            <option v-for="source in props.recurringSourceOptions" :key="source.id" :value="String(source.id)">
                                {{ source.job_number }} - {{ source.title }}
                            </option>
                        </select>
                        <select v-model="recurringForm.frequency" class="rounded border px-3 py-2">
                            <option v-for="freq in props.recurringFrequencies" :key="freq" :value="freq">{{ freq }}</option>
                        </select>
                        <input v-model="recurringForm.start_date" type="date" class="rounded border px-3 py-2" />
                        <input v-model="recurringForm.end_date" type="date" class="rounded border px-3 py-2" />
                        <button class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700" @click="addRecurringJobcard">
                            Add recurring
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-lg border overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Frequency</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Start</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">End</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Next Run</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in props.recurringDocuments" :key="item.id" class="border-t">
                                <td class="px-4 py-2 text-sm text-gray-900">{{ item.source_label }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ item.frequency }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ formatDate(item.start_date) }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ item.end_date ? formatDate(item.end_date) : '-' }}</td>
                                <td class="px-4 py-2 text-sm text-gray-900">{{ formatDate(item.next_run_date) }}</td>
                                <td class="px-4 py-2 text-sm">
                                    <button class="rounded bg-red-100 px-2 py-1 text-red-700 hover:bg-red-200" @click="deleteRecurringJobcard(item.id)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref, computed, watch, nextTick } from 'vue';
import jobcardsRoute from '@/routes/jobcards';
import invoicesRoute from '@/routes/invoices';

const page = usePage();
const isLimitedUser = computed(() => (page.props.auth as any)?.user?.user_type === 'limited');
const canJobcardsCreate = useAuthAbility('jobcards', 'create');
const canJobcardsEdit = useAuthAbility('jobcards', 'edit');
const canJobcardsDelete = useAuthAbility('jobcards', 'delete');

interface AppUser {
    id: number;
    name: string;
}

interface TeamOption {
    id: number;
    name: string;
}

interface Jobcard {
    id: number;
    job_number: string;
    title: string;
    status: string;
    description?: string | null;
    order_number?: string | null;
    email?: string | null;
    phone?: string | null;
    start_date?: string | null;
    completed_date?: string | null;
    tax_amount?: number | null;
    created_at?: string;
    updated_at?: string;
    due_date: string | null;
    formatted_total: string | null;
    customer: {
        id: number;
        name: string;
    };
    invoice: { id: number; invoice_number: string } | null;
    assigned_user: AppUser | null;
    assigned_team: TeamOption | null;
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
    users: AppUser[];
    teams: TeamOption[];
    filters: {
        status?: string;
        customer_id?: string;
        assigned_to_user_id?: string;
        assigned_to_team_id?: string;
        search?: string;
        show_closed?: boolean;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
    currentCompany: {
        id: number;
        name: string;
    };
    canEditCompleted: boolean;
    statusOptions?: Array<{ value: string; label: string }>;
    recurringFrequencies: string[];
    recurringSourceOptions: Array<{ id: number; job_number: string; title: string }>;
    recurringDocuments: Array<{
        id: number;
        source_id: number;
        frequency: string;
        start_date: string;
        end_date: string | null;
        next_run_date: string;
        source_label?: string;
    }>;
}

const props = defineProps<Props>();

const statusOptions = computed(() => props.statusOptions ?? []);

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');
const customerId = ref(props.filters?.customer_id || '');
const assignedToUserId = ref(props.filters?.assigned_to_user_id || '');
const assignedToTeamId = ref(props.filters?.assigned_to_team_id || '');
const showClosed = ref(props.filters?.show_closed ?? false);
const sortBy = ref(props.filters?.sort_by || 'job_number');
const sortDir = ref<'asc' | 'desc'>(props.filters?.sort_dir || 'desc');
const activeTab = ref<'documents' | 'recurring'>('documents');
const recurringForm = ref({
    source_id: '',
    frequency: props.recurringFrequencies[0] || 'monthly',
    start_date: new Date().toISOString().slice(0, 10),
    end_date: '',
});

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
    assignedToUserId.value = '';
    assignedToTeamId.value = '';
    showClosed.value = false;
    
    // Navigate to clean URL without filters
    router.get(jobcardsRoute.index().url, {}, {
        preserveState: true,
        replace: true,
    });
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }

    const params: Record<string, string> = {};
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (status.value && status.value.trim()) params.status = status.value.trim();
    if (customerId.value && customerId.value.trim()) params.customer_id = customerId.value.trim();
    if (assignedToUserId.value && assignedToUserId.value.trim()) params.assigned_to_user_id = assignedToUserId.value.trim();
    if (assignedToTeamId.value && assignedToTeamId.value.trim()) params.assigned_to_team_id = assignedToTeamId.value.trim();
    if (showClosed.value) params.show_closed = '1';
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;

    router.get(jobcardsRoute.index().url, params, {
        preserveState: true,
        replace: true,
    });
};

const sortIndicator = (field: string) => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const canEditJobcard = (jobcard: Jobcard) => {
    if (!canJobcardsEdit.value) {
        return false;
    }
    if (jobcard.status !== 'completed') {
        return true;
    }
    return props.canEditCompleted;
};

const canDeleteJobcard = (jobcard: Jobcard) => {
    if (!canJobcardsDelete.value) {
        return false;
    }
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
        router.delete(jobcardsRoute.destroy(jobcard.id).url, {
            onSuccess: () => {
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

const formatStatus = (code: string) => {
    if (!code) return '';
    const opt = statusOptions.value.find((o) => o.value === code);
    if (opt) return opt.label;
    return code.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
};

const formatDate = (date: string) => {
    if (!date) return '';
    return new Date(date).toLocaleDateString();
};

const addRecurringJobcard = () => {
    if (!recurringForm.value.source_id) {
        alert('Please select a source jobcard.');
        return;
    }
    router.post('/jobcards/recurring', recurringForm.value, {
        preserveState: true,
    });
};

const deleteRecurringJobcard = (id: number) => {
    if (!confirm('Delete this recurring jobcard?')) return;
    router.delete(`/jobcards/recurring/${id}`, { preserveState: true });
};

// Watch for filter changes and update URL
watch([search, status, customerId, assignedToUserId, assignedToTeamId, showClosed], () => {
    // Skip if component is not fully initialized
    if (!isInitialized || !props.filters) return;
    
    const params: Record<string, string> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (status.value && status.value.trim()) params.status = status.value.trim();
    if (customerId.value && customerId.value.trim()) params.customer_id = customerId.value.trim();
    if (assignedToUserId.value && assignedToUserId.value.trim()) params.assigned_to_user_id = assignedToUserId.value.trim();
    if (assignedToTeamId.value && assignedToTeamId.value.trim()) params.assigned_to_team_id = assignedToTeamId.value.trim();
    if (showClosed.value) params.show_closed = '1';
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    
    router.get(jobcardsRoute.index().url, params, {
        preserveState: true,
        replace: true,
    });
}, { immediate: false });
</script>
