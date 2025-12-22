<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import stockMovements from '@/routes/stock-movements';

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    stock_quantity: number;
    track_serial_numbers: boolean;
    serialNumbers?: SerialNumber[];
}

interface Company {
    id: number;
    name: string;
}

interface Props {
    product_id?: number;
    products: Product[];
    companies?: Company[];
    currentCompany?: Company;
}

const props = withDefaults(defineProps<Props>(), {
    companies: () => [],
    currentCompany: undefined,
});

const form = useForm({
    product_id: props.product_id || '',
    type: 'in',
    quantity: 1,
    unit_cost: null as number | null,
    to_company_id: null as number | null,
    serial_number_ids: [] as number[],
    reference: '',
    reference_type: '',
    reference_id: null as number | null,
    notes: '',
});

function submit() {
    form.post(stockMovements.store().url);
}

const selectedProduct = computed(() => {
    if (!form.product_id) return null;
    return props.products.find(p => p.id === parseInt(form.product_id.toString()));
});

// Reset serial numbers when product or quantity changes
watch([() => form.product_id, () => form.quantity, () => form.type], () => {
    form.serial_number_ids = [];
});

// Watch for type change to reset to_company_id
watch(() => form.type, (newType) => {
    if (newType !== 'transfer') {
        form.to_company_id = null;
    }
});
</script>

<template>
    <Head title="Record Stock Movement" />
    <AppLayout :breadcrumbs="[
        { title: 'Stock Movements', href: stockMovements.index().url },
        { title: 'Record Movement', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="stockMovements.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Stock Movements
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Record Stock Movement</h1>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Product Selection -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Product <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.product_id"
                            required
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.product_id }"
                        >
                            <option value="">Select a product</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }} {{ product.sku ? `(${product.sku})` : '' }} - Current Stock: {{ product.stock_quantity }}
                            </option>
                        </select>
                        <div v-if="form.errors.product_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.product_id }}
                        </div>
                        <div v-if="selectedProduct" class="mt-2 text-sm text-gray-500">
                            Current stock: <span class="font-medium">{{ selectedProduct.stock_quantity }}</span>
                        </div>
                    </div>

                    <!-- Movement Type -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Movement Type <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.type"
                            required
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="in">Stock In (Add)</option>
                            <option value="out">Stock Out (Remove)</option>
                            <option value="adjustment">Adjustment (Set to specific level)</option>
                            <option value="transfer">Transfer (Move to another company)</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            <span v-if="form.type === 'adjustment'">Enter the new stock level (not the change amount)</span>
                            <span v-else-if="form.type === 'transfer'">Transfer stock to another company</span>
                            <span v-else>Enter the quantity to add or remove</span>
                        </p>
                    </div>

                    <!-- Destination Company (for transfers) -->
                    <div v-if="form.type === 'transfer'">
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Destination Company <span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.to_company_id"
                            required
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.to_company_id }"
                        >
                            <option value="">Select a company</option>
                            <option v-for="company in companies" :key="company.id" :value="company.id">
                                {{ company.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.to_company_id" class="mt-1 text-sm text-red-600">
                            {{ form.errors.to_company_id }}
                        </div>
                    </div>

                    <!-- Quantity -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            {{ form.type === 'adjustment' ? 'New Stock Level' : 'Quantity' }} <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model.number="form.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            :class="{ 'border-red-500': form.errors.quantity }"
                        />
                        <div v-if="form.errors.quantity" class="mt-1 text-sm text-red-600">
                            {{ form.errors.quantity }}
                        </div>
                    </div>

                    <!-- Unit Cost (for stock in) -->
                    <div v-if="form.type === 'in'">
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Unit Cost (Optional)
                        </label>
                        <input
                            v-model.number="form.unit_cost"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="0.00"
                        />
                    </div>

                    <!-- Serial Number Selection (for transfers and out movements with serial tracking) -->
                    <div v-if="selectedProduct && selectedProduct.track_serial_numbers && (form.type === 'transfer' || form.type === 'out')">
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Serial Numbers <span class="text-red-500">*</span>
                            <span class="text-xs font-normal text-gray-500 ml-2">
                                (Select {{ form.quantity }} {{ form.quantity === 1 ? 'serial number' : 'serial numbers' }})
                            </span>
                        </label>
                        <div v-if="selectedProduct.serialNumbers && selectedProduct.serialNumbers.length > 0" class="max-h-60 overflow-y-auto rounded border border-gray-300 p-3">
                            <div class="space-y-2">
                                <label
                                    v-for="serial in selectedProduct.serialNumbers"
                                    :key="serial.id"
                                    class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 cursor-pointer"
                                    :class="{ 'bg-blue-50': form.serial_number_ids.includes(serial.id) }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="serial.id"
                                        v-model="form.serial_number_ids"
                                        :disabled="form.serial_number_ids.length >= form.quantity && !form.serial_number_ids.includes(serial.id)"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-900">{{ serial.serial_number }}</span>
                                    <span class="text-xs text-gray-500">({{ serial.status }})</span>
                                </label>
                            </div>
                        </div>
                        <div v-else class="rounded border border-gray-300 p-3 text-sm text-gray-500">
                            No available serial numbers for this product.
                        </div>
                        <div v-if="form.errors.serial_number_ids" class="mt-1 text-sm text-red-600">
                            {{ form.errors.serial_number_ids }}
                        </div>
                        <div v-if="form.serial_number_ids.length > 0" class="mt-2 text-sm text-gray-600">
                            Selected: {{ form.serial_number_ids.length }} / {{ form.quantity }}
                        </div>
                    </div>

                    <!-- Reference -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Reference</label>
                        <input
                            v-model="form.reference"
                            type="text"
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., Purchase Order #123, Invoice #456"
                        />
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Additional notes about this movement..."
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 border-t pt-6">
                        <Link
                            :href="stockMovements.index().url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Recording...' : 'Record Movement' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

