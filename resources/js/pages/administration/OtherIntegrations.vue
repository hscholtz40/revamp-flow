<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    maps_map_id: string;
    has_maps_api_key: boolean;
    has_openai_api_key: boolean;
    ai_enabled: boolean;
    ai_admin_only: boolean;
    ai_prompt_logging_enabled: boolean;
    ai_daily_user_limit: number;
    ai_daily_company_limit: number;
}>();

const form = useForm({
    maps_api_key: '',
    maps_map_id: props.maps_map_id || '',
    clear_maps_api_key: false,
    openai_api_key: '',
    clear_openai_api_key: false,
    ai_enabled: props.ai_enabled,
    ai_admin_only: props.ai_admin_only,
    ai_prompt_logging_enabled: props.ai_prompt_logging_enabled,
    ai_daily_user_limit: props.ai_daily_user_limit || 50,
    ai_daily_company_limit: props.ai_daily_company_limit || 500,
});

const submit = () => {
    form.put('/administration/other-integrations', { preserveScroll: true });
};
</script>

<template>
    <Head title="Other Integrations" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Administration', href: '/administration' },
            { title: 'Other Integrations', href: '#' },
        ]"
    >
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Other Integrations</h1>
                <p class="text-gray-600">Global integration credentials and AI runtime controls. Secret keys are encrypted at rest.</p>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <form class="max-w-2xl space-y-8" @submit.prevent="submit">
                    <div class="space-y-6">
                        <h2 class="text-lg font-semibold text-gray-900">Google Maps</h2>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Maps JavaScript API key</label>
                            <p v-if="has_maps_api_key" class="mb-2 text-sm text-gray-600">
                                A key is already saved. Enter a new key to replace it, or remove it below.
                            </p>
                            <input
                                v-model="form.maps_api_key"
                                type="password"
                                autocomplete="off"
                                class="w-full rounded border px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Paste API key"
                            />
                            <p v-if="form.errors.maps_api_key" class="mt-1 text-sm text-red-600">{{ form.errors.maps_api_key }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.clear_maps_api_key" type="checkbox" class="rounded border-gray-300" />
                            Remove stored Maps API key
                        </label>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">Map ID (Advanced Markers)</label>
                            <input
                                v-model="form.maps_map_id"
                                type="text"
                                class="w-full rounded border px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. abc123def456"
                            />
                            <p v-if="form.errors.maps_map_id" class="mt-1 text-sm text-red-600">{{ form.errors.maps_map_id }}</p>
                        </div>
                    </div>

                    <div class="space-y-6 border-t border-gray-200 pt-6">
                        <h2 class="text-lg font-semibold text-gray-900">OpenAI</h2>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700">OpenAI API key</label>
                            <p v-if="has_openai_api_key" class="mb-2 text-sm text-gray-600">
                                A key is already saved. Enter a new key to replace it, or remove it below.
                            </p>
                            <input
                                v-model="form.openai_api_key"
                                type="password"
                                autocomplete="off"
                                class="w-full rounded border px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="sk-..."
                            />
                            <p v-if="form.errors.openai_api_key" class="mt-1 text-sm text-red-600">{{ form.errors.openai_api_key }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.clear_openai_api_key" type="checkbox" class="rounded border-gray-300" />
                            Remove stored OpenAI API key
                        </label>

                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.ai_enabled" type="checkbox" class="rounded border-gray-300" />
                                Enable AI features
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.ai_admin_only" type="checkbox" class="rounded border-gray-300" />
                                Admin-only AI access
                            </label>
                            <label class="flex items-center gap-2 text-sm text-gray-700 md:col-span-2">
                                <input v-model="form.ai_prompt_logging_enabled" type="checkbox" class="rounded border-gray-300" />
                                Enable prompt/response audit logging (redacted)
                            </label>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Daily user AI request limit</label>
                                <input
                                    v-model.number="form.ai_daily_user_limit"
                                    type="number"
                                    min="1"
                                    max="10000"
                                    class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.ai_daily_user_limit" class="mt-1 text-sm text-red-600">{{ form.errors.ai_daily_user_limit }}</p>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700">Daily company AI request limit</label>
                                <input
                                    v-model.number="form.ai_daily_company_limit"
                                    type="number"
                                    min="1"
                                    max="100000"
                                    class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                                <p v-if="form.errors.ai_daily_company_limit" class="mt-1 text-sm text-red-600">{{ form.errors.ai_daily_company_limit }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            class="rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Saving…' : 'Save' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
