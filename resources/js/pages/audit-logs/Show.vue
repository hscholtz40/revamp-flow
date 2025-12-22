<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Calendar, Globe, User, FileText } from 'lucide-vue-next';

interface AuditLog {
    id: number;
    event: string;
    description: string;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    company: {
        id: number;
        name: string;
    } | null;
    auditable_type: string;
    auditable_id: number;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    changed_fields: string[] | null;
    ip_address: string | null;
    user_agent: string | null;
    url: string | null;
    method: string | null;
    metadata: Record<string, any> | null;
    created_at: string;
}

const props = defineProps<{
    auditLog: AuditLog;
}>();

function getEventBadgeVariant(event: string): string {
    const variants: Record<string, string> = {
        created: 'default',
        updated: 'secondary',
        deleted: 'destructive',
        restored: 'outline',
    };
    return variants[event] || 'default';
}

function formatModelType(type: string): string {
    return type.split('\\').pop() || type;
}

function formatValue(value: any): string {
    if (value === null || value === undefined) {
        return 'N/A';
    }
    if (typeof value === 'boolean') {
        return value ? 'Yes' : 'No';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value, null, 2);
    }
    return String(value);
}
</script>

<template>
    <Head :title="`Audit Log #${auditLog.id}`" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/audit-logs">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Audit Log Details</h1>
                        <p class="text-muted-foreground mt-1">
                            View complete change history
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Event Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Event Information</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">
                                        Event Type
                                    </label>
                                    <div class="mt-1">
                                        <Badge :variant="getEventBadgeVariant(auditLog.event)">
                                            {{ auditLog.event }}
                                        </Badge>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">
                                        Date & Time
                                    </label>
                                    <div class="mt-1 flex items-center gap-2">
                                        <Calendar class="h-4 w-4 text-muted-foreground" />
                                        <span>
                                            {{ new Date(auditLog.created_at).toLocaleString() }}
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">
                                        Model Type
                                    </label>
                                    <div class="mt-1">
                                        {{ formatModelType(auditLog.auditable_type) }}
                                    </div>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted-foreground">
                                        Model ID
                                    </label>
                                    <div class="mt-1">#{{ auditLog.auditable_id }}</div>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted-foreground">
                                    Description
                                </label>
                                <div class="mt-1">{{ auditLog.description }}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Changes -->
                    <Card v-if="auditLog.changed_fields && auditLog.changed_fields.length > 0">
                        <CardHeader>
                            <CardTitle>Changes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="field in auditLog.changed_fields"
                                    :key="field"
                                    class="rounded-lg border p-4"
                                >
                                    <div class="mb-2 font-medium">{{ field }}</div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <div class="text-sm text-muted-foreground">Old Value</div>
                                            <div class="mt-1 font-mono text-sm">
                                                {{ formatValue(auditLog.old_values?.[field]) }}
                                            </div>
                                        </div>
                                        <div>
                                            <div class="text-sm text-muted-foreground">New Value</div>
                                            <div class="mt-1 font-mono text-sm">
                                                {{ formatValue(auditLog.new_values?.[field]) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- User Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <User class="h-5 w-5" />
                                User Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="auditLog.user">
                                <div class="text-sm text-muted-foreground">Name</div>
                                <div class="font-medium">{{ auditLog.user.name }}</div>
                                <div class="mt-2 text-sm text-muted-foreground">Email</div>
                                <div class="text-sm">{{ auditLog.user.email }}</div>
                            </div>
                            <div v-else class="text-muted-foreground">System</div>
                        </CardContent>
                    </Card>

                    <!-- Request Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Globe class="h-5 w-5" />
                                Request Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="auditLog.ip_address">
                                <div class="text-sm text-muted-foreground">IP Address</div>
                                <div class="font-mono text-sm">{{ auditLog.ip_address }}</div>
                            </div>
                            <div v-if="auditLog.method">
                                <div class="text-sm text-muted-foreground">HTTP Method</div>
                                <div class="font-medium">{{ auditLog.method }}</div>
                            </div>
                            <div v-if="auditLog.url">
                                <div class="text-sm text-muted-foreground">URL</div>
                                <div class="break-all text-sm">{{ auditLog.url }}</div>
                            </div>
                            <div v-if="auditLog.user_agent">
                                <div class="text-sm text-muted-foreground">User Agent</div>
                                <div class="break-all text-xs">{{ auditLog.user_agent }}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Metadata -->
                    <Card v-if="auditLog.metadata">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="h-5 w-5" />
                                Metadata
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <pre class="overflow-auto text-xs">{{
                                JSON.stringify(auditLog.metadata, null, 2)
                            }}</pre>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

