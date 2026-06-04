<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, Mail, Phone, Trash2, CheckCircle2, RotateCcw, LayoutGrid, List, FileVideo, ExternalLink } from 'lucide-vue-next';

interface QueryAttachment {
    id: number;
    url: string;
    type: string | null;
    original_name: string | null;
}

interface QueryItem {
    id: number;
    name: string;
    surname: string;
    email: string;
    cell: string;
    description: string;
    status: 'open' | 'closed';
    created_at: string | null;
    attachments: QueryAttachment[];
}

const props = defineProps<{ query: QueryItem }>();

const canEdit = useAuthAbility('queries', 'edit');
const canDelete = useAuthAbility('queries', 'delete');

const attachments = computed(() => props.query.attachments ?? []);

const attachmentView = ref<'grid' | 'list'>('grid');

function setStatus(status: 'open' | 'closed') {
    router.patch(`/queries/${props.query.id}`, { status }, { preserveScroll: true });
}

function deleteQuery() {
    if (confirm(`Delete the query from "${props.query.name} ${props.query.surname}"?`)) {
        router.delete(`/queries/${props.query.id}`);
    }
}

function formatDate(value: string | null) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    const pad = (n: number) => String(n).padStart(2, '0');
    let hours12 = d.getHours() % 12;
    if (hours12 === 0) hours12 = 12;
    return `${d.getFullYear()}-${pad(d.getDate())}-${pad(d.getMonth() + 1)} ${pad(hours12)}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}
</script>

<template>
    <Head :title="`Query — ${query.name} ${query.surname}`" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <Link href="/queries" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Queries
                </Link>
                <span
                    :class="query.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                >
                    {{ query.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Details -->
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h1 class="text-2xl font-bold text-gray-900">{{ query.name }} {{ query.surname }}</h1>
                        <p class="mt-1 text-sm text-gray-500">Submitted {{ formatDate(query.created_at) }}</p>

                        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Email</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <Mail class="h-4 w-4 text-gray-400" />
                                    <a :href="`mailto:${query.email}`" class="text-blue-600 hover:underline">{{ query.email }}</a>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Cell</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <Phone class="h-4 w-4 text-gray-400" />
                                    <a :href="`tel:${query.cell}`" class="text-blue-600 hover:underline">{{ query.cell }}</a>
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6">
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Description</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ query.description }}</dd>
                        </div>
                    </div>

                    <!-- Attachments -->
                    <div v-if="attachments.length" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 flex items-center justify-between">
                            <h2 class="text-sm font-medium uppercase tracking-wider text-gray-500">
                                Attachments ({{ attachments.length }})
                            </h2>
                            <div class="inline-flex overflow-hidden rounded-md border border-gray-300">
                                <button
                                    type="button"
                                    @click="attachmentView = 'grid'"
                                    :class="attachmentView === 'grid' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    class="flex items-center gap-1 px-3 py-1.5 text-xs font-medium"
                                    title="Grid view"
                                >
                                    <LayoutGrid class="h-4 w-4" />
                                    Grid
                                </button>
                                <button
                                    type="button"
                                    @click="attachmentView = 'list'"
                                    :class="attachmentView === 'list' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    class="flex items-center gap-1 border-l border-gray-300 px-3 py-1.5 text-xs font-medium"
                                    title="List view"
                                >
                                    <List class="h-4 w-4" />
                                    List
                                </button>
                            </div>
                        </div>

                        <!-- Grid view -->
                        <div v-if="attachmentView === 'grid'" class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div v-for="attachment in attachments" :key="attachment.id" class="space-y-2">
                                <video v-if="attachment.type === 'video'" :src="attachment.url" controls class="max-h-[360px] w-full rounded-md bg-black" />
                                <img v-else :src="attachment.url" :alt="attachment.original_name || 'Query attachment'" class="max-h-[360px] w-full rounded-md object-contain" />
                                <a :href="attachment.url" target="_blank" class="inline-block truncate text-sm text-blue-600 hover:underline">
                                    {{ attachment.original_name || 'Open in new tab' }}
                                </a>
                            </div>
                        </div>

                        <!-- List view -->
                        <ul v-else class="divide-y divide-gray-200">
                            <li v-for="attachment in attachments" :key="attachment.id" class="flex items-center gap-3 py-3">
                                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                                    <img v-if="attachment.type !== 'video'" :src="attachment.url" :alt="attachment.original_name || 'Query attachment'" class="h-full w-full object-cover" />
                                    <div v-else class="flex h-full w-full items-center justify-center text-gray-400">
                                        <FileVideo class="h-6 w-6" />
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ attachment.original_name || 'Attachment' }}</p>
                                    <p class="text-xs uppercase tracking-wide text-gray-400">{{ attachment.type || 'file' }}</p>
                                </div>
                                <a
                                    :href="attachment.url"
                                    target="_blank"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-blue-600 hover:bg-gray-50"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                    Open
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Actions -->
                <div class="space-y-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-sm font-medium uppercase tracking-wider text-gray-500">Manage</h2>
                        <div class="space-y-3">
                            <button
                                v-if="canEdit && query.status === 'open'"
                                type="button"
                                @click="setStatus('closed')"
                                class="flex w-full items-center justify-center gap-2 rounded-md bg-gray-700 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                Mark as Closed
                            </button>
                            <button
                                v-if="canEdit && query.status === 'closed'"
                                type="button"
                                @click="setStatus('open')"
                                class="flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                            >
                                <RotateCcw class="h-4 w-4" />
                                Reopen Query
                            </button>
                            <button
                                v-if="canDelete"
                                type="button"
                                @click="deleteQuery"
                                class="flex w-full items-center justify-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                            >
                                <Trash2 class="h-4 w-4" />
                                Delete Query
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
