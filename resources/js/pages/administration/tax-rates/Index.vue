<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Eye, Percent } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface TaxRate {
    id: number;
    name: string;
    code: string | null;
    rate: number;
    description: string | null;
    is_active: boolean;
    is_default_sales: boolean;
    is_default_purchasing: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    taxRates: TaxRate[];
}

const props = defineProps<Props>();

function deleteTaxRate(taxRate: TaxRate) {
    if (confirm(`Are you sure you want to delete "${taxRate.name}"?`)) {
        router.delete(administration.taxRates.destroy(taxRate.id).url);
    }
}
</script>

<template>
    <Head title="Tax Rate Management" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Tax Rates', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Tax Rate Management</h1>
                    <p class="text-gray-600">Manage tax rates for invoices, quotes, and jobcards</p>
                </div>
                <Link
                    :href="administration.taxRates.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Tax Rate
                </Link>
            </div>

            <!-- Tax Rates List -->
            <div v-if="props.taxRates.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Percent class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No tax rates found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first tax rate.</p>
                <div class="mt-6">
                    <Link
                        :href="administration.taxRates.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Tax Rate
                    </Link>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Rate</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="taxRate in props.taxRates"
                            :key="taxRate.id"
                            class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            tabindex="0"
                            role="link"
                            @click="router.visit(administration.taxRates.show(taxRate.id).url)"
                            @keydown.enter.prevent="router.visit(administration.taxRates.show(taxRate.id).url)"
                            @keydown.space.prevent="router.visit(administration.taxRates.show(taxRate.id).url)"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center">
                                    <Percent class="mr-2 h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900">{{ taxRate.name }}</span>
                                            <span
                                                v-if="taxRate.is_default_sales"
                                                class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-800"
                                            >
                                                Default Sales
                                            </span>
                                            <span
                                                v-if="taxRate.is_default_purchasing"
                                                class="inline-flex rounded-full bg-purple-100 px-2 py-0.5 text-xs font-semibold text-purple-800"
                                            >
                                                Default Purchasing
                                            </span>
                                        </div>
                                        <div v-if="taxRate.description" class="text-sm text-gray-500 line-clamp-1">
                                            {{ taxRate.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ taxRate.code || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                {{ taxRate.rate }}%
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                        taxRate.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ taxRate.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="administration.taxRates.edit(taxRate.id).url"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteTaxRate(taxRate)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
