<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel class="px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-sidebar-foreground/50">
            Platform
        </SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="urlIsActive(item.href, page.url)"
                    :tooltip="item.title"
                    class="rounded-xl border border-transparent px-2.5 text-sidebar-foreground/80 transition-all duration-200 hover:border-sidebar-border/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:shadow-sm dark:data-[active=true]:border-primary/35 dark:data-[active=true]:bg-primary/20"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
