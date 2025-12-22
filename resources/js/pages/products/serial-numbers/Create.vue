<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, X } from 'lucide-vue-next';
import products from '@/routes/products';
import { ref } from 'vue';

interface Product {
    id: number;
    name: string;
}

interface Batch {
    id: number;
    batch_number: string;
}

interface Props {
    product: Product;
    batches: Batch[];
}

const props = defineProps<Props>();

const serialNumberInput = ref('');
const serialNumbers = ref<string[]>([]);

const form = useForm({
    serial_numbers: [] as string[],
    batch_id: null as number | null,
    purchase_date: '',
    notes: '',
});

function addSerialNumber() {
    if (serialNumberInput.value.trim() && !serialNumbers.value.includes(serialNumberInput.value.trim())) {
        serialNumbers.value.push(serialNumberInput.value.trim());
        serialNumberInput.value = '';
    }
}

function removeSerialNumber(index: number) {
    serialNumbers.value.splice(index, 1);
}

function submit() {
    if (serialNumbers.value.length === 0) {
        alert('Please add at least one serial number');
        return;
    }
    form.serial_numbers = serialNumbers.value;
    form.post(`/products/${props.product.id}/serial-numbers`);
}
</script>

<template>
    <Head :title="`New Serial Numbers - ${props.product.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Products', href: products.index().url },
        { title: props.product.name, href: products.show(props.product.id).url },
        { title: 'Serial Numbers', href: `/products/${props.product.id}/serial-numbers` },
        { title: 'Create', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="`/products/${props.product.id}/serial-numbers`"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Serial Numbers
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Add Serial Numbers</h1>
                    <p class="text-gray-600">Add serial numbers for {{ props.product.name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Serial Number Information</h2>
                        
                        <div class="space-y-4">
                            <!-- Serial Numbers -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Serial Numbers *
                                </label>
                                <div class="mt-1 flex gap-2">
                                    <input
                                        v-model="serialNumberInput"
                                        type="text"
                                        class="flex-1 rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        placeholder="Enter serial number"
                                        @keyup.enter.prevent="addSerialNumber"
                                    />
                                    <button
                                        type="button"
                                        @click="addSerialNumber"
                                        class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">Press Enter or click + to add each serial number</p>
                                
                                <!-- Serial Numbers List -->
                                <div v-if="serialNumbers.length > 0" class="mt-3 space-y-2">
                                    <div
                                        v-for="(serial, index) in serialNumbers"
                                        :key="index"
                                        class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2"
                                    >
                                        <span class="text-sm text-gray-900">{{ serial }}</span>
                                        <button
                                            type="button"
                                            @click="removeSerialNumber(index)"
                                            class="text-red-600 hover:text-red-800"
                                        >
                                            <X class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div v-if="form.errors.serial_numbers" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.serial_numbers }}
                                </div>
                            </div>

                            <!-- Batch -->
                            <div v-if="props.batches.length > 0">
                                <label class="block text-sm font-medium text-gray-700">
                                    Batch (Optional)
                                </label>
                                <select
                                    v-model.number="form.batch_id"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option :value="null">No Batch</option>
                                    <option v-for="batch in props.batches" :key="batch.id" :value="batch.id">
                                        {{ batch.batch_number }}
                                    </option>
                                </select>
                                <div v-if="form.errors.batch_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.batch_id }}
                                </div>
                            </div>

                            <!-- Purchase Date -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Purchase Date
                                </label>
                                <input
                                    v-model="form.purchase_date"
                                    type="date"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.purchase_date" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.purchase_date }}
                                </div>
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
                            :href="`/products/${props.product.id}/serial-numbers`"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing || serialNumbers.length === 0"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : `Create ${serialNumbers.length} Serial Number(s)` }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

