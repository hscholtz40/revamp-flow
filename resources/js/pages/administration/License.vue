<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import administration from '@/routes/administration';

const props = defineProps<{
    license: {
        license_key: string | null;
        status: string;
        message: string | null;
        licensed_url: string | null;
        limited_users: number | null;
        standard_users: number | null;
        last_validated_at: string | null;
        valid: boolean;
        validation_message: string;
    };
    canManageLicense: boolean;
}>();

const form = useForm({
    license_key: props.license.license_key ?? '',
});

const submit = () => {
    form.post('/administration/license');
};
</script>

<template>
    <Head title="License" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'License', href: '/administration/license' }
    ]">
        <div class="p-4">
            <div v-if="$page.props.flash?.success" class="mb-4 rounded-lg border border-green-400 bg-green-100 px-4 py-3 text-green-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg border border-red-400 bg-red-100 px-4 py-3 text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <h1 class="mb-2 text-2xl font-bold">Instance License</h1>
            <p class="mb-6 text-sm text-gray-600">
                This instance must have a valid license key linked to this URL before normal usage is allowed.
            </p>

            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="mb-4">
                    <p class="text-sm text-gray-600">Status</p>
                    <p :class="props.license.valid ? 'text-green-700 font-semibold' : 'text-red-700 font-semibold'">
                        {{ props.license.valid ? 'Valid' : 'Invalid' }}
                    </p>
                    <p class="mt-1 text-sm text-gray-600">{{ props.license.validation_message }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs uppercase text-gray-500">Licensed URL</p>
                        <p class="text-sm text-gray-800">{{ props.license.licensed_url || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Last Validated</p>
                        <p class="text-sm text-gray-800">{{ props.license.last_validated_at || '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Standard Users</p>
                        <p class="text-sm text-gray-800">{{ props.license.standard_users ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-gray-500">Limited Users</p>
                        <p class="text-sm text-gray-800">{{ props.license.limited_users ?? '-' }}</p>
                    </div>
                </div>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <div>
                        <label for="license_key" class="mb-1 block text-sm font-medium text-gray-700">License Key</label>
                        <input
                            id="license_key"
                            v-model="form.license_key"
                            type="text"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                            placeholder="XXXX-XXXX-XXXX-XXXX-XXXX"
                            :disabled="!props.canManageLicense || form.processing"
                        />
                        <p v-if="form.errors.license_key" class="mt-1 text-sm text-red-600">{{ form.errors.license_key }}</p>
                    </div>

                    <div class="flex items-center gap-3">
                        <button
                            type="submit"
                            class="rounded bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="!props.canManageLicense || form.processing"
                        >
                            <span v-if="form.processing">Validating...</span>
                            <span v-else>Save & Validate</span>
                        </button>
                        <p v-if="!props.canManageLicense" class="text-sm text-gray-600">
                            Only administrators can update the license key.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
