<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { getCsrfToken } from '@/lib/csrf';
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

interface NotificationRow {
    id: string;
    data: { type?: string; entity_id?: number; title?: string; url?: string };
    read_at: string | null;
    created_at: string;
}

const page = usePage();

const unreadCount = computed(() => {
    const n = page.props.unreadNotificationCount;
    return typeof n === 'number' ? n : 0;
});

const open = ref(false);
const loading = ref(false);
const rows = ref<NotificationRow[]>([]);

async function fetchNotifications(): Promise<void> {
    loading.value = true;
    try {
        const res = await fetch('/user/notifications?per_page=15', {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('Failed to load');
        }
        const json = (await res.json()) as { data?: NotificationRow[] };
        rows.value = json.data ?? [];
    } catch {
        toast.error('Could not load notifications');
    } finally {
        loading.value = false;
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        void fetchNotifications();
    }
});

function hrefFor(n: NotificationRow): string | null {
    if (n.data?.url) {
        return n.data.url;
    }
    const t = n.data?.type;
    const id = n.data?.entity_id;
    if (id == null) {
        return null;
    }
    if (t === 'jobcard' || t === 'jobcard_note') {
        return `/jobcards/${id}`;
    }
    if (t === 'task') {
        return `/tasks/${id}`;
    }
    return null;
}

function relativeTime(iso: string): string {
    const t = new Date(iso).getTime();
    const s = Math.round((Date.now() - t) / 1000);
    if (s < 45) {
        return 'Just now';
    }
    const m = Math.round(s / 60);
    if (m < 60) {
        return `${m}m ago`;
    }
    const h = Math.round(m / 60);
    if (h < 48) {
        return `${h}h ago`;
    }
    const d = Math.round(h / 24);
    if (d < 14) {
        return `${d}d ago`;
    }
    return new Date(iso).toLocaleDateString();
}

async function markRead(id: string, navigateTo: string | null): Promise<void> {
    try {
        const res = await fetch(`/user/notifications/${id}/read`, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('Failed');
        }
        const idx = rows.value.findIndex((r) => r.id === id);
        if (idx !== -1) {
            rows.value[idx] = { ...rows.value[idx], read_at: new Date().toISOString() };
        }
        open.value = false;
        if (navigateTo) {
            router.visit(navigateTo);
        } else {
            await router.reload({ only: ['unreadNotificationCount'] });
        }
    } catch {
        toast.error('Could not update notification');
    }
}

async function markAllRead(): Promise<void> {
    try {
        const res = await fetch('/user/notifications/read-all', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            throw new Error('Failed');
        }
        rows.value = rows.value.map((r) => ({
            ...r,
            read_at: r.read_at ?? new Date().toISOString(),
        }));
        await router.reload({ only: ['unreadNotificationCount'] });
    } catch {
        toast.error('Could not mark notifications read');
    }
}
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="relative h-9 w-9 shrink-0"
                aria-label="Notifications"
            >
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] font-semibold leading-none text-destructive-foreground"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-96 max-w-[calc(100vw-2rem)] p-0">
            <div class="flex items-center justify-between border-b px-3 py-2">
                <span class="text-sm font-semibold">Notifications</span>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs"
                    :disabled="unreadCount === 0"
                    @click="markAllRead"
                >
                    Mark all read
                </Button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div v-if="loading" class="px-3 py-8 text-center text-sm text-muted-foreground">Loading…</div>
                <template v-else>
                    <div v-if="rows.length === 0" class="px-3 py-8 text-center text-sm text-muted-foreground">
                        No notifications yet
                    </div>
                    <button
                        v-for="n in rows"
                        :key="n.id"
                        type="button"
                        class="flex w-full flex-col gap-0.5 border-b border-border/60 px-3 py-2.5 text-left text-sm transition-colors hover:bg-muted/60"
                        :class="{ 'bg-muted/30': !n.read_at }"
                        @click="markRead(n.id, hrefFor(n))"
                    >
                        <span class="font-medium leading-snug">{{ n.data?.title ?? 'Notification' }}</span>
                        <span class="text-xs text-muted-foreground">{{ relativeTime(n.created_at) }}</span>
                    </button>
                </template>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
