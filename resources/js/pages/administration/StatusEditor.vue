<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import administration from '@/routes/administration';
import { RotateCcw } from 'lucide-vue-next';

type StatusOption = { value: string; label: string };

const props = defineProps<{
    jobcardStatusOptions: StatusOption[];
    quoteStatusOptions: StatusOption[];
    defaultJobcardStatusLabels: Record<string, string>;
    defaultQuoteStatusLabels: Record<string, string>;
    company: { id: number; name: string };
}>();

const toLabelMap = (options: StatusOption[]) =>
    options.reduce((acc, item) => {
        acc[item.value] = item.label;
        return acc;
    }, {} as Record<string, string>);

const form = useForm({
    jobcard_status_labels: toLabelMap(props.jobcardStatusOptions),
    quote_status_labels: toLabelMap(props.quoteStatusOptions),
});

const resetJobcardDefaults = () => {
    form.jobcard_status_labels = { ...props.defaultJobcardStatusLabels };
};

const resetQuoteDefaults = () => {
    form.quote_status_labels = { ...props.defaultQuoteStatusLabels };
};

const submit = () => {
    form.put('/administration/status-editor');
};
</script>

<template>
    <Head title="Status Editor" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Status Editor', href: '#' },
    ]">
        <div class="p-4">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Status Editor</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Customize Jobcard and Quote status labels for {{ props.company.name }}.
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Jobcard Status Labels</h2>
                            <p class="text-sm text-gray-600">Edit how each jobcard status is displayed in the app.</p>
                        </div>
                        <button
                            type="button"
                            @click="resetJobcardDefaults"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Reset to Default
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="status in props.jobcardStatusOptions" :key="status.value">
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                {{ status.value.replace(/_/g, ' ') }}
                            </label>
                            <input
                                v-model="form.jobcard_status_labels[status.value]"
                                type="text"
                                maxlength="50"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                            <p
                                v-if="(form.errors as Record<string, string>)[`jobcard_status_labels.${status.value}`]"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ (form.errors as Record<string, string>)[`jobcard_status_labels.${status.value}`] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">Quote Status Labels</h2>
                            <p class="text-sm text-gray-600">Edit how each quote status is displayed in the app.</p>
                        </div>
                        <button
                            type="button"
                            @click="resetQuoteDefaults"
                            class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <RotateCcw class="h-4 w-4" />
                            Reset to Default
                        </button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div v-for="status in props.quoteStatusOptions" :key="status.value">
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                {{ status.value.replace(/_/g, ' ') }}
                            </label>
                            <input
                                v-model="form.quote_status_labels[status.value]"
                                type="text"
                                maxlength="50"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                            />
                            <p
                                v-if="(form.errors as Record<string, string>)[`quote_status_labels.${status.value}`]"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ (form.errors as Record<string, string>)[`quote_status_labels.${status.value}`] }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Status Labels' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
