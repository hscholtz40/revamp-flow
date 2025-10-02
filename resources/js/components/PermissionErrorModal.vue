<script setup lang="ts">
import { computed } from 'vue';
import { ShieldX, X } from 'lucide-vue-next';
import { usePermissionError } from '@/composables/usePermissionError';

const { permissionError, showPermissionModal, hideError, getModuleDisplayName, getAbilityDisplayName } = usePermissionError();

const moduleDisplayName = computed(() => 
    permissionError.value ? getModuleDisplayName(permissionError.value.module) : ''
);

const abilityDisplayName = computed(() => 
    permissionError.value ? getAbilityDisplayName(permissionError.value.ability) : ''
);
</script>

<template>
    <!-- Modal Backdrop -->
    <div
        v-if="showPermissionModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
        @click="hideError"
    >
        <!-- Modal Content -->
        <div
            class="relative mx-4 w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
            @click.stop
        >
            <!-- Close Button -->
            <button
                @click="hideError"
                class="absolute right-4 top-4 text-gray-400 hover:text-gray-600"
            >
                <X class="h-5 w-5" />
            </button>

            <!-- Icon -->
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                <ShieldX class="h-8 w-8 text-red-600" />
            </div>

            <!-- Title -->
            <h2 class="mb-3 text-center text-xl font-semibold text-gray-900">
                Access Denied
            </h2>

            <!-- Message -->
            <p class="mb-4 text-center text-gray-600">
                You don't have permission to {{ abilityDisplayName }} {{ moduleDisplayName }}.
            </p>

            <!-- Additional Info -->
            <div class="mb-6 rounded-lg bg-yellow-50 border border-yellow-200 p-3">
                <p class="text-sm text-yellow-800">
                    <strong>Need access?</strong> Contact your administrator to request permission for this feature.
                </p>
            </div>

            <!-- Actions -->
            <div class="flex gap-3">
                <button
                    @click="hideError"
                    class="flex-1 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    OK
                </button>
            </div>
        </div>
    </div>
</template>
