<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface Category {
    id: number;
    name: string;
    description: string;
    color: string;
    is_active: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    category: Category;
}

const props = defineProps<Props>();

const form = useForm({
    name: props.category.name,
    description: props.category.description,
    color: props.category.color,
    is_active: props.category.is_active,
    sort_order: props.category.sort_order,
});

const colorOptions = [
    { name: 'Blue', value: '#3B82F6' },
    { name: 'Green', value: '#10B981' },
    { name: 'Yellow', value: '#F59E0B' },
    { name: 'Purple', value: '#8B5CF6' },
    { name: 'Cyan', value: '#06B6D4' },
    { name: 'Lime', value: '#84CC16' },
    { name: 'Orange', value: '#F97316' },
    { name: 'Pink', value: '#EC4899' },
    { name: 'Red', value: '#EF4444' },
    { name: 'Indigo', value: '#6366F1' },
];

function submit() {
    form.put(administration.categories.update(props.category.id).url);
}
</script>

<template>
    <Head :title="`Edit ${props.category.name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Categories', href: administration.categories.index().url },
        { title: props.category.name, href: administration.categories.show(props.category.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="administration.categories.show(props.category.id).url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Category
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Edit Category</h1>
                    <p class="text-gray-600">Update the details for {{ props.category.name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        
                        <div class="space-y-6">
                            <!-- Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Name *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appearance -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Appearance</h2>
                        
                        <div class="space-y-6">
                            <!-- Color -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Color *
                                </label>
                                <div class="mt-2 grid grid-cols-5 gap-3">
                                    <label
                                        v-for="color in colorOptions"
                                        :key="color.value"
                                        class="relative cursor-pointer"
                                    >
                                        <input
                                            v-model="form.color"
                                            type="radio"
                                            :value="color.value"
                                            class="sr-only"
                                        />
                                        <div
                                            :class="[
                                                'flex h-12 w-full items-center justify-center rounded-lg border-2',
                                                form.color === color.value
                                                    ? 'border-gray-900'
                                                    : 'border-gray-300 hover:border-gray-400'
                                            ]"
                                            :style="{ backgroundColor: color.value }"
                                        >
                                            <span class="text-xs font-medium text-white mix-blend-difference">
                                                {{ color.name }}
                                            </span>
                                        </div>
                                    </label>
                                </div>
                                <div v-if="form.errors.color" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.color }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        
                        <div class="space-y-6">
                            <!-- Sort Order -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Sort Order
                                </label>
                                <input
                                    v-model="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-sm text-gray-500">Lower numbers appear first in lists</p>
                                <div v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.sort_order }}
                                </div>
                            </div>

                            <!-- Active Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for selection)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="administration.categories.show(props.category.id).url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Updating...' : 'Update Category' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
