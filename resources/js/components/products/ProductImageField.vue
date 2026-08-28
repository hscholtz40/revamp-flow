<script setup lang="ts">
import { ref, watch } from 'vue';

const props = defineProps<{
    modelValue: File | null;
    currentImageUrl?: string | null;
    removeImage?: boolean;
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [File | null];
    'update:removeImage': [boolean];
}>();

const preview = ref<string | null>(props.currentImageUrl ?? null);

watch(
    () => props.currentImageUrl,
    (url) => {
        if (!props.modelValue && !props.removeImage) {
            preview.value = url ?? null;
        }
    },
);

function handleImageChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    emit('update:modelValue', file);
    emit('update:removeImage', false);

    if (!file) {
        preview.value = props.currentImageUrl ?? null;
        return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        preview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
}

function removeCurrentImage() {
    emit('update:modelValue', null);
    emit('update:removeImage', true);
    preview.value = null;
}
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-gray-700">Product Image</label>
        <div class="mt-2 flex items-start gap-4">
            <div
                v-if="preview"
                class="h-24 w-24 overflow-hidden rounded-lg border bg-gray-50"
            >
                <img :src="preview" alt="Product image preview" class="h-full w-full object-cover" />
            </div>
            <div class="flex-1 space-y-2">
                <input
                    type="file"
                    accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-full file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                    @change="handleImageChange"
                />
                <p class="text-xs text-gray-500">PNG, JPG, GIF, or WebP up to 2MB</p>
                <button
                    v-if="preview"
                    type="button"
                    class="text-sm text-red-600 hover:text-red-800"
                    @click="removeCurrentImage"
                >
                    Remove image
                </button>
            </div>
        </div>
        <div v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</div>
    </div>
</template>
