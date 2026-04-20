<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    maps_map_id: string;
    has_maps_api_key: boolean;
}>();

const form = useForm({
    maps_api_key: '',
    maps_map_id: props.maps_map_id || '',
    clear_maps_api_key: false,
});

const submit = () => {
    form.put('/administration/google-integration', { preserveScroll: true });
};
</script>

<template>
    <Head title="Google Integration" />

    <AppLayout
        :breadcrumbs="[
            { title: 'Administration', href: '/administration' },
            { title: 'Google Integration', href: '#' },
        ]"
    >
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Google Integration</h1>
                <p class="text-gray-600">
                    Instance-wide Maps JavaScript API credentials for Dispatch (maps, geocoding, ETA) and server-side route optimization. Values are stored encrypted.
                </p>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <form class="max-w-2xl space-y-6" @submit.prevent="submit">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Maps JavaScript API key</label>
                        <p v-if="has_maps_api_key" class="mb-2 text-sm text-gray-600">
                            A key is already saved. Enter a new key to replace it, or use the checkbox below to remove it.
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
                        Remove stored API key
                    </label>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700">Map ID (Advanced Markers)</label>
                        <p class="mb-2 text-sm text-gray-600">
                            From Google Cloud Console (Maps Management → Map IDs). Leave empty to use the default from server configuration
                            (<code class="rounded bg-gray-100 px-1">GOOGLE_MAPS_MAP_ID</code> / demo ID).
                        </p>
                        <input
                            v-model="form.maps_map_id"
                            type="text"
                            class="w-full rounded border px-3 py-2 font-mono text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g. abc123def456"
                        />
                        <p v-if="form.errors.maps_map_id" class="mt-1 text-sm text-red-600">{{ form.errors.maps_map_id }}</p>
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
