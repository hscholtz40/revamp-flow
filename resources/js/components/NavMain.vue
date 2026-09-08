<script setup lang="ts">
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { captureSidebarScrollFromDom } from '@/lib/sidebarScroll';
import { collectNavHrefs, urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();
const navHrefs = computed(() => collectNavHrefs(props.items));

const isNavActive = (href: NavItem['href']) => urlIsActive(href, page.url, navHrefs.value);

const isParentOrChildActive = (item: NavItem): boolean => {
    if (isNavActive(item.href)) {
        return true;
    }
    return item.children?.some((c) => isNavActive(c.href)) ?? false;
};

function onNavClick() {
    captureSidebarScrollFromDom();
    if (isMobile.value) {
        setOpenMobile(false);
    }
}
</script>

<template>
    <SidebarGroup class="px-2 py-1">
        <SidebarGroupLabel class="px-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-sidebar-foreground/50">
            Platform
        </SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <SidebarMenuItem v-if="item.children?.length">
                    <SidebarMenuButton
                        as-child
                        :is-active="isParentOrChildActive(item)"
                        :tooltip="item.title"
                        class="rounded-xl border border-transparent px-2.5 text-sidebar-foreground/80 transition-all duration-200 hover:border-sidebar-border/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:shadow-sm dark:data-[active=true]:border-primary/35 dark:data-[active=true]:bg-primary/20"
                    >
                        <Link :href="item.href" @click="onNavClick">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuSub>
                        <SidebarMenuSubItem v-for="sub in item.children" :key="sub.title">
                            <SidebarMenuSubButton
                                as-child
                                size="sm"
                                :is-active="isNavActive(sub.href)"
                                class="data-[active=true]:bg-primary/10 data-[active=true]:text-primary"
                            >
                                <Link :href="sub.href" class="flex items-center gap-2" @click="onNavClick">
                                    <component :is="sub.icon" v-if="sub.icon" class="size-4 shrink-0" />
                                    <span>{{ sub.title }}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    </SidebarMenuSub>
                </SidebarMenuItem>
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isNavActive(item.href)"
                        :tooltip="item.title"
                        class="rounded-xl border border-transparent px-2.5 text-sidebar-foreground/80 transition-all duration-200 hover:border-sidebar-border/70 hover:bg-sidebar-accent/80 hover:text-sidebar-foreground data-[active=true]:border-primary/25 data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:shadow-sm dark:data-[active=true]:border-primary/35 dark:data-[active=true]:bg-primary/20"
                    >
                        <Link :href="item.href" @click="onNavClick">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
