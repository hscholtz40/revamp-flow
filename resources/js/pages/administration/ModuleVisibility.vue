<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import administration from '@/routes/administration';
import { modules } from '@/lib/modules';
import { Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const currentCompany = computed(() => page.props.currentCompany as {
    id: number;
    name: string;
    visible_modules: string[] | null;
} | null);

// Initialize: if null, show all modules; if array, use that array
const isAllVisible = computed(() => currentCompany.value?.visible_modules === null);
const visibleModules = ref<string[]>(
    isAllVisible.value 
        ? modules.map(m => m.key) 
        : (currentCompany.value?.visible_modules ?? [])
);

const form = useForm({
    visible_modules: isAllVisible.value ? null : visibleModules.value,
});

const toggleModule = (moduleKey: string) => {
    if (!visibleModules.value.includes(moduleKey)) {
        visibleModules.value.push(moduleKey);
    } else {
        visibleModules.value = visibleModules.value.filter(key => key !== moduleKey);
    }
    // If all modules are selected, set to null (all visible)
    // Otherwise, use the array
    form.visible_modules = visibleModules.value.length === modules.length 
        ? null 
        : visibleModules.value;
};

const selectAll = () => {
    visibleModules.value = modules.map(m => m.key);
    form.visible_modules = null; // null means all modules visible
};

const deselectAll = () => {
    visibleModules.value = [];
    form.visible_modules = []; // empty array means no modules visible
};

const isModuleVisible = (moduleKey: string): boolean => {
    return visibleModules.value.includes(moduleKey);
};

const submit = () => {
    form.put(administration.moduleVisibility.update().url, {
        preserveScroll: true,
        onSuccess: () => {
            // Force a full page reload to ensure sidebar gets updated currentCompany data
            window.location.reload();
        },
    });
};
</script>

<template>
    <Head title="Module Visibility" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Module Visibility', href: '/administration/module-visibility' }
    ]">
        <div class="p-4">
            <div class="mb-6">
                <h1 class="text-2xl font-bold">Module Visibility</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Select which modules should be visible in the sidebar menu. Unselected modules will be hidden from all users.
                </p>
            </div>

            <div class="rounded-lg border bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">Available Modules</h2>
                        <p class="text-sm text-gray-600">
                            {{ form.visible_modules === null ? 'All modules visible' : `${visibleModules.length} of ${modules.length} modules selected` }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="selectAll"
                            class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Show All
                        </button>
                        <button
                            @click="deselectAll"
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Hide All
                        </button>
                    </div>
                </div>

                <form @submit.prevent="submit">
                    <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="module in modules"
                            :key="module.key"
                            @click="toggleModule(module.key)"
                            :class="[
                                'flex cursor-pointer items-center justify-between rounded-lg border p-4 transition-colors',
                                isModuleVisible(module.key)
                                    ? 'border-green-500 bg-green-50'
                                    : 'border-gray-200 bg-white hover:border-gray-300'
                            ]"
                        >
                            <span class="font-medium text-gray-900">{{ module.label }}</span>
                            <div
                                :class="[
                                    'flex h-5 w-5 items-center justify-center rounded border-2',
                                    isModuleVisible(module.key)
                                        ? 'border-green-500 bg-green-500'
                                        : 'border-gray-300 bg-white'
                                ]"
                            >
                                <Check
                                    v-if="isModuleVisible(module.key)"
                                    class="h-3 w-3 text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="form.processing">Saving...</span>
                            <span v-else>Save Changes</span>
                        </button>
                        <button
                            type="button"
                            @click="$inertia.visit(administration.index().url)"
                            class="rounded border px-4 py-2 text-sm font-medium hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
