<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import Footer from '@/components/Footer.vue';
import PermissionErrorModal from '@/components/PermissionErrorModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { useRecentlyViewed } from '@/composables/useRecentlyViewed';
import { watch, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const { addItem } = useRecentlyViewed();

// Track page URL when it changes
watch(
    () => page.url,
    (url) => {
        if (url) {
            addItem(url);
        }
    },
    { immediate: true }
);

// Also track on mount to catch initial page load
onMounted(() => {
    if (page.url) {
        addItem(page.url);
    }
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
            <Footer />
        </AppContent>
        <PermissionErrorModal />
    </AppShell>
</template>
