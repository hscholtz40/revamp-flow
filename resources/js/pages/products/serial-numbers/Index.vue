<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Hash, Package } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
    track_serial_numbers: boolean;
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
    serialNumbers: SerialNumber[];
}

const props = defineProps<Props>();

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
    <Head :title="`Serial Numbers - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Serial Numbers', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="products.show(props.product.id).url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Product
                    </Link>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Serial Numbers for {{ props.product.name }}</h1>
                        <p class="text-gray-600">Manage product serial numbers</p>
                    </div>
                </div>
                <Link
                    :href="`/products/${props.product.id}/serial-numbers/create`"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Serial Numbers
                </Link>
            </div>

            <!-- Serial Numbers Table -->
            <div v-if="props.serialNumbers.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Hash class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No serial numbers found</h3>
                <p class="mt-1 text-gray-500">Add serial numbers to start tracking individual items.</p>
                <div class="mt-6">
                    <Link
                        :href="`/products/${props.product.id}/serial-numbers/create`"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Serial Numbers
                    </Link>
                </div>
            </div>

            <div v-else class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Serial Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Batch
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Purchase Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Sale Date
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="serial in props.serialNumbers" :key="serial.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ serial.serial_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ serial.batch ? serial.batch.batch_number : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                            getStatusColor(serial.status)
                                        ]"
                                    >
                                        {{ serial.status.charAt(0).toUpperCase() + serial.status.slice(1) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ serial.purchase_date ? new Date(serial.purchase_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ serial.sale_date ? new Date(serial.sale_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link
                                        :href="`/products/${props.product.id}/serial-numbers/${serial.id}`"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        View
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

