<script setup lang="ts">
interface PhysicalAttributesForm {
    weight: number | null;
    length: number | null;
    width: number | null;
    height: number | null;
    color: string;
    size: string;
    errors: Record<string, string | undefined>;
}

defineProps<{
    form: PhysicalAttributesForm;
}>();
</script>

<template>
    <div class="rounded-lg border bg-white p-6">
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Physical Attributes</h2>
        <p class="mb-4 text-sm text-gray-600">
            Optional weight, dimensions, colour, and size details for this product.
        </p>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                <input
                    v-model.number="form.weight"
                    type="number"
                    min="0"
                    step="0.001"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <div v-if="form.errors.weight" class="mt-1 text-sm text-red-600">{{ form.errors.weight }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Colour</label>
                <input
                    v-model="form.color"
                    type="text"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="e.g. Red"
                />
                <div v-if="form.errors.color" class="mt-1 text-sm text-red-600">{{ form.errors.color }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Size</label>
                <input
                    v-model="form.size"
                    type="text"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="e.g. Large / 42"
                />
                <div v-if="form.errors.size" class="mt-1 text-sm text-red-600">{{ form.errors.size }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Length (cm)</label>
                <input
                    v-model.number="form.length"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <div v-if="form.errors.length" class="mt-1 text-sm text-red-600">{{ form.errors.length }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Width (cm)</label>
                <input
                    v-model.number="form.width"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <div v-if="form.errors.width" class="mt-1 text-sm text-red-600">{{ form.errors.width }}</div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Height (cm)</label>
                <input
                    v-model.number="form.height"
                    type="number"
                    min="0"
                    step="0.01"
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <div v-if="form.errors.height" class="mt-1 text-sm text-red-600">{{ form.errors.height }}</div>
            </div>
        </div>
    </div>
</template>
