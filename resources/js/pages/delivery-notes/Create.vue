<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import deliveryNotes from '@/routes/delivery-notes';
import jobcards from '@/routes/jobcards';

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface LineItem {
    product_id: string | number;
    line_group_id?: number | null;
    quantity: number;
    description: string;
}

interface LineGroup {
    name: string;
    sort_order: number;
}

interface Props {
    jobcard: {
        id: number;
        job_number: string;
        title: string;
        customer: { id: number; name: string } | null;
    };
    prefill: {
        jobcard_id: number;
        delivery_address?: string | null;
        line_groups: LineGroup[];
        items: Array<{
            product_id: number | null;
            line_group_id?: number | null;
            quantity: number;
            description: string;
        }>;
    };
    products?: Product[];
}

const props = withDefaults(defineProps<Props>(), {
    products: () => [],
});

const form = useForm({
    jobcard_id: props.prefill.jobcard_id,
    delivery_date: new Date().toISOString().split('T')[0],
    delivery_address: props.prefill.delivery_address ?? '',
    notes: '',
    line_groups: (props.prefill.line_groups.length > 0
        ? props.prefill.line_groups
        : [{ name: 'Items', sort_order: 0 }]) as LineGroup[],
    items: props.prefill.items.map((item) => ({
        product_id: item.product_id ?? '',
        line_group_id: item.line_group_id ?? 1,
        quantity: Number(item.quantity) || 1,
        description: item.description || '',
    })) as LineItem[],
});

const groupedItems = computed(() => {
    return form.line_groups.map((group, index) => ({
        groupId: index + 1,
        groupName: group.name || 'Items',
        items: form.items.filter((item) => (item.line_group_id ?? 1) === index + 1),
    }));
});

const addLineItem = (groupId: number) => {
    form.items.push({
        product_id: '',
        line_group_id: groupId,
        quantity: 1,
        description: '',
    });
};

const removeLineItem = (index: number) => {
    if (form.items.length <= 1) {
        return;
    }
    form.items.splice(index, 1);
};

const onProductChange = (index: number) => {
    const item = form.items[index];
    const productId = Number(item.product_id);
    if (!productId) {
        return;
    }
    const product = props.products.find((p) => p.id === productId);
    if (product && !item.description) {
        item.description = product.name;
    }
};

const submit = () => {
    form.post(deliveryNotes.store().url);
};
</script>

<template>
    <Head title="Create Delivery Note" />

    <AppLayout :breadcrumbs="[
        { title: 'Jobcards', href: jobcards.index().url },
        { title: props.jobcard.job_number, href: jobcards.show(props.jobcard.id).url },
        { title: 'Create Delivery Note', href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Create Delivery Note</h1>
                    <p class="text-sm text-gray-600">
                        From jobcard {{ props.jobcard.job_number }}
                        <span v-if="props.jobcard.customer"> — {{ props.jobcard.customer.name }}</span>
                    </p>
                </div>
                <Link
                    :href="jobcards.show(props.jobcard.id).url"
                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Jobcard
                </Link>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Delivery Details</h2>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Delivery Date</label>
                            <input v-model="form.delivery_date" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2" />
                            <p v-if="form.errors.delivery_date" class="mt-1 text-sm text-red-600">{{ form.errors.delivery_date }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Delivery Address</label>
                            <textarea v-model="form.delivery_address" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2" />
                            <p v-if="form.errors.delivery_address" class="mt-1 text-sm text-red-600">{{ form.errors.delivery_address }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                        <textarea v-model="form.notes" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2" />
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Items to Deliver</h2>

                    <div v-for="group in groupedItems" :key="group.groupId" class="mb-6">
                        <h3 class="mb-2 text-sm font-semibold text-gray-700">{{ group.groupName }}</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Qty</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="(item, itemIndex) in form.items.filter(i => (i.line_group_id ?? 1) === group.groupId)" :key="`${group.groupId}-${itemIndex}`">
                                        <td class="px-3 py-2">
                                            <select
                                                v-model="item.product_id"
                                                class="w-full rounded border border-gray-300 px-2 py-1 text-sm"
                                                @change="onProductChange(form.items.indexOf(item))"
                                            >
                                                <option value="">— None —</option>
                                                <option v-for="product in props.products" :key="product.id" :value="product.id">
                                                    {{ product.name }}<span v-if="product.sku"> ({{ product.sku }})</span>
                                                </option>
                                            </select>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model="item.description" type="text" class="w-full rounded border border-gray-300 px-2 py-1 text-sm" required />
                                        </td>
                                        <td class="px-3 py-2">
                                            <input v-model.number="item.quantity" type="number" min="1" class="w-20 rounded border border-gray-300 px-2 py-1 text-sm" required />
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" class="text-sm text-red-600 hover:text-red-800" @click="removeLineItem(form.items.indexOf(item))">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="mt-2 text-sm text-blue-600 hover:text-blue-800" @click="addLineItem(group.groupId)">
                            + Add line
                        </button>
                    </div>

                    <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="jobcards.show(props.jobcard.id).url" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50">
                        Create Delivery Note
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
