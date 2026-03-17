<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Save, Eye } from 'lucide-vue-next';

interface Report {
    id: number;
    name: string;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    config: {
        columns: string[];
        group_by?: string;
        sort_by?: string;
        sort_direction?: 'asc' | 'desc';
        show_totals?: boolean;
    };
    report_template_id?: number | null;
}

interface ReportTemplate {
    id: number;
    name: string;
    description: string | null;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    config: any;
    is_default: boolean;
}

interface Company {
    id: number;
    name: string;
}

interface Props {
    report: Report;
    templates: ReportTemplate[];
    currentCompany: Company;
}

const props = defineProps<Props>();

// Available columns for each entity type
const availableColumns = {
    invoice: [
        { value: 'invoice_number', label: 'Invoice Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'salesperson.name', label: 'Salesperson' },
        { value: 'formatted_date', label: 'Invoice Date' },
        { value: 'due_date', label: 'Due Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
        { value: 'payment_count', label: 'Payments Count', groupable: false, sortable: false },
        { value: 'last_payment_date', label: 'Last Payment Date', groupable: false, sortable: false },
        { value: 'payments_summary', label: 'Payments (Type + Amount)', groupable: false, sortable: false },
        { value: 'total_paid', label: 'Total Paid', groupable: false, sortable: false },
        { value: 'remaining_balance', label: 'Balance Due', groupable: false, sortable: false },
    ],
    quote: [
        { value: 'quote_number', label: 'Quote Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'formatted_date', label: 'Date' },
        { value: 'expiry_date', label: 'Expiry Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
    ],
    jobcard: [
        { value: 'job_number', label: 'Job Number' },
        { value: 'customer.name', label: 'Customer' },
        { value: 'formatted_date', label: 'Start Date' },
        { value: 'due_date', label: 'Due Date' },
        { value: 'completed_date', label: 'Completed Date' },
        { value: 'status', label: 'Status' },
        { value: 'subtotal', label: 'Subtotal' },
        { value: 'tax_amount', label: 'Tax Amount' },
        { value: 'discount_amount', label: 'Discount' },
        { value: 'formatted_total', label: 'Total' },
    ],
};

const entityType = ref(props.report.entity_type);
const selectedColumns = ref<string[]>([...props.report.config.columns]);
const groupBy = ref(props.report.config.group_by || '');
const sortBy = ref(props.report.config.sort_by || 'created_at');
const sortDirection = ref<'asc' | 'desc'>(props.report.config.sort_direction || 'desc');
const showTotals = ref(props.report.config.show_totals ?? true);
const groupableColumns = computed(() => availableColumns[entityType.value].filter(column => column.groupable !== false));
const sortableColumns = computed(() => availableColumns[entityType.value].filter(column => column.sortable !== false));

// Filters are now part of templates, not reports
const form = useForm({
    name: props.report.name,
    entity_type: entityType.value,
    config: {
        columns: selectedColumns.value,
        group_by: groupBy.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
        show_totals: showTotals.value,
    },
    report_template_id: props.report.report_template_id || null,
});

// Watch for entity type changes
watch(entityType, (newType) => {
    form.entity_type = newType;
});

// Watch for config changes
watch([selectedColumns, groupBy, sortBy, sortDirection, showTotals], () => {
    form.config = {
        columns: selectedColumns.value,
        group_by: groupBy.value,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value,
        show_totals: showTotals.value,
    };
});

// Filters are now part of templates, not reports - no need to watch filter changes

const toggleColumn = (columnValue: string) => {
    const index = selectedColumns.value.indexOf(columnValue);
    if (index > -1) {
        selectedColumns.value.splice(index, 1);
    } else {
        selectedColumns.value.push(columnValue);
    }
};

const submit = () => {
    form.put(`/reports/${props.report.id}`);
};
</script>

<template>
    <Head :title="`Edit ${props.report.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Reports', href: '/reports' },
        { title: props.report.name, href: `/reports/${props.report.id}` },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Edit Report</h1>
                <button
                    @click="submit"
                    class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 flex items-center gap-2"
                >
                    <Save class="w-4 h-4" />
                    Update Report
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Basic Information -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Basic Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Report Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.name }"
                                required
                            />
                            <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entity Type *</label>
                            <select
                                v-model="entityType"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.entity_type }"
                                required
                            >
                                <option value="invoice">Invoice</option>
                                <option value="quote">Quote</option>
                                <option value="jobcard">Job Card</option>
                            </select>
                            <div v-if="form.errors.entity_type" class="text-red-500 text-sm mt-1">
                                {{ form.errors.entity_type }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Column Selection -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Select Columns</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <label
                            v-for="column in availableColumns[entityType]"
                            :key="column.value"
                            class="flex items-center gap-2 p-3 border rounded cursor-pointer hover:bg-gray-50"
                            :class="{ 'bg-blue-50 border-blue-500': selectedColumns.includes(column.value) }"
                        >
                            <input
                                type="checkbox"
                                :value="column.value"
                                :checked="selectedColumns.includes(column.value)"
                                @change="toggleColumn(column.value)"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm font-medium text-gray-700">{{ column.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Filters are now part of templates, not reports -->

                <!-- Grouping & Sorting -->
                <div class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Grouping & Sorting</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Group By</label>
                            <select v-model="groupBy" class="w-full rounded border px-3 py-2">
                                <option value="">None</option>
                                <option
                                    v-for="column in groupableColumns"
                                    :key="column.value"
                                    :value="column.value"
                                >
                                    {{ column.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                            <select v-model="sortBy" class="w-full rounded border px-3 py-2">
                                <option
                                    v-for="column in sortableColumns"
                                    :key="column.value"
                                    :value="column.value"
                                >
                                    {{ column.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sort Direction</label>
                            <select v-model="sortDirection" class="w-full rounded border px-3 py-2">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="showTotals"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm font-medium text-gray-700">Show Totals</span>
                            </label>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
