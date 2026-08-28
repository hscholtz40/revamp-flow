<script setup lang="ts">
import { watch } from 'vue';

export interface QuickCreateProductForm {
    name: string;
    type: 'product' | 'service';
    price: number;
    cost: number;
    category: string;
    processing: boolean;
    errors: Record<string, string>;
}

const props = defineProps<{
    modelValue: boolean;
    form: QuickCreateProductForm;
    categoryOptions?: Array<{ id: number; name: string }>;
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
            <h3 class="mb-1 text-lg font-semibold text-gray-900">Quick Add Product</h3>
            <p class="mb-4 text-sm text-gray-600">
                Add this item to your product list and use it on the quote line.
            </p>

            <form class="space-y-4" @submit.prevent="emit('submit')">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name *</span>
                    <input v-model="form.name" type="text" required class="w-full rounded border px-3 py-2" />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Type *</span>
                    <select v-model="form.type" class="w-full rounded border px-3 py-2">
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                    <p v-if="form.errors.type" class="mt-1 text-sm text-red-600">{{ form.errors.type }}</p>
                </label>

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Price *</span>
                        <input
                            v-model.number="form.price"
                            type="number"
                            min="0"
                            step="0.01"
                            required
                            class="w-full rounded border px-3 py-2"
                        />
                        <p v-if="form.errors.price" class="mt-1 text-sm text-red-600">{{ form.errors.price }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700">Cost</span>
                        <input
                            v-model.number="form.cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full rounded border px-3 py-2"
                        />
                        <p v-if="form.errors.cost" class="mt-1 text-sm text-red-600">{{ form.errors.cost }}</p>
                    </label>
                </div>

                <label v-if="(categoryOptions?.length ?? 0) > 0" class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Category</span>
                    <select v-model="form.category" class="w-full rounded border px-3 py-2">
                        <option value="">No category</option>
                        <option v-for="category in categoryOptions" :key="category.id" :value="category.name">
                            {{ category.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.category" class="mt-1 text-sm text-red-600">{{ form.errors.category }}</p>
                </label>

                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Adding...' : 'Add product' }}
                    </button>
                    <button type="button" class="flex-1 rounded border px-4 py-2 hover:bg-gray-50" @click="close">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
