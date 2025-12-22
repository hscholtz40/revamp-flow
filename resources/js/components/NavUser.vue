<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import UserMenuContent from './UserMenuContent.vue';

const page = usePage();
const user = page.props.auth?.user;
const { isMobile, state } = useSidebar();

</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <template v-if="user">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <SidebarMenuButton
                            size="lg"
                            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        >
                            <UserInfo :user="user" />
                            <ChevronsUpDown class="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        :side="
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                  ? 'left'
                                  : 'bottom'
                        "
                        align="end"
                        :side-offset="4"
                    >
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
            <template v-else>
                <!-- Debug: Show when user is null -->
                <SidebarMenuButton
                    size="lg"
                    class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                >
                    <div class="text-xs text-red-500">No user (check console)</div>
                </SidebarMenuButton>
            </template>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
