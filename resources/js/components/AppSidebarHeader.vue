<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import CompanySwitcher from '@/components/CompanySwitcher.vue';
import ListViewColumnsEditor from '@/components/ListViewColumnsEditor.vue';
import NotificationsBell from '@/components/NotificationsBell.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Company {
    id: number;
    name: string;
    logo_path: string | null;
    is_default: boolean;
}

interface LicenseExpiry {
    expires_at: string;
    days_remaining: number;
    message: string;
}

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const currentCompany = computed(() => page.props.currentCompany as Company | null);
const companies = computed(() => page.props.companies as Company[] || []);
const userType = computed(() => (page.props.auth?.user as { user_type?: string } | null)?.user_type ?? 'standard');
const showCompanySwitcher = computed(() => userType.value !== 'client');
const licenseExpiry = computed(() => (page.props.licenseExpiry as LicenseExpiry | null | undefined) ?? null);
const isUrgentExpiry = computed(() => {
    const days = licenseExpiry.value?.days_remaining;
    return typeof days === 'number' && days <= 7;
});
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        
        <!-- Company Switcher -->
        <div class="flex items-center gap-2">
            <TooltipProvider v-if="licenseExpiry" :delay-duration="0">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Link
                            href="/administration/license"
                            class="inline-flex max-w-[16rem] items-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium transition-colors"
                            :class="isUrgentExpiry
                                ? 'bg-amber-50 text-amber-800 hover:bg-amber-100'
                                : 'text-amber-600 hover:bg-amber-50 hover:text-amber-700'"
                            :aria-label="licenseExpiry.message"
                        >
                            <AlertTriangle class="size-4 shrink-0" />
                            <span class="hidden truncate sm:inline">{{ licenseExpiry.message }}</span>
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent class="sm:hidden">
                        <p>{{ licenseExpiry.message }}</p>
                    </TooltipContent>
                </Tooltip>
            </TooltipProvider>
            <NotificationsBell />
            <ListViewColumnsEditor />
            <CompanySwitcher
                v-if="showCompanySwitcher"
                :current-company="currentCompany" 
                :companies="companies" 
            />
        </div>
    </header>
</template>
