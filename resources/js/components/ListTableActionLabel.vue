<script setup lang="ts">
/**
 * Row actions on list/table views: label text from md breakpoint up; icon-only below md
 * (sr-only duplicate keeps screen readers working when the icon is visible).
 */
defineProps<{
    label: string;
}>();
</script>

<template>
    <span class="inline-flex items-center justify-center gap-1.5">
        <span class="inline-flex shrink-0 md:hidden" aria-hidden="true">
            <slot />
        </span>
        <span class="sr-only md:hidden">{{ label }}</span>
        <span class="hidden md:inline">{{ label }}</span>
    </span>
</template>
