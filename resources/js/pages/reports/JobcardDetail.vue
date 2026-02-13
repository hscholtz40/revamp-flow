<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import {
    Download, Printer, Filter, X, ChevronDown, ChevronRight,
    Clock, DollarSign, ClipboardList, Users, FileText,
} from 'lucide-vue-next';

interface Customer { id: number; name: string; }
interface AppUser { id: number; name: string; }
interface TeamOption { id: number; name: string; }

interface LineItem {
    id: number;
    description: string | null;
    product_name: string | null;
    quantity: number;
    unit_price: number;
    discount_amount: number;
    discount_percentage: number;
    total: number;
}

interface TimeEntry {
    id: number;
    user_name: string;
    date: string | null;
    description: string | null;
    duration_minutes: number;
    formatted_duration: string;
    hourly_rate: number;
    is_billable: boolean;
    total_amount: number;
    status: string;
}

interface StatusDuration {
    minutes: number;
    formatted: string;
}

interface StatusTransition {
    from_status: string | null;
    to_status: string;
    transitioned_at: string;
    user_name: string;
}

interface JobcardRow {
    id: number;
    job_number: string;
    title: string;
    description: string | null;
    status: string;
    status_color: string;
    start_date: string | null;
    due_date: string | null;
    completed_date: string | null;
    created_at: string | null;
    customer: { id: number; name: string } | null;
    assigned_user: { id: number; name: string } | null;
    assigned_team: { id: number; name: string } | null;
    invoice: { id: number; invoice_number: string } | null;
    subtotal: number;
    discount_amount: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    formatted_total: string;
    line_items_count: number;
    line_items: LineItem[];
    time_entries_count: number;
    total_minutes: number;
    total_hours: number;
    formatted_total_time: string;
    billable_minutes: number;
    billable_hours: number;
    formatted_billable_time: string;
    billable_amount: number;
    time_entries: TimeEntry[];
    status_durations: Record<string, StatusDuration> | null;
    status_transitions: StatusTransition[] | null;
}

interface Summary {
    total_jobcards: number;
    total_value: number;
    total_subtotal: number;
    total_tax: number;
    total_discount: number;
    total_minutes: number;
    total_hours: number;
    formatted_total_time: string;
    billable_minutes: number;
    billable_hours: number;
    formatted_billable_time: string;
    billable_amount: number;
    total_line_items: number;
    total_time_entries: number;
    status_breakdown: Record<string, number>;
}

interface Filters {
    date_from: string;
    date_to: string;
    due_date_from: string;
    due_date_to: string;
    status: string;
    customer_id: string;
    assigned_to_user_id: string;
    assigned_to_team_id: string;
    include_status_trackers: boolean;
    sort_by: string;
    sort_dir: string;
}

const props = defineProps<{
    reportData: JobcardRow[];
    summary: Summary;
    customers: Customer[];
    users: AppUser[];
    teams: TeamOption[];
    filters: Filters;
}>();

// Filter state
const showFilters = ref(true);
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const dueDateFrom = ref(props.filters.due_date_from || '');
const dueDateTo = ref(props.filters.due_date_to || '');
const status = ref(props.filters.status || '');
const customerId = ref(props.filters.customer_id || '');
const assignedUserId = ref(props.filters.assigned_to_user_id || '');
const assignedTeamId = ref(props.filters.assigned_to_team_id || '');
const includeStatusTrackers = ref(props.filters.include_status_trackers || false);

// Expanded rows
const expandedRows = ref<Set<number>>(new Set());

const toggleRow = (id: number) => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

const expandAll = () => {
    props.reportData.forEach(jc => expandedRows.value.add(jc.id));
};

const collapseAll = () => {
    expandedRows.value.clear();
};

const applyFilters = () => {
    const params: Record<string, string> = {};
    if (dateFrom.value) params.date_from = dateFrom.value;
    if (dateTo.value) params.date_to = dateTo.value;
    if (dueDateFrom.value) params.due_date_from = dueDateFrom.value;
    if (dueDateTo.value) params.due_date_to = dueDateTo.value;
    if (status.value) params.status = status.value;
    if (customerId.value) params.customer_id = customerId.value;
    if (assignedUserId.value) params.assigned_to_user_id = assignedUserId.value;
    if (assignedTeamId.value) params.assigned_to_team_id = assignedTeamId.value;
    if (includeStatusTrackers.value) params.include_status_trackers = '1';

    router.get('/reports/jobcard-detail', params, { preserveState: true, preserveScroll: false });
};

const clearFilters = () => {
    dateFrom.value = '';
    dateTo.value = '';
    dueDateFrom.value = '';
    dueDateTo.value = '';
    status.value = '';
    customerId.value = '';
    assignedUserId.value = '';
    assignedTeamId.value = '';
    includeStatusTrackers.value = false;
    router.get('/reports/jobcard-detail', {}, { preserveState: true });
};

const hasActiveFilters = computed(() => {
    return dateFrom.value || dateTo.value || dueDateFrom.value || dueDateTo.value || status.value || customerId.value || assignedUserId.value || assignedTeamId.value;
});

const exportCsv = () => {
    const params = new URLSearchParams();
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);
    if (dueDateFrom.value) params.set('due_date_from', dueDateFrom.value);
    if (dueDateTo.value) params.set('due_date_to', dueDateTo.value);
    if (status.value) params.set('status', status.value);
    if (customerId.value) params.set('customer_id', customerId.value);
    if (assignedUserId.value) params.set('assigned_to_user_id', assignedUserId.value);
    if (assignedTeamId.value) params.set('assigned_to_team_id', assignedTeamId.value);
    if (includeStatusTrackers.value) params.set('include_status_trackers', '1');
    window.location.href = `/reports/jobcard-detail/export?${params.toString()}`;
};

const printReport = () => {
    window.print();
};

const fmtCurrency = (v: number) => 'R' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');

const statusLabel = (s: string) => {
    const map: Record<string, string> = {
        draft: 'Draft', pending: 'Pending', in_progress: 'In Progress',
        completed: 'Completed', cancelled: 'Cancelled',
    };
    return map[s] ?? s;
};

const statusBadge = (s: string) => {
    const map: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-700',
        pending: 'bg-yellow-100 text-yellow-700',
        in_progress: 'bg-blue-100 text-blue-700',
        completed: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700',
    };
    return map[s] ?? 'bg-gray-100 text-gray-700';
};

// Status bar colors for the stacked duration chart
const statusBarColor = (s: string) => {
    const map: Record<string, string> = {
        draft: '#9ca3af',
        pending: '#f59e0b',
        in_progress: '#3b82f6',
        completed: '#10b981',
        cancelled: '#ef4444',
    };
    return map[s] ?? '#9ca3af';
};

// Total duration minutes from status_durations
const totalDurationMinutes = (durations: Record<string, StatusDuration> | null): number => {
    if (!durations) return 0;
    return Object.values(durations).reduce((sum, d) => sum + d.minutes, 0);
};

// Compact inline summary of status durations
const durationSummary = (durations: Record<string, StatusDuration> | null): string => {
    if (!durations) return '-';
    const order = ['draft', 'pending', 'in_progress', 'completed', 'cancelled'];
    const parts: string[] = [];
    for (const s of order) {
        if (durations[s] && durations[s].minutes > 0) {
            parts.push(`${statusLabel(s)} ${durations[s].formatted}`);
        }
    }
    return parts.length > 0 ? parts.join(', ') : '-';
};

// Column count for the table (dynamic based on tracker toggle)
const colCount = computed(() => includeStatusTrackers.value ? 11 : 10);
</script>

<template>
    <Head title="Detailed Jobcards Report" />

    <AppLayout :breadcrumbs="[
        { title: 'Reports', href: '/reports' },
        { title: 'Detailed Jobcards', href: '#' }
    ]">
        <div class="p-4 space-y-6 print:p-0">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4 print:hidden">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Detailed Jobcards Report</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ props.summary.total_jobcards }} jobcard{{ props.summary.total_jobcards !== 1 ? 's' : '' }} found</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="showFilters = !showFilters" class="inline-flex items-center gap-1.5 rounded border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <Filter class="h-4 w-4" />
                        Filters
                        <span v-if="hasActiveFilters" class="ml-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-xs text-white">!</span>
                    </button>
                    <button @click="exportCsv" class="inline-flex items-center gap-1.5 rounded border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <Download class="h-4 w-4" />
                        Export CSV
                    </button>
                    <button @click="printReport" class="inline-flex items-center gap-1.5 rounded border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        <Printer class="h-4 w-4" />
                        Print
                    </button>
                </div>
            </div>

            <!-- Filters Panel -->
            <div v-if="showFilters" class="bg-white rounded-lg border p-4 print:hidden">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Start Date From</label>
                        <input v-model="dateFrom" type="date" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Start Date To</label>
                        <input v-model="dateTo" type="date" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Due Date From</label>
                        <input v-model="dueDateFrom" type="date" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Due Date To</label>
                        <input v-model="dueDateTo" type="date" class="w-full rounded border px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <select v-model="status" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Customer</label>
                        <select v-model="customerId" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">All Customers</option>
                            <option v-for="c in props.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Assigned User</label>
                        <select v-model="assignedUserId" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">All Users</option>
                            <option v-for="u in props.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Assigned Team</label>
                        <select v-model="assignedTeamId" class="w-full rounded border px-3 py-2 text-sm">
                            <option value="">All Teams</option>
                            <option v-for="t in props.teams" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input
                            v-model="includeStatusTrackers"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <span class="text-sm font-medium text-gray-700">Include Status Time Trackers</span>
                        <span class="text-xs text-gray-400">(shows how long each jobcard spent in each status)</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <button v-if="hasActiveFilters" @click="clearFilters" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700">
                            <X class="h-4 w-4" /> Clear
                        </button>
                        <button @click="applyFilters" class="rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90">
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 print:grid-cols-6">
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <ClipboardList class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Jobcards</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ props.summary.total_jobcards }}</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <DollarSign class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Total Value</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ fmtCurrency(props.summary.total_value) }}</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <Clock class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Total Time</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ props.summary.formatted_total_time }}</p>
                    <p class="text-xs text-gray-500">{{ props.summary.total_hours }}h</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <Clock class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Billable Time</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ props.summary.formatted_billable_time }}</p>
                    <p class="text-xs text-gray-500">{{ fmtCurrency(props.summary.billable_amount) }}</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <FileText class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Line Items</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ props.summary.total_line_items }}</p>
                </div>
                <div class="bg-white rounded-lg border p-4">
                    <div class="flex items-center gap-2 text-gray-500 mb-1">
                        <Users class="h-4 w-4" />
                        <span class="text-xs font-medium uppercase">Time Entries</span>
                    </div>
                    <p class="text-2xl font-bold text-gray-900">{{ props.summary.total_time_entries }}</p>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="bg-white rounded-lg border p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Status Breakdown</h3>
                <div class="flex flex-wrap gap-3">
                    <div v-for="(count, st) in props.summary.status_breakdown" :key="st" class="flex items-center gap-2">
                        <span :class="statusBadge(st as string)" class="rounded-full px-2.5 py-0.5 text-xs font-medium">
                            {{ statusLabel(st as string) }}
                        </span>
                        <span class="text-sm font-semibold text-gray-900">{{ count }}</span>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b bg-gray-50 print:hidden">
                    <span class="text-sm font-medium text-gray-700">Jobcard Details</span>
                    <div class="flex items-center gap-2 text-sm">
                        <button @click="expandAll" class="text-blue-600 hover:text-blue-800">Expand All</button>
                        <span class="text-gray-300">|</span>
                        <button @click="collapseAll" class="text-blue-600 hover:text-blue-800">Collapse All</button>
                    </div>
                </div>

                <div v-if="props.reportData.length === 0" class="p-12 text-center text-gray-500">
                    No jobcards match the selected filters.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50 text-left">
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase w-8 print:hidden"></th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Job #</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Assigned To</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Start Date</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Due Date</th>
                                <th v-if="includeStatusTrackers" class="px-4 py-3 text-xs font-medium text-gray-500 uppercase">Status Duration</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase text-right">Time Booked</th>
                                <th class="px-4 py-3 text-xs font-medium text-gray-500 uppercase text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="jc in props.reportData" :key="jc.id">
                                <!-- Main Row -->
                                <tr
                                    class="border-b hover:bg-gray-50 cursor-pointer transition-colors"
                                    @click="toggleRow(jc.id)"
                                >
                                    <td class="px-4 py-3 print:hidden">
                                        <ChevronDown v-if="expandedRows.has(jc.id)" class="h-4 w-4 text-gray-400" />
                                        <ChevronRight v-else class="h-4 w-4 text-gray-400" />
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs">
                                        <Link :href="`/jobcards/${jc.id}`" class="text-blue-600 hover:underline" @click.stop>
                                            {{ jc.job_number }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900 max-w-[200px] truncate">{{ jc.title }}</td>
                                    <td class="px-4 py-3 text-gray-700">
                                        <Link v-if="jc.customer" :href="`/customers/${jc.customer.id}`" class="text-blue-600 hover:underline" @click.stop>
                                            {{ jc.customer.name }}
                                        </Link>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="statusBadge(jc.status)" class="rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap">
                                            {{ statusLabel(jc.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                        <span v-if="jc.assigned_user">{{ jc.assigned_user.name }}</span>
                                        <span v-else-if="jc.assigned_team" class="inline-flex items-center gap-1">
                                            {{ jc.assigned_team.name }}
                                            <span class="text-xs text-gray-400">(Team)</span>
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ jc.start_date ?? '-' }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ jc.due_date ?? '-' }}</td>
                                    <td v-if="includeStatusTrackers" class="px-4 py-3 text-xs text-gray-600 max-w-[280px]">
                                        <span v-if="jc.status_durations">{{ durationSummary(jc.status_durations) }}</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <span v-if="jc.total_minutes > 0" class="font-medium">{{ jc.formatted_total_time }}</span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium whitespace-nowrap">{{ jc.formatted_total }}</td>
                                </tr>

                                <!-- Expanded Detail -->
                                <tr v-if="expandedRows.has(jc.id)" :key="'detail-' + jc.id">
                                    <td :colspan="colCount" class="bg-gray-50/50 px-4 py-4">
                                        <div class="space-y-4 ml-4">
                                            <!-- Summary row -->
                                            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                                <div>
                                                    <span class="text-gray-500 block text-xs">Subtotal</span>
                                                    <span class="font-medium">{{ fmtCurrency(jc.subtotal) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 block text-xs">Discount</span>
                                                    <span class="font-medium">{{ fmtCurrency(jc.discount_amount) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 block text-xs">Tax ({{ jc.tax_rate }}%)</span>
                                                    <span class="font-medium">{{ fmtCurrency(jc.tax_amount) }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 block text-xs">Completed</span>
                                                    <span class="font-medium">{{ jc.completed_date ?? '-' }}</span>
                                                </div>
                                                <div>
                                                    <span class="text-gray-500 block text-xs">Invoice</span>
                                                    <Link v-if="jc.invoice" :href="`/invoices/${jc.invoice.id}`" class="text-blue-600 hover:underline font-medium" @click.stop>
                                                        {{ jc.invoice.invoice_number }}
                                                    </Link>
                                                    <span v-else class="text-gray-400 font-medium">Not invoiced</span>
                                                </div>
                                            </div>

                                            <!-- Status Time Tracker -->
                                            <div v-if="includeStatusTrackers && jc.status_durations && totalDurationMinutes(jc.status_durations) > 0">
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Status Time Tracker</h4>

                                                <!-- Stacked duration bar -->
                                                <div class="flex h-6 rounded-full overflow-hidden shadow-sm mb-3">
                                                    <template v-for="s in ['draft', 'pending', 'in_progress', 'completed', 'cancelled']" :key="s">
                                                        <div
                                                            v-if="jc.status_durations[s] && jc.status_durations[s].minutes > 0"
                                                            :style="{
                                                                width: ((jc.status_durations[s].minutes / totalDurationMinutes(jc.status_durations)) * 100) + '%',
                                                                backgroundColor: statusBarColor(s),
                                                            }"
                                                            :title="`${statusLabel(s)}: ${jc.status_durations[s].formatted}`"
                                                            class="h-full transition-all duration-300 cursor-pointer hover:opacity-80 flex items-center justify-center"
                                                        >
                                                            <span
                                                                v-if="(jc.status_durations[s].minutes / totalDurationMinutes(jc.status_durations)) > 0.1"
                                                                class="text-[10px] font-medium text-white truncate px-1"
                                                            >
                                                                {{ statusLabel(s) }}
                                                            </span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Duration legend -->
                                                <div class="flex flex-wrap gap-3 mb-3">
                                                    <template v-for="s in ['draft', 'pending', 'in_progress', 'completed', 'cancelled']" :key="'legend-' + s">
                                                        <div v-if="jc.status_durations[s] && jc.status_durations[s].minutes > 0" class="flex items-center gap-1.5 text-xs">
                                                            <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: statusBarColor(s) }"></span>
                                                            <span class="text-gray-600 font-medium">{{ statusLabel(s) }}:</span>
                                                            <span class="text-gray-900 font-semibold">{{ jc.status_durations[s].formatted }}</span>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- Transitions table -->
                                                <div v-if="jc.status_transitions && jc.status_transitions.length > 0" class="overflow-x-auto rounded-lg border">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="bg-gray-100 text-left">
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">From</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">To</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">Date / Time</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">Changed By</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="(t, idx) in jc.status_transitions" :key="idx" class="border-t">
                                                                <td class="px-3 py-2">
                                                                    <span v-if="t.from_status" :class="statusBadge(t.from_status)" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                                                        {{ statusLabel(t.from_status) }}
                                                                    </span>
                                                                    <span v-else class="text-gray-400 text-xs">Created</span>
                                                                </td>
                                                                <td class="px-3 py-2">
                                                                    <span :class="statusBadge(t.to_status)" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                                                        {{ statusLabel(t.to_status) }}
                                                                    </span>
                                                                </td>
                                                                <td class="px-3 py-2 text-gray-600 font-mono text-xs">{{ t.transitioned_at }}</td>
                                                                <td class="px-3 py-2 text-gray-700">{{ t.user_name }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Description -->
                                            <div v-if="jc.description" class="text-sm">
                                                <span class="text-gray-500 text-xs block mb-1">Description</span>
                                                <p class="text-gray-700">{{ jc.description }}</p>
                                            </div>

                                            <!-- Line Items -->
                                            <div v-if="jc.line_items.length > 0">
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">Line Items ({{ jc.line_items_count }})</h4>
                                                <div class="overflow-x-auto rounded-lg border">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="bg-gray-100 text-left">
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">Product / Description</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Qty</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Unit Price</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Discount</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Total</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="li in jc.line_items" :key="li.id" class="border-t">
                                                                <td class="px-3 py-2">
                                                                    <span v-if="li.product_name" class="font-medium">{{ li.product_name }}</span>
                                                                    <span v-if="li.description" class="text-gray-500" :class="{ 'block text-xs': li.product_name }">{{ li.description }}</span>
                                                                </td>
                                                                <td class="px-3 py-2 text-right">{{ li.quantity }}</td>
                                                                <td class="px-3 py-2 text-right">{{ fmtCurrency(li.unit_price) }}</td>
                                                                <td class="px-3 py-2 text-right">
                                                                    <span v-if="li.discount_percentage > 0">{{ li.discount_percentage }}%</span>
                                                                    <span v-else-if="li.discount_amount > 0">{{ fmtCurrency(li.discount_amount) }}</span>
                                                                    <span v-else class="text-gray-400">-</span>
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-medium">{{ fmtCurrency(li.total) }}</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- Time Entries -->
                                            <div v-if="jc.time_entries.length > 0">
                                                <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">
                                                    Time Entries ({{ jc.time_entries_count }})
                                                    <span class="ml-2 font-normal normal-case text-gray-400">
                                                        Total: {{ jc.formatted_total_time }}
                                                        &middot; Billable: {{ jc.formatted_billable_time }} ({{ fmtCurrency(jc.billable_amount) }})
                                                    </span>
                                                </h4>
                                                <div class="overflow-x-auto rounded-lg border">
                                                    <table class="w-full text-sm">
                                                        <thead>
                                                            <tr class="bg-gray-100 text-left">
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">User</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">Date</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500">Description</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Duration</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-center">Billable</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Rate</th>
                                                                <th class="px-3 py-2 text-xs font-medium text-gray-500 text-right">Amount</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr v-for="te in jc.time_entries" :key="te.id" class="border-t">
                                                                <td class="px-3 py-2 font-medium">{{ te.user_name }}</td>
                                                                <td class="px-3 py-2 text-gray-600">{{ te.date ?? '-' }}</td>
                                                                <td class="px-3 py-2 text-gray-700 max-w-[250px] truncate">{{ te.description ?? '-' }}</td>
                                                                <td class="px-3 py-2 text-right font-mono">{{ te.formatted_duration }}</td>
                                                                <td class="px-3 py-2 text-center">
                                                                    <span v-if="te.is_billable" class="inline-block h-2 w-2 rounded-full bg-green-500" title="Billable"></span>
                                                                    <span v-else class="inline-block h-2 w-2 rounded-full bg-gray-300" title="Non-billable"></span>
                                                                </td>
                                                                <td class="px-3 py-2 text-right">
                                                                    <span v-if="te.hourly_rate > 0">{{ fmtCurrency(te.hourly_rate) }}/hr</span>
                                                                    <span v-else class="text-gray-400">-</span>
                                                                </td>
                                                                <td class="px-3 py-2 text-right font-medium">
                                                                    <span v-if="te.total_amount > 0">{{ fmtCurrency(te.total_amount) }}</span>
                                                                    <span v-else class="text-gray-400">-</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>

                                            <!-- No details -->
                                            <div v-if="jc.line_items.length === 0 && jc.time_entries.length === 0" class="text-sm text-gray-400 italic">
                                                No line items or time entries recorded.
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <!-- Footer Totals -->
                        <tfoot>
                            <tr class="border-t-2 bg-gray-50 font-semibold">
                                <td class="px-4 py-3 print:hidden"></td>
                                <td class="px-4 py-3" :colspan="includeStatusTrackers ? 8 : 7">Totals ({{ props.summary.total_jobcards }} jobcards)</td>
                                <td class="px-4 py-3 text-right">{{ props.summary.formatted_total_time }}</td>
                                <td class="px-4 py-3 text-right">{{ fmtCurrency(props.summary.total_value) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    :deep(.print\:hidden) {
        display: none !important;
    }
}
</style>
