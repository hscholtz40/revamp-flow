<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface EmailTemplate {
    id: number;
    name: string;
    subject: string;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

defineProps<{
    templates: EmailTemplate[];
    variableGroups: Record<string, string[]>;
}>();

const deleteTemplate = (template: EmailTemplate) => {
    if (!confirm(`Delete email template "${template.name}"?`)) {
        return;
    }

    router.delete(`/administration/email-templates/${template.id}`);
};
</script>

<template>
    <Head title="Email Templates" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Email Templates', href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Email Templates</h1>
                    <p class="text-sm text-gray-600">Manage templates for customer and contact emails using the visual email editor.</p>
                </div>
                <Link
                    href="/administration/email-templates/create"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    New Template
                </Link>
            </div>

            <div class="rounded-lg border bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Subject</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="template in templates" :key="template.id">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    {{ template.name }}
                                    <span v-if="template.is_default" class="ml-2 inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">
                                        Default
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ template.subject }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span
                                        :class="template.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'"
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs"
                                    >
                                        {{ template.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <Link
                                            :href="`/administration/email-templates/${template.id}/edit`"
                                            class="rounded border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                        >
                                            Edit
                                        </Link>
                                        <button
                                            @click="deleteTemplate(template)"
                                            class="rounded border border-red-300 px-3 py-1 text-xs font-medium text-red-700 hover:bg-red-50"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="templates.length === 0">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No email templates found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

