<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, FileText, FileBarChart, Trash2, Eye, Edit, Copy } from 'lucide-vue-next';

interface Report {
    id: number;
    name: string;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    created_at: string;
    template?: ReportTemplate;
    creator?: {
        id: number;
        name: string;
    };
}

interface ReportTemplate {
    id: number;
    name: string;
    description: string | null;
    entity_type: 'invoice' | 'quote' | 'jobcard';
    is_default: boolean;
    created_by?: number;
    creator?: {
        id: number;
        name: string;
    };
}

interface Company {
    id: number;
    name: string;
}

interface Props {
    reports: {
        data: Report[];
        links: any[];
        meta: any;
    };
    templates: ReportTemplate[];
    currentCompany: Company;
}

const props = defineProps<Props>();

const deleteReport = (reportId: number) => {
    if (confirm('Are you sure you want to delete this report?')) {
        router.delete(`/reports/${reportId}`);
    }
};

const useTemplate = (templateId: number) => {
    router.get('/reports/create', { template_id: templateId });
};

const editTemplate = (templateId: number, event: Event) => {
    event.stopPropagation();
    router.get(`/reports/templates/${templateId}/edit`);
};

const deleteTemplate = (templateId: number, event: Event) => {
    event.stopPropagation();
    if (confirm('Are you sure you want to delete this template? This action cannot be undone.')) {
        router.delete(`/reports/templates/${templateId}`);
    }
};
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="[{ title: 'Reports', href: '/reports' }]">
        <div class="p-4">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Reports</h1>
                <Link href="/reports/create" class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 flex items-center gap-2">
                    <Plus class="w-4 h-4" />
                    Run Report
                </Link>
            </div>

            <!-- Templates Section -->
            <div class="mb-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Report Templates</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="template in props.templates"
                        :key="template.id"
                        class="bg-white rounded-lg border p-4 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div 
                                class="flex items-center gap-2 flex-1 cursor-pointer"
                                @click="useTemplate(template.id)"
                            >
                                <FileBarChart class="w-5 h-5 text-blue-600" />
                                <h3 class="font-semibold text-gray-900">{{ template.name }}</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="template.is_default"
                                    class="px-2 py-1 text-xs font-semibold rounded bg-blue-100 text-blue-800"
                                >
                                    Default
                                </span>
                                <Link
                                    :href="`/reports/templates/${template.id}/edit`"
                                    @click="editTemplate(template.id, $event)"
                                    class="text-gray-600 hover:text-gray-900"
                                    title="Edit Template"
                                >
                                    <Edit class="w-4 h-4" />
                                </Link>
                                <button
                                    @click="deleteTemplate(template.id, $event)"
                                    class="text-red-600 hover:text-red-900"
                                    title="Delete Template"
                                >
                                    <Trash2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                        <div 
                            class="cursor-pointer"
                            @click="useTemplate(template.id)"
                        >
                            <p v-if="template.description" class="text-sm text-gray-600 mb-2">
                                {{ template.description }}
                            </p>
                            <div class="flex items-center gap-2 text-xs text-gray-500">
                                <span class="px-2 py-1 rounded bg-gray-100">
                                    {{ template.entity_type }}
                                </span>
                                <span v-if="template.creator">
                                    by {{ template.creator.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reports Section -->
            <div>
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Saved Reports</h2>
                <div v-if="props.reports.data.length === 0" class="bg-white rounded-lg border p-8 text-center">
                    <FileText class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <p class="text-gray-600">No reports yet. Create your first report to get started.</p>
                </div>
                <div v-else class="bg-white rounded-lg border overflow-hidden">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Template
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created By
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Created At
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="report in props.reports.data" :key="report.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ report.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-semibold rounded bg-gray-100 text-gray-800">
                                        {{ report.entity_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ report.template?.name || 'Custom' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ report.creator?.name || 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500">
                                        {{ new Date(report.created_at).toLocaleDateString() }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="`/reports/${report.id}`"
                                            class="text-blue-600 hover:text-blue-900"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </Link>
                                        <Link
                                            :href="`/reports/${report.id}/edit`"
                                            class="text-gray-600 hover:text-gray-900"
                                        >
                                            <Edit class="w-4 h-4" />
                                        </Link>
                                        <button
                                            @click="deleteReport(report.id)"
                                            class="text-red-600 hover:text-red-900"
                                        >
                                            <Trash2 class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
