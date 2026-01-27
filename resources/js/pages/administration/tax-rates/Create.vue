<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import administration from '@/routes/administration';

const form = useForm({
    name: '',
    code: '',
    rate: 0,
    description: '',
    is_active: true,
    is_default: false,
});

function submit() {
    form.post(administration.taxRates.store().url);
}
</script>

<template>
    <Head title="Create Tax Rate" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Tax Rates', href: administration.taxRates.index().url },
        { title: 'Create', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="administration.taxRates.index().url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Tax Rates
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Create New Tax Rate</h1>
                    <p class="text-gray-600">Add a new tax rate for use in invoices, quotes, and jobcards</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        
                        <div class="space-y-6">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Name *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="e.g., VAT, Sales Tax"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Code
                                </label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    placeholder="e.g., VAT15, TAX20"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-sm text-gray-500">Optional code for reference</p>
                                <div v-if="form.errors.code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.code }}
                                </div>
                            </div>

                            <!-- Rate -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Rate (%) *
                                </label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input
                                        v-model="form.rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        required
                                        placeholder="15.00"
                                        class="block w-full rounded-l-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                    <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-gray-500">
                                        %
                                    </span>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Tax rate as a percentage (e.g., 15 for 15%)</p>
                                <div v-if="form.errors.rate" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.rate }}
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    placeholder="Optional description for this tax rate"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        
                        <div class="space-y-6">
                            <!-- Active Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for selection)</span>
                                </label>
                            </div>

                            <!-- Default Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Set as Default Tax Rate</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500">This tax rate will be used as the default when syncing invoices and quotes to Xero</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="administration.taxRates.index().url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Tax Rate' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
