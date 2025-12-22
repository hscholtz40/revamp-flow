<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { User, Clock, RotateCcw } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';

interface ModelVersion {
    id: number;
    version_number: number;
    user: {
        id: number;
        name: string;
    } | null;
    reason: string | null;
    notes: string | null;
    is_current: boolean;
    created_at: string;
    data: Record<string, any>;
    changes: Record<string, any> | null;
}

const props = defineProps<{
    modelType: string;
    modelId: number;
}>();

const versions = ref<ModelVersion[]>([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const response = await fetch(
            `/audit-logs/model/${props.modelType}/${props.modelId}/versions`
        );
        const data = await response.json();
        versions.value = data.versions;
    } catch (error) {
        console.error('Failed to load versions:', error);
    } finally {
        loading.value = false;
    }
});

function restoreVersion(versionId: number) {
    if (confirm('Are you sure you want to restore this version? This will create a new version with the restored data.')) {
        router.post(`/audit-logs/versions/${versionId}/restore`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                // Reload versions
                onMounted();
            },
        });
    }
}

function formatDate(date: string): string {
    return new Date(date).toLocaleString();
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Version History</CardTitle>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="py-4 text-center text-sm text-muted-foreground">
                Loading versions...
            </div>
            <div v-else-if="versions.length === 0" class="py-4 text-center text-sm text-muted-foreground">
                No version history available
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="version in versions"
                    :key="version.id"
                    class="rounded-lg border p-4"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Badge :variant="version.is_current ? 'default' : 'outline'">
                                Version {{ version.version_number }}
                            </Badge>
                            <span v-if="version.is_current" class="text-xs text-muted-foreground">
                                (Current)
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-muted-foreground">
                                <Clock class="mr-1 inline h-3 w-3" />
                                {{ formatDate(version.created_at) }}
                            </span>
                            <Button
                                v-if="!version.is_current"
                                variant="ghost"
                                size="sm"
                                @click="restoreVersion(version.id)"
                            >
                                <RotateCcw class="mr-1 h-3 w-3" />
                                Restore
                            </Button>
                        </div>
                    </div>
                    <div v-if="version.user" class="mb-2 flex items-center gap-1 text-sm text-muted-foreground">
                        <User class="h-3 w-3" />
                        {{ version.user.name }}
                    </div>
                    <div v-if="version.reason" class="mb-1 text-sm">
                        <span class="font-medium">Reason:</span> {{ version.reason }}
                    </div>
                    <div v-if="version.notes" class="text-sm text-muted-foreground">
                        {{ version.notes }}
                    </div>
                    <div v-if="version.changes" class="mt-2 text-xs text-muted-foreground">
                        Changed fields: {{ Object.keys(version.changes).join(', ') }}
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>

