<script setup lang="ts">
import { computed } from 'vue';

interface QuickActionOption {
    key: string;
    label: string;
    group: string;
}

const props = defineProps<{
    modelValue: Record<string, boolean>;
    options: QuickActionOption[];
}>();

const emit = defineEmits<{
    'update:modelValue': [Record<string, boolean>];
}>();

const documentOptions = computed(() => props.options.filter((option) => option.group === 'documents'));
const catalogOptions = computed(() => props.options.filter((option) => option.group === 'catalog'));

function updateAction(key: string, enabled: boolean) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: enabled,
    });
}
</script>

<template>
    <div class="space-y-4">
        <div v-if="documentOptions.length > 0">
            <p class="mb-2 text-sm font-medium text-muted-foreground">Documents</p>
            <div class="grid gap-2 md:grid-cols-2">
                <label
                    v-for="option in documentOptions"
                    :key="option.key"
                    class="flex items-center gap-2 rounded border px-3 py-2"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue[option.key] !== false"
                        @change="updateAction(option.key, ($event.target as HTMLInputElement).checked)"
                    />
                    <span>{{ option.label }}</span>
                </label>
            </div>
        </div>

        <div v-if="catalogOptions.length > 0">
            <p class="mb-2 text-sm font-medium text-muted-foreground">Customers &amp; catalog</p>
            <div class="grid gap-2 md:grid-cols-2">
                <label
                    v-for="option in catalogOptions"
                    :key="option.key"
                    class="flex items-center gap-2 rounded border px-3 py-2"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue[option.key] !== false"
                        @change="updateAction(option.key, ($event.target as HTMLInputElement).checked)"
                    />
                    <span>{{ option.label }}</span>
                </label>
            </div>
        </div>
    </div>
</template>
