<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    documentLabel: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [boolean];
    choose: [emailNow: boolean];
    cancel: [];
}>();

function close() {
    emit('cancel');
    emit('update:modelValue', false);
}

function choose(emailNow: boolean) {
    emit('choose', emailNow);
    emit('update:modelValue', false);
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-[250] flex items-center justify-center bg-black/50 p-4"
        @click.self="close"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
            <h3 class="mb-2 text-lg font-semibold text-gray-900">Save {{ documentLabel }}</h3>
            <p class="mb-6 text-sm text-gray-600">Email now to customer?</p>

            <div class="flex flex-col gap-3 sm:flex-row-reverse">
                <button
                    type="button"
                    class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    @click="choose(true)"
                >
                    Save and email
                </button>
                <button
                    type="button"
                    class="flex-1 rounded border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    @click="choose(false)"
                >
                    Save only
                </button>
            </div>
        </div>
    </div>
</template>
