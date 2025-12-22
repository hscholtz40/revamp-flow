<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, FileText } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface PdfTemplate {
    id: number;
    module: string;
    name: string;
    html_template: string;
    css_styles: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    modules: Record<string, string>;
    templates: Record<string, PdfTemplate[]>;
}

const props = defineProps<Props>();

function deleteTemplate(template: PdfTemplate) {
    if (confirm(`Are you sure you want to delete "${template.name}"?`)) {
        router.delete(administration.pdfTemplates.destroy(template.id).url);
    }
}

function getModuleIcon(module: string) {
    const icons: Record<string, string> = {
        'invoice': '📄',
        'quote': '💼',
        'jobcard': '📋',
        'proforma-invoice': '🧾',
    };
    return icons[module] || '📄';
}
</script>

<template>
    <Head title="PDF Templates" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'PDF Templates', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">PDF Templates</h1>
                    <p class="text-gray-600">Manage PDF templates for invoices, quotes, jobcards, and proforma invoices</p>
                </div>
                <div class="flex gap-2">
                    <Link
                        v-for="(moduleName, moduleKey) in props.modules"
                        :key="moduleKey"
                        :href="administration.pdfTemplates.create().url + '?module=' + moduleKey"
                        class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add {{ moduleName }} Template
                    </Link>
                </div>
            </div>

            <!-- Templates by Module -->
            <div class="space-y-6">
                <div
                    v-for="(moduleName, moduleKey) in props.modules"
                    :key="moduleKey"
                    class="rounded-lg border bg-white shadow-sm"
                >
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-2xl">{{ getModuleIcon(moduleKey) }}</span>
                                <div>
                                    <h2 class="text-lg font-semibold text-gray-900">{{ moduleName }} Templates</h2>
                                    <p class="text-sm text-gray-600">
                                        {{ (props.templates[moduleKey] || []).length }} template(s)
                                    </p>
                                </div>
                            </div>
                            <Link
                                :href="administration.pdfTemplates.create().url + '?module=' + moduleKey"
                                class="flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                <Plus class="h-4 w-4" />
                                Add Template
                            </Link>
                        </div>
                    </div>

                    <div v-if="!props.templates[moduleKey] || props.templates[moduleKey].length === 0" class="p-8 text-center">
                        <FileText class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-lg font-medium text-gray-900">No templates found</h3>
                        <p class="mt-1 text-gray-500">Get started by creating your first {{ moduleName }} template.</p>
                        <div class="mt-6">
                            <Link
                                :href="administration.pdfTemplates.create().url + '?module=' + moduleKey"
                                class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                            >
                                <Plus class="h-4 w-4" />
                                Add Template
                            </Link>
                        </div>
                    </div>

                    <div v-else class="divide-y divide-gray-200">
                        <div
                            v-for="template in props.templates[moduleKey]"
                            :key="template.id"
                            class="p-6 hover:bg-gray-50 transition-colors"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3">
                                        <h3 class="text-lg font-semibold text-gray-900">{{ template.name }}</h3>
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-1 text-xs font-medium',
                                                template.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                            ]"
                                        >
                                            {{ template.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Created {{ new Date(template.created_at).toLocaleDateString() }}
                                        <span v-if="template.updated_at !== template.created_at">
                                            • Updated {{ new Date(template.updated_at).toLocaleDateString() }}
                                        </span>
                                    </p>
                                    <div class="mt-2 flex items-center gap-4 text-xs text-gray-500">
                                        <span>HTML: {{ template.html_template?.length || 0 }} chars</span>
                                        <span v-if="template.css_styles">CSS: {{ template.css_styles.length }} chars</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="administration.pdfTemplates.edit(template.id).url"
                                        class="flex items-center gap-1 rounded border px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                                    >
                                        <Edit class="h-3 w-3" />
                                        Edit
                                    </Link>
                                    <button
                                        @click="deleteTemplate(template)"
                                        class="flex items-center gap-1 rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50"
                                    >
                                        <Trash2 class="h-3 w-3" />
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

