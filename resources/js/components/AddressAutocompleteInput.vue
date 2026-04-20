<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { attachPlacesAutocomplete } from '@/lib/placesAutocomplete';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
    /** Merged onto the input (e.g. border, padding). */
    inputClass?: string;
    /** When set, overrides shared Inertia `google_maps_api_key`. */
    googleMapsApiKey?: string;
    /** Native input id for labels */
    id?: string;
    autocomplete?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [string];
}>();

const page = usePage();
const inputRef = ref<HTMLInputElement | null>(null);
let teardown: (() => void) | undefined;

const resolvedKey = () =>
    (props.googleMapsApiKey?.trim() || page.props.google_maps_api_key?.trim() || '') as string;

async function bindAutocomplete() {
    teardown?.();
    teardown = undefined;
    const el = inputRef.value;
    const key = resolvedKey();
    if (!el || !key) {
        return;
    }
    try {
        teardown = await attachPlacesAutocomplete(el, key, (address) => {
            emit('update:modelValue', address);
        });
    } catch {
        /* Plain text input still works if Places fails or API not enabled */
    }
}

onMounted(() => {
    void nextTick(() => void bindAutocomplete());
});

watch(
    () => [resolvedKey(), inputRef.value] as const,
    () => void nextTick(() => void bindAutocomplete()),
);

watch(
    () => props.modelValue,
    (v) => {
        const el = inputRef.value;
        if (!el || document.activeElement === el) {
            return;
        }
        const next = v ?? '';
        if (el.value !== next) {
            el.value = next;
        }
    },
);

onBeforeUnmount(() => {
    teardown?.();
    teardown = undefined;
});
</script>

<template>
    <input
        :id="id"
        ref="inputRef"
        type="text"
        :value="modelValue"
        :placeholder="placeholder"
        :autocomplete="autocomplete ?? 'street-address'"
        :class="inputClass"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
</template>
