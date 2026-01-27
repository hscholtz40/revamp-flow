<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Percent, Calendar } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface TaxRate {
    id: number;
    name: string;
    code: string | null;
    rate: number;
    description: string | null;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    taxRate: TaxRate;
}

const props = defineProps<Props>();

function deleteTaxRate() {
    if (confirm(`Are you sure you want to delete "${props.taxRate.name}"?`)) {
        router.delete(administration.taxRates.destroy(props.taxRate.id).url);
    }
}
</script>

<template>
    <Head :title="props.taxRate.name" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Tax Rates', href: administration.taxRates.index().url },
        { title: props.taxRate.name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="administration.taxRates.index().url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Tax Rates
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="administration.taxRates.edit(props.taxRate.id).url"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        @click="deleteTaxRate"
                        class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <!-- Tax Rate Header -->
                <div class="mb-8 rounded-lg border bg-white p-6">
                    <div class="flex items-start gap-6">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-blue-100">
                                <Percent class="h-8 w-8 text-blue-600" />
                            </div>
                        </div>

                        <!-- Tax Rate Info -->
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-gray-900">{{ props.taxRate.name }}</h1>
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        props.taxRate.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.taxRate.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            
                            <p v-if="props.taxRate.description" class="text-gray-600">
                                {{ props.taxRate.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tax Rate Details Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center gap-3">
                                    <Percent class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Name</div>
                                        <div class="text-gray-900">{{ props.taxRate.name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Percent class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Code</div>
                                        <div class="text-gray-900">{{ props.taxRate.code || '—' }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Percent class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Rate</div>
                                        <div class="text-lg font-semibold text-gray-900">{{ props.taxRate.rate }}%</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="props.taxRate.description" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                            <div class="text-gray-700 whitespace-pre-wrap">{{ props.taxRate.description }}</div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Quick Stats</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.taxRate.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.taxRate.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Rate</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.taxRate.rate }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Timestamps</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Created</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.taxRate.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.taxRate.updated_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
