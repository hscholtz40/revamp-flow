<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import administration from '@/routes/administration';
import PdfTemplateEditor from '@/components/PdfTemplateEditor.vue';

interface PdfTemplate {
    id: number;
    module: string;
    name: string;
    html_template: string;
    css_styles: string | null;
    default_data: Record<string, any> | null;
    is_active: boolean;
    is_default: boolean;
}

interface Props {
    template: PdfTemplate;
    modules: Record<string, string>;
}

const props = defineProps<Props>();

const form = useForm({
    module: props.template.module,
    name: props.template.name,
    html_template: props.template.html_template,
    css_styles: props.template.css_styles || '',
    default_data: props.template.default_data || {},
    is_active: props.template.is_active,
    is_default: props.template.is_default || false,
});

function submit() {
    form.put(administration.pdfTemplates.update(props.template.id).url);
}
</script>

<template>
    <Head :title="`Edit ${props.template.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'PDF Templates', href: administration.pdfTemplates.index().url },
        { title: props.template.name, href: '#' }
    ]">
        <div class="p-4">
            <div class="mx-auto max-w-full">
                <!-- Header -->
                <div class="mb-6 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <Link
                            :href="administration.pdfTemplates.index().url"
                            class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back to Templates
                        </Link>
                    </div>
                </div>

                <!-- Form -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">Edit PDF Template</h2>
                        <p class="text-sm text-gray-600">Update template: {{ props.template.name }}</p>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-6">
                        <!-- Module -->
                        <div>
                            <label for="module" class="block text-sm font-medium text-gray-700 mb-1">
                                Module *
                            </label>
                            <select
                                id="module"
                                v-model="form.module"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.module }"
                            >
                                <option v-for="(moduleName, moduleKey) in props.modules" :key="moduleKey" :value="moduleKey">
                                    {{ moduleName }}
                                </option>
                            </select>
                            <div v-if="form.errors.module" class="mt-1 text-sm text-red-600">
                                {{ form.errors.module }}
                            </div>
                        </div>

                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                Template Name *
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.name }"
                            />
                            <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                {{ form.errors.name }}
                            </div>
                        </div>

                        <!-- HTML Template -->
                        <div class="w-full">
                            <label for="html_template" class="block text-sm font-medium text-gray-700 mb-1">
                                HTML Template *
                            </label>
                            <div class="w-full">
                                <PdfTemplateEditor
                                    v-model="form.html_template"
                                    :module="form.module"
                                    :disabled="form.processing"
                                    :cssStyles="form.css_styles"
                                    @update:cssStyles="form.css_styles = $event"
                                />
                            </div>
                            <div v-if="form.errors.html_template" class="mt-1 text-sm text-red-600">
                                {{ form.errors.html_template }}
                            </div>
                            <p class="mt-2 text-xs text-gray-500">
                                Use the Source button in the toolbar to edit raw HTML code
                            </p>
                        </div>

                        <!-- CSS Styles -->
                        <div>
                            <label for="css_styles" class="block text-sm font-medium text-gray-700 mb-1">
                                CSS Styles
                            </label>
                            <textarea
                                id="css_styles"
                                v-model="form.css_styles"
                                rows="10"
                                class="w-full rounded-md border-gray-300 shadow-sm font-mono text-sm focus:border-blue-500 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.css_styles }"
                            ></textarea>
                            <div v-if="form.errors.css_styles" class="mt-1 text-sm text-red-600">
                                {{ form.errors.css_styles }}
                            </div>
                        </div>

                        <!-- Is Active -->
                        <div class="flex items-center">
                            <input
                                id="is_active"
                                v-model="form.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label for="is_active" class="ml-2 block text-sm text-gray-700">
                                Active (template will be available for use)
                            </label>
                        </div>

                        <!-- Is Default -->
                        <div class="flex items-center">
                            <input
                                id="is_default"
                                v-model="form.is_default"
                                type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label for="is_default" class="ml-2 block text-sm text-gray-700">
                                Set as Default (this template will be automatically selected when downloading/emailing PDFs)
                            </label>
                            <p class="ml-2 text-xs text-gray-500">
                                Only one template per module can be set as default
                            </p>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t">
                            <Link
                                :href="administration.pdfTemplates.index().url"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Updating...' : 'Update Template' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

