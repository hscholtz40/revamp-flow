<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Search, Eye, Trash2, Mail, Phone, Inbox, Paperclip, AlertTriangle, MapPin } from 'lucide-vue-next';

interface QueryItem {
    id: number;
    kind: 'enquiry' | 'job' | 'contractor';
    name: string;
    surname: string;
    email: string;
    cell: string;
    description: string;
    attachments_count: number;
    status: 'open' | 'closed';
    response: 'pending' | 'accepted' | 'declined' | 'expired' | null;
    external_source: string | null;
    external_quote_id: string | null;
    quote_total_amount: number | null;
    job_location: string | null;
    created_at: string;
}

const jobResponseClasses: Record<string, string> = {
    pending: 'bg-blue-100 text-blue-800',
    accepted: 'bg-green-100 text-green-800',
    declined: 'bg-red-100 text-red-800',
    expired: 'bg-amber-100 text-amber-800',
};

interface Props {
    queries?: {
        data: QueryItem[];
        links: any[];
        meta: any;
        from?: number;
        to?: number;
        total?: number;
    };
    filters?: {
        search?: string;
        status?: '' | 'open' | 'closed';
    };
    counts?: {
        open: number;
        closed: number;
    };
    integration?: {
        public_url: string;
        contractor_public_url?: string | null;
    } | null;
}

const props = withDefaults(defineProps<Props>(), {
    queries: () => ({ data: [], links: [], meta: {} }),
    filters: () => ({}),
    counts: () => ({ open: 0, closed: 0 }),
});

const canDelete = useAuthAbility('queries', 'delete');

const search = ref(props.filters?.search || '');
const status = ref<'' | 'open' | 'closed'>(props.filters?.status || '');

const hasQueries = computed(() => !!props.queries?.data && props.queries.data.length > 0);

const paginationFrom = computed(() => props.queries?.meta?.from ?? props.queries?.from ?? (hasQueries.value ? 1 : 0));
const paginationTo = computed(() => props.queries?.meta?.to ?? props.queries?.to ?? (props.queries?.data?.length ?? 0));
const paginationTotal = computed(() => props.queries?.meta?.total ?? props.queries?.total ?? (props.queries?.data?.length ?? 0));

function copy(text: string) {
    navigator.clipboard.writeText(text);
}

function applyFilters() {
    const params: Record<string, string> = {};
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (status.value) params.status = status.value;

    router.get('/queries', params, { preserveState: true, replace: true });
}

function setStatus(value: '' | 'open' | 'closed') {
    status.value = value;
    applyFilters();
}

function clearFilters() {
    search.value = '';
    status.value = '';
    applyFilters();
}

const queryToDelete = ref<QueryItem | null>(null);
const isDeleting = ref(false);

function confirmDelete(query: QueryItem) {
    queryToDelete.value = query;
}

function cancelDelete() {
    if (isDeleting.value) return;
    queryToDelete.value = null;
}

function performDelete() {
    if (!queryToDelete.value) return;
    const data: Record<string, string | number> = {};
    if (status.value) data.status = status.value;
    if (search.value && search.value.trim()) data.search = search.value.trim();
    const currentPage = props.queries?.meta?.current_page;
    if (currentPage && currentPage > 1) data.page = currentPage;

    router.delete(`/queries/${queryToDelete.value.id}`, {
        data,
        preserveScroll: true,
        onStart: () => (isDeleting.value = true),
        onFinish: () => {
            isDeleting.value = false;
            queryToDelete.value = null;
        },
    });
}

function formatDate(value: string) {
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
    <Head title="Queries" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Queries</h1>
                    <p class="text-gray-600">Public enquiries and dispatched contractor jobs</p>
                </div>
            </div>

            <div v-if="props.integration" class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-600">Public Form Integration</h2>
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="mb-1 font-medium text-gray-800">Hosted URL</p>
                        <div class="flex gap-2">
                            <input :value="props.integration.public_url" readonly class="w-full rounded border border-gray-300 px-3 py-2 text-xs text-gray-700" />
                            <button type="button" class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700" @click="copy(props.integration.public_url)">Copy</button>
                        </div>
                    </div>
                    <div v-if="props.integration.contractor_public_url">
                        <p class="mb-1 font-medium text-gray-800">Contractor Form URL (Licensing only)</p>
                        <div class="flex gap-2">
                            <input :value="props.integration.contractor_public_url" readonly class="w-full rounded border border-gray-300 px-3 py-2 text-xs text-gray-700" />
                            <button type="button" class="rounded bg-blue-600 px-3 py-2 text-white hover:bg-blue-700" @click="copy(props.integration.contractor_public_url)">Copy</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status tabs -->
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    type="button"
                    @click="setStatus('')"
                    :class="status === '' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium"
                >
                    All ({{ counts.open + counts.closed }})
                </button>
                <button
                    type="button"
                    @click="setStatus('open')"
                    :class="status === 'open' ? 'bg-green-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium"
                >
                    Open ({{ counts.open }})
                </button>
                <button
                    type="button"
                    @click="setStatus('closed')"
                    :class="status === 'closed' ? 'bg-gray-700 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium"
                >
                    Closed ({{ counts.closed }})
                </button>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="min-w-[200px] flex-1">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search name, email, cell, description..."
                                class="w-full rounded border border-gray-300 py-2 pl-10 pr-4 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="applyFilters"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Filter
                        </button>
                        <button
                            @click="clearFilters"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Queries Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Query</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Submitted</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template v-if="hasQueries">
                            <tr
                                v-for="query in props.queries.data"
                                :key="query.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(`/queries/${query.id}`)"
                                @keydown.enter.prevent="router.visit(`/queries/${query.id}`)"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <Inbox class="mr-2 h-5 w-5 text-gray-400" />
                                        <div>
                                            <div class="font-medium text-gray-900">{{ query.name }} {{ query.surname }}</div>
                                            <div v-if="query.kind === 'job' && query.external_quote_id" class="mt-0.5 text-xs text-gray-500">
                                                {{ query.external_quote_id }}<span v-if="query.external_source"> &middot; Source: {{ query.external_source }}</span>
                                            </div>
                                            <div v-if="query.kind === 'job' && query.job_location" class="mt-0.5 flex items-center gap-1 text-xs text-gray-400">
                                                <MapPin class="h-3 w-3" />
                                                <span class="truncate max-w-[200px]">{{ query.job_location }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        :class="query.kind === 'job' ? 'bg-indigo-100 text-indigo-800' : (query.kind === 'contractor' ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-700')"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ query.kind === 'job' ? 'Job' : (query.kind === 'contractor' ? 'Contractor' : 'Enquiry') }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-1">
                                        <Mail class="h-4 w-4" />
                                        {{ query.email }}
                                    </div>
                                    <div class="mt-1 flex items-center gap-1">
                                        <Phone class="h-4 w-4" />
                                        {{ query.cell }}
                                    </div>
                                </td>
                                <td class="max-w-xs px-6 py-4 text-sm text-gray-500">
                                    <div class="flex items-center gap-1 truncate">
                                        <span v-if="query.attachments_count" class="flex shrink-0 items-center gap-0.5 text-gray-400" :title="`${query.attachments_count} attachment(s)`">
                                            <Paperclip class="h-4 w-4" />{{ query.attachments_count }}
                                        </span>
                                        <span class="truncate">{{ query.description }}</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ formatDate(query.created_at) }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        v-if="query.kind === 'job'"
                                        :class="jobResponseClasses[query.response ?? 'pending'] ?? 'bg-gray-100 text-gray-800'"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ query.response ?? 'pending' }}
                                    </span>
                                    <span
                                        v-else-if="query.kind === 'contractor'"
                                        :class="jobResponseClasses[query.response ?? 'pending'] ?? 'bg-purple-100 text-purple-800'"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ query.response ?? 'pending' }}
                                    </span>
                                    <span
                                        v-else
                                        :class="query.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ query.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="`/queries/${query.id}`"
                                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-100 px-2 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 md:px-3 md:py-1"
                                        >
                                            <ListTableActionLabel label="View">
                                                <Eye class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="canDelete"
                                            type="button"
                                            @click="confirmDelete(query)"
                                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-red-100 px-2 py-1.5 text-xs font-medium text-red-700 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 md:px-3 md:py-1"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No queries found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="props.queries?.links" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ paginationFrom }} to {{ paginationTo }} of {{ paginationTotal }} queries
                </div>
                <div v-if="props.queries.links.length > 0" class="flex gap-2">
                    <Link
                        v-for="link in props.queries.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                            !link.url ? 'cursor-not-allowed opacity-50' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Delete confirmation modal -->
        <Dialog :open="!!queryToDelete" @update:open="(value) => { if (!value) cancelDelete(); }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <AlertTriangle class="h-5 w-5" />
                        </span>
                        Delete query
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the query from
                        <strong>{{ queryToDelete?.name }} {{ queryToDelete?.surname }}</strong>?
                        Any attached files will also be removed. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        @click="cancelDelete"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="isDeleting"
                        @click="performDelete"
                        class="flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        <span>{{ isDeleting ? 'Deleting…' : 'Delete' }}</span>
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
