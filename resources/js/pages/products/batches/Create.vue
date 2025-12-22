<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
}

interface Props {
    product: Product;
}

const props = defineProps<Props>();

const form = useForm({
    batch_number: '',
    manufacture_date: '',
    expiry_date: '',
    quantity: 0,
    unit_cost: null as number | null,
    notes: '',
});

function submit() {
    form.post(`/products/${props.product.id}/batches`);
}
</script>

<template>
    <Head :title="`New Batch - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Batches', href: `/products/${props.product.id}/batches` },
        { title: 'Create', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="`/products/${props.product.id}/batches`"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Batches
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Create New Batch</h1>
                    <p class="text-gray-600">Add a new batch for {{ props.product.name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Batch Information</h2>
                        
                        <div class="space-y-4">
                            <!-- Batch Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Batch Number *
                                </label>
                                <input
                                    v-model="form.batch_number"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    placeholder="e.g., BATCH-2025-001"
                                />
                                <div v-if="form.errors.batch_number" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.batch_number }}
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Quantity *
                                </label>
                                <input
                                    v-model.number="form.quantity"
                                    type="number"
                                    min="0"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.quantity }}
                                </div>
                            </div>

                            <!-- Unit Cost -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Unit Cost
                                </label>
                                <div class="mt-1 relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">R</span>
                                    </div>
                                    <input
                                        v-model.number="form.unit_cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full pl-7 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />
                                </div>
                                <div v-if="form.errors.unit_cost" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.unit_cost }}
                                </div>
                            </div>

                            <!-- Manufacture Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Manufacture Date
                                </label>
                                <input
                                    v-model="form.manufacture_date"
                                    type="date"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.manufacture_date" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.manufacture_date }}
                                </div>
                            </div>

                            <!-- Expiry Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Expiry Date
                                </label>
                                <input
                                    v-model="form.expiry_date"
                                    type="date"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.expiry_date" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.expiry_date }}
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Used for expiry tracking and alerts</p>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Notes
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.notes }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="`/products/${props.product.id}/batches`"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Batch' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

