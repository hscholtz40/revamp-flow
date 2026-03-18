<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import EmailTemplateUnlayerEditor from '@/components/EmailTemplateUnlayerEditor.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    variableGroups: Record<string, string[]>;
}>();

const form = useForm({
    name: '',
    subject: '',
    html_template: '',
    css_styles: '',
    is_active: true,
    is_default: false,
});

const submit = () => {
    form.css_styles = '';
    form.post('/administration/email-templates');
};
</script>

<template>
    <Head title="Create Email Template" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Email Templates', href: '/administration/email-templates' },
        { title: 'Create', href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Create Email Template</h1>
                <Link
                    href="/administration/email-templates"
                    class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Back
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Template Name</label>
                            <input v-model="form.name" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Email Subject</label>
                            <input v-model="form.subject" type="text" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            <p v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Active template
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                            Set as default template
                        </label>
                    </div>

                    <div class="rounded-lg border bg-white p-4 shadow-sm lg:col-span-2">
                        <h2 class="mb-2 text-sm font-semibold text-gray-900">Available Variables</h2>
                        <p class="mb-3 text-xs text-gray-600">Drag variables from the editor panel, or copy tokens below into subject/body.</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-for="(variables, groupName) in props.variableGroups" :key="groupName" class="rounded border border-gray-200 p-3">
                                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ groupName }}</h3>
                                <ul class="space-y-1">
                                    <li v-for="token in variables" :key="token" class="rounded bg-gray-50 px-2 py-1 font-mono text-xs text-gray-700">
                                        {{ token }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border bg-white p-4 shadow-sm">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Template Editor</label>
                    <EmailTemplateUnlayerEditor
                        v-model="form.html_template"
                        image-upload-url="/administration/email-templates/upload-image"
                    />
                    <p class="mt-2 text-xs text-gray-500">Use the designer tab for drag/drop content, or Manual HTML for direct editing.</p>
                    <p v-if="form.errors.html_template" class="mt-1 text-sm text-red-600">{{ form.errors.html_template }}</p>
                </div>

                <div class="flex justify-end gap-3">
                    <Link
                        href="/administration/email-templates"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Template' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

