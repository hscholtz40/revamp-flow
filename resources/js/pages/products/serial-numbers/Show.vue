<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Hash } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
}

interface SerialNumber {
    id: number;
    serial_number: string;
    batch_id: number | null;
    batch: {
        batch_number: string;
    } | null;
    status: 'available' | 'sold' | 'returned' | 'damaged' | 'scrapped';
    invoice_id: number | null;
    jobcard_id: number | null;
    purchase_date: string | null;
    sale_date: string | null;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    product: Product;
    serialNumber: SerialNumber;
}

const props = defineProps<Props>();

const form = useForm({
    batch_id: props.serialNumber.batch_id,
    status: props.serialNumber.status,
    notes: props.serialNumber.notes || '',
});

function submit() {
    form.put(`/products/${props.product.id}/serial-numbers/${props.serialNumber.id}`);
}

function deleteSerialNumber() {
    if (confirm('Are you sure you want to delete this serial number?')) {
        router.delete(`/products/${props.product.id}/serial-numbers/${props.serialNumber.id}`);
    }
}

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        available: 'bg-green-100 text-green-800',
        sold: 'bg-blue-100 text-blue-800',
        returned: 'bg-yellow-100 text-yellow-800',
        damaged: 'bg-orange-100 text-orange-800',
        scrapped: 'bg-red-100 text-red-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head :title="`Serial Number ${props.serialNumber.serial_number} - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Serial Numbers', href: `/products/${props.product.id}/serial-numbers` },
        { title: props.serialNumber.serial_number, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <Link
                    :href="`/products/${props.product.id}/serial-numbers`"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Serial Numbers
                </Link>
                <button
                    @click="deleteSerialNumber"
                    class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                >
                    <Trash2 class="h-4 w-4" />
                    Delete
                </button>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Serial Number: {{ props.serialNumber.serial_number }}</h1>
                    <p class="text-gray-600">Product: {{ props.product.name }}</p>
                </div>

                <div class="space-y-6">
                    <!-- Serial Number Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Serial Number Information</h2>
                        
                        <form @submit.prevent="submit" class="space-y-6">
                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <div class="text-sm font-medium text-gray-500">Serial Number</div>
                                    <div class="mt-1 text-lg text-gray-900">{{ props.serialNumber.serial_number }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Status *
                                    </label>
                                    <select
                                        v-model="form.status"
                                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    >
                                        <option value="available">Available</option>
                                        <option value="sold">Sold</option>
                                        <option value="returned">Returned</option>
                                        <option value="damaged">Damaged</option>
                                        <option value="scrapped">Scrapped</option>
                                    </select>
                                    <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                                        {{ form.errors.status }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-sm font-medium text-gray-500">Batch</div>
                                    <div class="mt-1 text-lg text-gray-900">
                                        {{ props.serialNumber.batch ? props.serialNumber.batch.batch_number : 'N/A' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-sm font-medium text-gray-500">Purchase Date</div>
                                    <div class="mt-1 text-lg text-gray-900">
                                        {{ props.serialNumber.purchase_date ? new Date(props.serialNumber.purchase_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-sm font-medium text-gray-500">Sale Date</div>
                                    <div class="mt-1 text-lg text-gray-900">
                                        {{ props.serialNumber.sale_date ? new Date(props.serialNumber.sale_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                </div>
                            </div>

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

                            <div class="flex items-center justify-end gap-4">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Updating...' : 'Update Serial Number' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

