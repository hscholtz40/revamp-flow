<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    conversations: Array<{
        id: number;
        subject: string | null;
        participants?: Array<{ user?: { name?: string | null } | null }> | null;
        messages: Array<{ body: string; created_at: string }>;
    }>;
}>();
</script>

<template>
    <Head title="Messages" />

    <AppLayout :breadcrumbs="[{ title: 'Messages', href: '/messages' }]">
        <div class="p-4 space-y-4">
            <h1 class="text-2xl font-bold text-gray-900">Message Center</h1>
            <p class="text-sm text-gray-600">Review recent conversations synced across web and mobile apps.</p>

            <div class="overflow-hidden rounded-lg border bg-white">
                <div class="divide-y">
                    <div v-for="conversation in conversations" :key="conversation.id" class="p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium text-gray-900">{{ conversation.subject || `Conversation #${conversation.id}` }}</div>
                                <div class="mt-1 text-xs text-gray-600">
                                    {{
                                        (conversation.participants || [])
                                            .map((participant) => participant.user?.name)
                                            .filter(Boolean)
                                            .join(', ') || 'No participants'
                                    }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 text-sm text-gray-700">
                            {{ conversation.messages?.[0]?.body || 'No messages yet' }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500">
                            {{ conversation.messages?.[0]?.created_at || '' }}
                        </div>
                    </div>
                    <div v-if="conversations.length === 0" class="p-6 text-sm text-gray-500">No conversations yet.</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
