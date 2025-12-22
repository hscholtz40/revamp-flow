<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Calendar, Package, AlertTriangle } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
}

interface Batch {
    id: number;
    batch_number: string;
    manufacture_date: string | null;
    expiry_date: string | null;
    quantity: number;
    unit_cost: number | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    product: Product;
    batch: Batch;
}

const props = defineProps<Props>();

function isExpired(expiryDate: string | null): boolean {
    if (!expiryDate) return false;
    return new Date(expiryDate) < new Date();
}

function isExpiringSoon(expiryDate: string | null, days: number = 30): boolean {
    if (!expiryDate) return false;
    const expiry = new Date(expiryDate);
    const now = new Date();
    const diffDays = Math.ceil((expiry.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
    return diffDays > 0 && diffDays <= days;
}

function deleteBatch() {
    if (confirm('Are you sure you want to delete this batch?')) {
        router.delete(`/products/${props.product.id}/batches/${props.batch.id}`);
    }
}
</script>

<template>
    <Head :title="`Batch ${props.batch.batch_number} - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Batches', href: `/products/${props.product.id}/batches` },
        { title: props.batch.batch_number, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <Link
                    :href="`/products/${props.product.id}/batches`"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Batches
                </Link>
                <div class="flex items-center gap-2">
                    <Link
                        :href="`/products/${props.product.id}/batches/${props.batch.id}/edit`"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        @click="deleteBatch"
                        class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Batch: {{ props.batch.batch_number }}</h1>
                    <p class="text-gray-600">Product: {{ props.product.name }}</p>
                </div>

                <div class="space-y-6">
                    <!-- Batch Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Batch Information</h2>
                        
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <div class="text-sm font-medium text-gray-500">Batch Number</div>
                                <div class="mt-1 text-lg text-gray-900">{{ props.batch.batch_number }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-gray-500">Quantity</div>
                                <div class="mt-1 text-lg text-gray-900">{{ props.batch.quantity }}</div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-gray-500">Unit Cost</div>
                                <div class="mt-1 text-lg text-gray-900">
                                    {{ props.batch.unit_cost ? 'R' + parseFloat(props.batch.unit_cost.toString()).toFixed(2) : 'N/A' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-gray-500">Manufacture Date</div>
                                <div class="mt-1 text-lg text-gray-900">
                                    {{ props.batch.manufacture_date ? new Date(props.batch.manufacture_date).toLocaleDateString() : 'N/A' }}
                                </div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-gray-500">Expiry Date</div>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="text-lg text-gray-900">
                                        {{ props.batch.expiry_date ? new Date(props.batch.expiry_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                    <AlertTriangle
                                        v-if="props.batch.expiry_date && (isExpired(props.batch.expiry_date) || isExpiringSoon(props.batch.expiry_date))"
                                        :class="[
                                            'h-5 w-5',
                                            isExpired(props.batch.expiry_date) ? 'text-red-500' : 'text-yellow-500'
                                        ]"
                                    />
                                </div>
                            </div>

                            <div>
                                <div class="text-sm font-medium text-gray-500">Status</div>
                                <div class="mt-1">
                                    <span
                                        v-if="props.batch.expiry_date"
                                        :class="[
                                            'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium',
                                            isExpired(props.batch.expiry_date) ? 'bg-red-100 text-red-800' :
                                            isExpiringSoon(props.batch.expiry_date) ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-green-100 text-green-800'
                                        ]"
                                    >
                                        {{ isExpired(props.batch.expiry_date) ? 'Expired' :
                                            isExpiringSoon(props.batch.expiry_date) ? 'Expiring Soon' : 'Valid' }}
                                    </span>
                                    <span v-else class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium bg-gray-100 text-gray-800">
                                        No Expiry
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-if="props.batch.notes" class="mt-6">
                            <div class="text-sm font-medium text-gray-500">Notes</div>
                            <div class="mt-1 text-gray-900 whitespace-pre-wrap bg-gray-50 p-4 rounded-md">{{ props.batch.notes }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

