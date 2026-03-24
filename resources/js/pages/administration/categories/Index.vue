<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Tag } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface Category {
    id: number;
    name: string;
    description: string;
    color: string;
    is_active: boolean;
    sort_order: number;
    products_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    categories: Category[];
}

const props = defineProps<Props>();

function deleteCategory(category: Category) {
    if (category.products_count > 0) {
        alert(`Cannot delete "${category.name}" because it has ${category.products_count} products. Please move or delete the products first.`);
        return;
    }
    
    if (confirm(`Are you sure you want to delete "${category.name}"?`)) {
        router.delete(administration.categories.destroy(category.id).url);
    }
}
</script>

<template>
    <Head title="Category Management" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Categories', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Category Management</h1>
                    <p class="text-gray-600">Manage product and service categories</p>
                </div>
                <Link
                    :href="administration.categories.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Category
                </Link>
            </div>

            <!-- Categories List -->
            <div v-if="props.categories.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Tag class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No categories found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first category.</p>
                <div class="mt-6">
                    <Link
                        :href="administration.categories.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Category
                    </Link>
                </div>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="category in props.categories"
                    :key="category.id"
                    class="cursor-pointer rounded-lg border bg-white p-6 shadow-sm transition-shadow hover:shadow-md focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                    tabindex="0"
                    role="link"
                    @click="router.visit(administration.categories.show(category.id).url)"
                    @keydown.enter.prevent="router.visit(administration.categories.show(category.id).url)"
                    @keydown.space.prevent="router.visit(administration.categories.show(category.id).url)"
                >
                    <!-- Category Header -->
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="h-4 w-4 rounded-full"
                                :style="{ backgroundColor: category.color }"
                            />
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ category.name }}</h3>
                                <p class="text-sm text-gray-500">{{ category.products_count }} products</p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-2 py-1 text-xs font-medium',
                                category.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ category.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Description -->
                    <p v-if="category.description" class="mb-4 text-sm text-gray-600 line-clamp-2">
                        {{ category.description }}
                    </p>

                    <!-- Sort Order -->
                    <div class="mb-4 text-sm text-gray-500">
                        Sort Order: {{ category.sort_order }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2" @click.stop>
                        <Link
                            :href="administration.categories.edit(category.id).url"
                            class="inline-flex items-center justify-center rounded border px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-50 md:px-3 md:py-1"
                        >
                            <ListTableActionLabel label="Edit">
                                <Edit class="h-4 w-4" />
                            </ListTableActionLabel>
                        </Link>
                        <button
                            type="button"
                            @click="deleteCategory(category)"
                            class="inline-flex items-center justify-center rounded border border-red-300 px-2 py-1.5 text-sm text-red-700 hover:bg-red-50 md:px-3 md:py-1"
                        >
                            <ListTableActionLabel label="Delete">
                                <Trash2 class="h-4 w-4" />
                            </ListTableActionLabel>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
