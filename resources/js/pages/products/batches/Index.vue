<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Calendar, Package, AlertTriangle, Eye, Edit } from 'lucide-vue-next';
import products from '@/routes/products';

interface Product {
    id: number;
    name: string;
    track_batches: boolean;
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
    batches: Batch[];
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
</script>

<template>
    <Head :title="`Batches - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Batches', href: '#' }
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
                        <h1 class="text-2xl font-bold text-gray-900">Batches for {{ props.product.name }}</h1>
                        <p class="text-gray-600">Manage product batches and lot numbers</p>
                    </div>
                </div>
                <Link
                    :href="`/products/${props.product.id}/batches/create`"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Batch
                </Link>
            </div>

            <!-- Batches Table -->
            <div v-if="props.batches.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Package class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No batches found</h3>
                <p class="mt-1 text-gray-500">Create your first batch to start tracking.</p>
                <div class="mt-6">
                    <Link
                        :href="`/products/${props.product.id}/batches/create`"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Batch
                    </Link>
                </div>
            </div>

            <div v-else class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Batch Number
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit Cost
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Manufacture Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Expiry Date
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="batch in props.batches" :key="batch.id">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ batch.batch_number }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ batch.quantity }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ batch.unit_cost ? 'R' + parseFloat(batch.unit_cost).toFixed(2) : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        {{ batch.manufacture_date ? new Date(batch.manufacture_date).toLocaleDateString() : 'N/A' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm text-gray-900">
                                            {{ batch.expiry_date ? new Date(batch.expiry_date).toLocaleDateString() : 'N/A' }}
                                        </div>
                                        <AlertTriangle
                                            v-if="batch.expiry_date && (isExpired(batch.expiry_date) || isExpiringSoon(batch.expiry_date))"
                                            :class="[
                                                'h-4 w-4',
                                                isExpired(batch.expiry_date) ? 'text-red-500' : 'text-yellow-500'
                                            ]"
                                        />
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        v-if="batch.expiry_date"
                                        :class="[
                                            'inline-flex items-center rounded-full px-2 py-1 text-xs font-medium',
                                            isExpired(batch.expiry_date) ? 'bg-red-100 text-red-800' :
                                            isExpiringSoon(batch.expiry_date) ? 'bg-yellow-100 text-yellow-800' :
                                            'bg-green-100 text-green-800'
                                        ]"
                                    >
                                        {{ isExpired(batch.expiry_date) ? 'Expired' :
                                            isExpiringSoon(batch.expiry_date) ? 'Expiring Soon' : 'Valid' }}
                                    </span>
                                    <span v-else class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800">
                                        No Expiry
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <Link
                                        :href="`/products/${props.product.id}/batches/${batch.id}`"
                                        class="mr-2 inline-flex items-center justify-center rounded-md p-2 text-blue-600 hover:bg-blue-50 md:mr-4 md:p-0 md:hover:bg-transparent hover:text-blue-900"
                                    >
                                        <ListTableActionLabel label="View">
                                            <Eye class="h-4 w-4" />
                                        </ListTableActionLabel>
                                    </Link>
                                    <Link
                                        :href="`/products/${props.product.id}/batches/${batch.id}/edit`"
                                        class="inline-flex items-center justify-center rounded-md p-2 text-gray-600 hover:bg-gray-100 md:p-0 md:hover:bg-transparent hover:text-gray-900"
                                    >
                                        <ListTableActionLabel label="Edit">
                                            <Edit class="h-4 w-4" />
                                        </ListTableActionLabel>
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

