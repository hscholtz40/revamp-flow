<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

function isExternalUrl(href: string): boolean {
    return href.startsWith('http://') || href.startsWith('https://');
}
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        class="rounded-xl border border-transparent px-2.5 text-sidebar-foreground/75 transition-all duration-200 hover:border-sidebar-border/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:shadow-sm dark:data-[active=true]:border-primary/35 dark:data-[active=true]:bg-primary/20"
                        as-child
                    >
                        <Link
                            v-if="!isExternalUrl(toUrl(item.href))"
                            :href="toUrl(item.href)"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                        <a
                            v-else
                            :href="toUrl(item.href)"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
