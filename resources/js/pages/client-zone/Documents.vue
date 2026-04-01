<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';

interface CompanyOption {
    id: number;
    name: string;
}

interface Row {
    id: number;
    type: string;
    type_key?: string;
    number: string;
    status: string;
    total: string | number;
    date: string | null;
    company_name: string | null;
    view_url?: string;
    download_pdf_url?: string;
}

const props = defineProps<{
    availableCompanies: CompanyOption[];
    selectedCompanyId: number | null;
    selectedDocumentType: string | null;
    statementPdfUrl: string | null;
    search: string;
    documents: {
        data: Row[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}>();

function onCompanyChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const params: Record<string, string> = {};
    if (value) params.company_id = value;
    if (props.selectedDocumentType) params.document_type = props.selectedDocumentType;
    if (props.search) params.search = props.search;
    router.get('/client-zone/documents', params, { preserveState: true, replace: true });
}

function onSearch(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    const params: Record<string, string> = {};
    if (props.selectedCompanyId) params.company_id = String(props.selectedCompanyId);
    if (props.selectedDocumentType) params.document_type = props.selectedDocumentType;
    if (value.trim()) params.search = value.trim();
    router.get('/client-zone/documents', params, { preserveState: true, replace: true });
}

function onDocumentTypeChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const params: Record<string, string> = {};
    if (props.selectedCompanyId) params.company_id = String(props.selectedCompanyId);
    if (value) params.document_type = value;
    if (props.search) params.search = props.search;
    router.get('/client-zone/documents', params, { preserveState: true, replace: true });
}
</script>

<template>
    <Head title="Client Documents" />
    <AppLayout :breadcrumbs="[{ title: 'Client Zone', href: '/client-zone' }, { title: 'Documents', href: '/client-zone/documents' }]">
        <div class="space-y-6 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h1 class="text-2xl font-semibold">Documents</h1>
                <div class="flex items-center gap-2">
                    <input
                        type="search"
                        :value="search"
                        @change="onSearch"
                        placeholder="Search documents..."
                        class="rounded border px-3 py-2 text-sm"
                    />
                    <select class="rounded border px-3 py-2 text-sm" :value="selectedCompanyId ?? ''" @change="onCompanyChange">
                        <option value="">All available companies</option>
                        <option v-for="company in availableCompanies" :key="company.id" :value="company.id">{{ company.name }}</option>
                    </select>
                    <select class="rounded border px-3 py-2 text-sm" :value="selectedDocumentType ?? ''" @change="onDocumentTypeChange">
                        <option value="">All document types</option>
                        <option value="invoice">Invoice</option>
                        <option value="quote">Quote</option>
                        <option value="jobcard">Jobcard</option>
                        <option value="credit-note">Credit Note</option>
                    </select>
                    <a
                        v-if="statementPdfUrl"
                        :href="statementPdfUrl"
                        class="rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-800"
                    >
                        Statement PDF
                    </a>
                </div>
            </div>

            <div class="rounded border p-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b bg-gray-50">
                                <th class="p-2 text-left">Type</th>
                                <th class="p-2 text-left">Number</th>
                                <th class="p-2 text-left">Status</th>
                                <th class="p-2 text-left">Company</th>
                                <th class="p-2 text-right">Total</th>
                                <th class="p-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in documents.data" :key="`${row.type}-${row.id}`" class="border-b">
                                <td class="p-2">{{ row.type }}</td>
                                <td class="p-2">{{ row.number }}</td>
                                <td class="p-2">{{ row.status }}</td>
                                <td class="p-2">{{ row.company_name }}</td>
                                <td class="p-2 text-right">{{ row.total }}</td>
                                <td class="p-2 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a v-if="row.view_url" :href="row.view_url" class="rounded bg-slate-100 px-3 py-1.5 text-slate-800 hover:bg-slate-200">View</a>
                                        <a v-if="row.download_pdf_url" :href="row.download_pdf_url" class="rounded bg-blue-600 px-3 py-1.5 text-white hover:bg-blue-700">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a
                    v-for="link in documents.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded border px-3 py-1.5 text-sm"
                    :class="[
                        link.active ? 'bg-slate-800 text-white border-slate-800' : 'bg-white text-slate-700',
                        !link.url ? 'pointer-events-none opacity-50' : '',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>
