<script setup lang="ts">
import UserInfo from '@/components/UserInfo.vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { LogOut, Settings, X } from 'lucide-vue-next';
import { useRecentlyViewed } from '@/composables/useRecentlyViewed';
import { computed } from 'vue';

interface Props {
    user: User | null;
}

const props = defineProps<Props>();

const { items, removeItem } = useRecentlyViewed();

const recentlyViewedItems = computed(() => items.value.slice(0, 5)); // Show max 5 items

const handleLogout = () => {
    router.flushAll();
};

const handleRemoveItem = (event: MouseEvent, url: string) => {
    event.preventDefault();
    event.stopPropagation();
    removeItem(url);
};
</script>

<template>
    <template v-if="user">
        <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <UserInfo :user="user" :show-email="true" />
            </div>
        </DropdownMenuLabel>
        <DropdownMenuSeparator />
        <template v-if="recentlyViewedItems.length > 0">
            <DropdownMenuGroup>
                <DropdownMenuLabel class="px-2 py-1.5 text-xs font-semibold text-muted-foreground">
                    Recently Viewed
                </DropdownMenuLabel>
                <DropdownMenuItem
                    v-for="item in recentlyViewedItems"
                    :key="item.url"
                    :as-child="true"
                    class="group"
                >
                    <Link
                        class="flex w-full items-center justify-between gap-2"
                        :href="item.url"
                        prefetch
                        as="button"
                    >
                        <div class="flex items-center gap-2 min-w-0 flex-1">
                            <component
                                v-if="item.icon"
                                :is="item.icon"
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                            />
                            <span class="truncate text-sm">{{ item.title }}</span>
                        </div>
                        <button
                            type="button"
                            class="ml-2 shrink-0 opacity-0 transition-opacity group-hover:opacity-100"
                            @click.stop="handleRemoveItem($event, item.url)"
                            title="Remove from recently viewed"
                        >
                            <X class="h-3 w-3 text-muted-foreground hover:text-foreground" />
                        </button>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
        </template>
        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link class="block w-full" :href="edit()" prefetch as="button">
                    <Settings class="mr-2 h-4 w-4" />
                    Settings
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full"
                :href="logout()"
                @click="handleLogout"
                as="button"
                data-test="logout-button"
            >
                <LogOut class="mr-2 h-4 w-4" />
                Log out
            </Link>
        </DropdownMenuItem>
    </template>
</template>
