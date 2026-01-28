<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Tag, Calendar, Package } from 'lucide-vue-next';
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
    category: Category;
}

const props = defineProps<Props>();

function deleteCategory() {
    if (props.category.products_count > 0) {
        alert(`Cannot delete "${props.category.name}" because it has ${props.category.products_count} products. Please move or delete the products first.`);
        return;
    }
    
    if (confirm(`Are you sure you want to delete "${props.category.name}"?`)) {
        // This would need to be implemented with a form or router.delete
    }
}
</script>

<template>
    <Head :title="props.category.name" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Categories', href: administration.categories.index().url },
        { title: props.category.name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="administration.categories.index().url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Categories
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="administration.categories.edit(props.category.id).url"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        @click="deleteCategory"
                        class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <!-- Category Header -->
                <div class="mb-8 rounded-lg border bg-white p-6">
                    <div class="flex items-start gap-6">
                        <!-- Category Color -->
                        <div class="flex-shrink-0">
                            <div
                                class="flex h-16 w-16 items-center justify-center rounded-lg"
                                :style="{ backgroundColor: props.category.color }"
                            >
                                <Tag class="h-8 w-8 text-white" />
                            </div>
                        </div>

                        <!-- Category Info -->
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-gray-900">{{ props.category.name }}</h1>
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        props.category.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.category.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            
                            <p v-if="props.category.description" class="text-gray-600">
                                {{ props.category.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Category Details Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center gap-3">
                                    <Tag class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Name</div>
                                        <div class="text-gray-900">{{ props.category.name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Package class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Products Count</div>
                                        <div class="text-gray-900">{{ props.category.products_count }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-5 w-5 rounded-full"
                                        :style="{ backgroundColor: props.category.color }"
                                    />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Color</div>
                                        <div class="text-gray-900">{{ props.category.color }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Tag class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Sort Order</div>
                                        <div class="text-gray-900">{{ props.category.sort_order }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="props.category.description" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                            <div class="text-gray-700 whitespace-pre-wrap">{{ props.category.description }}</div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Quick Stats</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.category.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.category.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Products</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.category.products_count }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Sort Order</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.category.sort_order }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Timestamps</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Created</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.category.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.category.updated_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
