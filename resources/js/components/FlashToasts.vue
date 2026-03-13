<script setup lang="ts">
import type { AppPageProps } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { nextTick, onMounted, onUnmounted } from 'vue';
import { toast } from 'vue-sonner';

type FlashMessages = Partial<Record<'success' | 'error' | 'warning' | 'info' | 'status', string | null>>;

const page = usePage<AppPageProps>();

const showFlashToasts = (flash?: FlashMessages) => {
    if (!flash) {
        return;
    }

    if (flash.success) {
        toast.success(flash.success);
    }

    if (flash.error) {
        toast.error(flash.error);
    }

    if (flash.warning) {
        toast.warning(flash.warning);
    }

    if (flash.info) {
        toast.info(flash.info);
    }

    if (flash.status) {
        toast.message(flash.status);
    }
};

let removeSuccessListener: (() => void) | undefined;

onMounted(() => {
    void nextTick(() => {
        showFlashToasts(page.props.flash);
    });

    removeSuccessListener = router.on('success', (event) => {
        showFlashToasts((event.detail.page.props as AppPageProps).flash);
    });
});

onUnmounted(() => {
    removeSuccessListener?.();
});
</script>

<template>
    <span class="sr-only" aria-hidden="true" />
</template>
