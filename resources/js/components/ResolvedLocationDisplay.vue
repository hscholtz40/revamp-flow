<script setup lang="ts">
import {
    formatLocationWithAddress,
    mapsUrlForCoords,
    useReverseGeocode,
} from '@/composables/useReverseGeocode';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    latitude?: number | string | null;
    longitude?: number | string | null;
    accuracy?: number | string | null;
    /** Optional prefix before the resolved location text, e.g. "Location: ". */
    prefix?: string;
    linkClass?: string;
    textClass?: string;
}>();

const { reverseGeocode } = useReverseGeocode();
const address = ref<string | null>(null);

const hasCoords = computed(() => props.latitude != null && props.longitude != null);

const displayText = computed(() => {
    if (!hasCoords.value) {
        return '';
    }

    if (address.value) {
        return formatLocationWithAddress(address.value, props.latitude, props.longitude, props.accuracy);
    }

    // Show coords immediately while reverse geocoding, then upgrade to address + coords.
    return formatLocationWithAddress(null, props.latitude, props.longitude, props.accuracy);
});

const href = computed(() => mapsUrlForCoords(props.latitude, props.longitude));

watch(
    () => [props.latitude, props.longitude] as const,
    async ([lat, lng]) => {
        address.value = null;
        if (lat == null || lng == null) {
            return;
        }

        const resolved = await reverseGeocode(lat, lng);
        if (resolved) {
            address.value = resolved;
        }
    },
    { immediate: true },
);
</script>

<template>
    <span v-if="hasCoords" :class="textClass">
        <template v-if="prefix">{{ prefix }}</template>
        <a
            :href="href"
            target="_blank"
            rel="noopener noreferrer"
            :class="linkClass || 'text-blue-600 hover:text-blue-800 hover:underline'"
            :title="displayText"
        >
            {{ displayText }}
        </a>
    </span>
</template>
