<template>
    <AppLayout>
        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900">Document Numbering</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Company: {{ company.name }}. Set custom prefix and next number for each record type.
                </p>
            </div>

            <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-6">
                <div v-for="row in rows" :key="row.key" class="border rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-3">{{ row.label }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Prefix</label>
                            <input
                                v-model="form[row.prefixField]"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors[row.prefixField] }"
                                placeholder="Example: INV-"
                            />
                            <p class="text-xs text-gray-500 mt-1">Leave blank to use legacy numbering format.</p>
                            <div v-if="form.errors[row.prefixField]" class="text-red-500 text-sm mt-1">
                                {{ form.errors[row.prefixField] }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next number</label>
                            <input
                                v-model.number="form[row.nextField]"
                                type="number"
                                min="1"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors[row.nextField] }"
                                placeholder="Example: 1001"
                            />
                            <p class="text-xs text-gray-500 mt-1">Used when creating the next record of this type.</p>
                            <div v-if="form.errors[row.nextField]" class="text-red-500 text-sm mt-1">
                                {{ form.errors[row.nextField] }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save Numbering Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useForm } from '@inertiajs/vue3';

interface NumberingPayload {
    invoice_prefix: string | null;
    invoice_next: number | null;
    quote_prefix: string | null;
    quote_next: number | null;
    jobcard_prefix: string | null;
    jobcard_next: number | null;
    credit_note_prefix: string | null;
    credit_note_next: number | null;
    purchase_order_prefix: string | null;
    purchase_order_next: number | null;
}

const props = defineProps<{
    numbering: NumberingPayload;
    company: {
        id: number;
        name: string;
    };
}>();

const form = useForm<NumberingPayload>({
    invoice_prefix: props.numbering.invoice_prefix,
    invoice_next: props.numbering.invoice_next,
    quote_prefix: props.numbering.quote_prefix,
    quote_next: props.numbering.quote_next,
    jobcard_prefix: props.numbering.jobcard_prefix,
    jobcard_next: props.numbering.jobcard_next,
    credit_note_prefix: props.numbering.credit_note_prefix,
    credit_note_next: props.numbering.credit_note_next,
    purchase_order_prefix: props.numbering.purchase_order_prefix,
    purchase_order_next: props.numbering.purchase_order_next,
});

const rows = [
    { key: 'invoice', label: 'Invoices', prefixField: 'invoice_prefix', nextField: 'invoice_next' },
    { key: 'quote', label: 'Quotes', prefixField: 'quote_prefix', nextField: 'quote_next' },
    { key: 'jobcard', label: 'Jobcards', prefixField: 'jobcard_prefix', nextField: 'jobcard_next' },
    { key: 'credit_note', label: 'Credit Notes', prefixField: 'credit_note_prefix', nextField: 'credit_note_next' },
    { key: 'purchase_order', label: 'Purchase Orders', prefixField: 'purchase_order_prefix', nextField: 'purchase_order_next' },
] as const;

const submit = () => {
    form.put('/administration/document-numbering');
};
</script>
