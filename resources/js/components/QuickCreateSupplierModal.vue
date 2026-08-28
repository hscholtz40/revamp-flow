<script setup lang="ts">
import { watch } from 'vue';

export interface QuickCreateSupplierForm {
    name: string;
    email: string;
    phone: string;
    vat_number: string;
    processing: boolean;
    errors: Record<string, string>;
}

const props = defineProps<{
    modelValue: boolean;
    form: QuickCreateSupplierForm;
}>();

const emit = defineEmits<{
    'update:modelValue': [boolean];
    submit: [];
}>();

function close() {
    emit('update:modelValue', false);
}

watch(
    () => props.modelValue,
    (open) => {
        if (!open) {
            props.form.errors = {};
        }
    },
);
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-[250] flex items-center justify-center bg-black/50 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
            <h3 class="mb-1 text-lg font-semibold text-gray-900">Quick Add Supplier</h3>
            <p class="mb-4 text-sm text-gray-600">
                Add a supplier and assign it to this quote line.
            </p>

            <form class="space-y-4" @submit.prevent="emit('submit')">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name *</span>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Email</span>
                    <input v-model="form.email" type="email" class="w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Phone</span>
                    <input v-model="form.phone" type="text" class="w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">VAT number</span>
                    <input v-model="form.vat_number" type="text" class="w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.vat_number" class="mt-1 text-sm text-red-600">{{ form.errors.vat_number }}</p>
                </label>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Adding...' : 'Add supplier' }}
                    </button>
                    <button type="button" class="flex-1 rounded border px-4 py-2 hover:bg-gray-50" @click="close">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
