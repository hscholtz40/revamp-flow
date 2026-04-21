<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import Footer from '@/components/Footer.vue';
import NotesPanel from '@/components/NotesPanel.vue';
import PermissionErrorModal from '@/components/PermissionErrorModal.vue';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { useRecentlyViewed } from '@/composables/useRecentlyViewed';
import { computed, watch, onMounted } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const { addItem } = useRecentlyViewed();

const noteContext = computed(() => {
    const path = (page.url || '').split('?')[0];
    const patterns: Array<{ regex: RegExp; module: string }> = [
        { regex: /^\/customers\/(\d+)\/?$/, module: 'customers' },
        { regex: /^\/contacts\/(\d+)\/?$/, module: 'contacts' },
        { regex: /^\/products\/(\d+)\/?$/, module: 'products' },
        { regex: /^\/products\/batches\/(\d+)\/?$/, module: 'product-batches' },
        { regex: /^\/products\/serial-numbers\/(\d+)\/?$/, module: 'product-serial-numbers' },
        { regex: /^\/suppliers\/(\d+)\/?$/, module: 'suppliers' },
        { regex: /^\/stock-movements\/(\d+)\/?$/, module: 'stock-movements' },
        { regex: /^\/jobcards\/(\d+)\/?$/, module: 'jobcards' },
        { regex: /^\/quotes\/(\d+)\/?$/, module: 'quotes' },
        { regex: /^\/invoices\/(\d+)\/?$/, module: 'invoices' },
        { regex: /^\/credit-notes\/(\d+)\/?$/, module: 'credit-notes' },
        { regex: /^\/purchase-orders\/(\d+)\/?$/, module: 'purchase-orders' },
        { regex: /^\/tasks\/(\d+)\/?$/, module: 'tasks' },
        { regex: /^\/reports\/(\d+)\/?$/, module: 'reports' },
        { regex: /^\/licenses\/(\d+)\/?$/, module: 'licenses' },
    ];

    for (const pattern of patterns) {
        const match = path.match(pattern.regex);
        if (match) {
            return { module: pattern.module, recordId: Number(match[1]) };
        }
    }

    return null;
});

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
            <div v-if="noteContext" class="px-4 pb-4">
                <NotesPanel
                    title="Record Notes"
                    :module="noteContext.module"
                    :record-id="noteContext.recordId"
                />
            </div>
            <Footer />
        </AppContent>
        <PermissionErrorModal />
    </AppShell>
</template>
