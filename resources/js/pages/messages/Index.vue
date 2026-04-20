<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    conversations: Array<{
        id: number;
        subject: string | null;
        messages: Array<{ body: string; created_at: string }>;
    }>;
}>();
</script>

<template>
    <Head title="Messages" />

    <AppLayout :breadcrumbs="[{ title: 'Messages', href: '/messages' }]">
        <div class="p-6 space-y-4">
            <h1 class="text-2xl font-semibold">Message Center</h1>
            <p class="text-sm text-muted-foreground">Web and mobile conversations sync through the API v1 messaging endpoints.</p>

            <div class="grid gap-3">
                <div v-for="conversation in conversations" :key="conversation.id" class="rounded border p-3">
                    <div class="font-medium">{{ conversation.subject || `Conversation #${conversation.id}` }}</div>
                    <div class="text-sm text-muted-foreground mt-1">
                        {{ conversation.messages?.[0]?.body || 'No messages yet' }}
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
