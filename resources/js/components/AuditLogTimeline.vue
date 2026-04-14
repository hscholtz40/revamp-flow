<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import type { BadgeVariant } from '@/components/ui/badge';
import { Calendar, User, Eye } from 'lucide-vue-next';

interface AuditLog {
    id: number;
    event: string;
    description: string;
    user: {
        id: number;
        name: string;
    } | null;
    created_at: string;
}

const props = defineProps<{
    auditLogs: AuditLog[];
    modelType?: string;
    modelId?: number;
}>();

function getEventBadgeVariant(event: string): BadgeVariant {
    const variants: Record<string, BadgeVariant> = {
        created: 'default',
        updated: 'secondary',
        deleted: 'destructive',
        restored: 'outline',
    };
    return variants[event] || 'default';
}

function formatDate(date: string): string {
    return new Date(date).toLocaleString();
}
</script>

<template>
    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 bg-purple-50 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Activity History
            </h2>
            <p class="text-sm text-gray-600">Track all changes made to this record</p>
        </div>
        <div class="p-6">
            <div v-if="auditLogs.length === 0" class="py-8 text-center text-sm text-gray-500">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p>No activity history yet. Changes to this record will be tracked here.</p>
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="log in auditLogs"
                    :key="log.id"
                    class="flex items-start gap-4 border-l-2 border-gray-300 pl-4"
                >
                    <div class="flex-1">
                        <div class="mb-1 flex items-center gap-2">
                            <Badge :variant="getEventBadgeVariant(log.event)" class="text-xs">
                                {{ log.event }}
                            </Badge>
                            <span class="text-sm text-gray-500">
                                {{ formatDate(log.created_at) }}
                            </span>
                        </div>
                        <div class="mb-2 text-sm text-gray-900">{{ log.description }}</div>
                        <div v-if="log.user" class="flex items-center gap-1 text-xs text-gray-500">
                            <User class="h-3 w-3" />
                            {{ log.user.name }}
                        </div>
                    </div>
                    <Link
                        :href="`/audit-logs/${log.id}`"
                        class="text-blue-600 hover:underline"
                    >
                        <Eye class="h-4 w-4" />
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

