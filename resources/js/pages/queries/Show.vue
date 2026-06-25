<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, Mail, Phone, Trash2, CheckCircle2, RotateCcw, LayoutGrid, List, FileVideo, ExternalLink, AlertTriangle, MapPin, Check, X, Lock, Briefcase } from 'lucide-vue-next';

interface QueryAttachment {
    id: number;
    url: string;
    type: string | null;
    original_name: string | null;
}

interface QueryItem {
    id: number;
    kind: 'enquiry' | 'job' | 'contractor';
    name: string;
    surname: string;
    email: string;
    cell: string;
    description: string;
    status: 'open' | 'closed';
    created_at: string | null;
    // Job fields (null for enquiries)
    response: 'pending' | 'accepted' | 'declined' | 'expired' | null;
    responded_at: string | null;
    external_source: string | null;
    external_quote_id: string | null;
    job_location: string | null;
    job_latitude: string | number | null;
    job_longitude: string | number | null;
    quote_line_items: Array<{group: string|null, description: string, quantity: number, unit_price: number, line_total: number}> | null;
    quote_total_amount: number | null;
    quote_client_email: string | null;
    quote_client_phone: string | null;
    company_name: string | null;
    company_registration_no: string | null;
    company_address: string | null;
    company_email: string | null;
    company_contact_number: string | null;
    company_website: string | null;
    accepted_at: string | null;
    accepted_customer_id: number | null;
    accepted_contact_id: number | null;
    attachments: QueryAttachment[];
}

const props = defineProps<{ query: QueryItem }>();

const canEdit = useAuthAbility('queries', 'edit');
const canDelete = useAuthAbility('queries', 'delete');

const isJob = computed(() => props.query.kind === 'job');
const isContractor = computed(() => props.query.kind === 'contractor');
const isPending = computed(() => props.query.response === 'pending');
const attachments = computed(() => props.query.attachments ?? []);
const attachmentView = ref<'grid' | 'list'>('grid');
const hasCoordinates = computed(() => props.query.job_latitude != null && props.query.job_longitude != null);
const hasStructuredQuote = computed(() => isJob.value && Array.isArray(props.query.quote_line_items) && props.query.quote_line_items.length > 0);

const groupedLineItems = computed(() => {
    if (!hasStructuredQuote.value) return [];
    const groups: Record<string, typeof props.query.quote_line_items> = {};
    for (const item of props.query.quote_line_items ?? []) {
        const key = item.group ?? '';
        if (!groups[key]) groups[key] = [];
        groups[key].push(item);
    }
    return Object.entries(groups);
});

function formatCurrency(value: number | null) {
    if (value == null) return '—';
    return 'R ' + value.toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const responseMeta = computed(() => {
    switch (props.query.response) {
        case 'accepted':
            return { label: 'Accepted', classes: 'bg-green-100 text-green-800' };
        case 'declined':
            return { label: 'Declined', classes: 'bg-red-100 text-red-800' };
        case 'expired':
            return { label: 'Expired', classes: 'bg-amber-100 text-amber-800' };
        default:
            return { label: 'Pending', classes: 'bg-blue-100 text-blue-800' };
    }
});

function setStatus(status: 'open' | 'closed') {
    router.patch(`/queries/${props.query.id}`, { status }, { preserveScroll: true });
}

// Accept / decline (job queries only)
const isResponding = ref(false);
const showDeclineDialog = ref(false);

function acceptJob() {
    router.post(`/queries/${props.query.id}/accept`, {}, {
        preserveScroll: true,
        onStart: () => (isResponding.value = true),
        onFinish: () => (isResponding.value = false),
    });
}

function performDecline() {
    router.post(`/queries/${props.query.id}/decline`, {}, {
        preserveScroll: true,
        onStart: () => (isResponding.value = true),
        onFinish: () => {
            isResponding.value = false;
            showDeclineDialog.value = false;
        },
    });
}

const isAcceptingContractor = ref(false);
function acceptContractor() {
    router.post(`/queries/${props.query.id}/accept-contractor`, {}, {
        preserveScroll: true,
        onStart: () => (isAcceptingContractor.value = true),
        onFinish: () => (isAcceptingContractor.value = false),
    });
}

const isConverting = ref(false);

function convertToJobcard() {
    router.post(`/queries/${props.query.id}/convert-to-jobcard`, {}, {
        preserveScroll: true,
        onStart: () => (isConverting.value = true),
        onFinish: () => (isConverting.value = false),
    });
}

const showDeleteDialog = ref(false);
const isDeleting = ref(false);

function cancelDelete() {
    if (isDeleting.value) return;
    showDeleteDialog.value = false;
}

function performDelete() {
    router.delete(`/queries/${props.query.id}`, {
        onStart: () => (isDeleting.value = true),
        onFinish: () => (isDeleting.value = false),
    });
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
    <Head :title="isJob ? `Job — ${query.name} ${query.surname}` : `Query — ${query.name} ${query.surname}`" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <Link href="/queries" class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                    <ArrowLeft class="h-4 w-4" />
                    Back to Queries
                </Link>
                <!-- Job/contractor: response badge. Enquiry: open/closed. -->
                <span
                    v-if="isJob"
                    :class="responseMeta.classes"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ responseMeta.label }}
                </span>
                <span
                    v-else-if="isContractor"
                    :class="responseMeta.classes"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                >
                    {{ responseMeta.label }}
                </span>
                <span
                    v-else
                    :class="query.status === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                    class="inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize"
                >
                    {{ query.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Details -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Read-only notice for dispatched jobs -->
                    <div v-if="isJob" class="flex items-start gap-2 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
                        <Lock class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            This is a read-only copy of the customer's quote, dispatched to you via
                            <strong class="capitalize">{{ query.external_source || 'an external system' }}</strong>.
                            You can <strong>accept</strong> or <strong>decline</strong> it — the first contractor to accept claims the job.
                        </span>
                    </div>
                    <div v-if="isContractor" class="rounded-lg border border-purple-200 bg-purple-50 p-4 text-sm text-purple-800">
                        Contractor onboarding query. Accepting this will create a customer and a linked primary contact.
                    </div>

                    <!-- Structured Quote Display (like a physical quote) -->
                    <div v-if="hasStructuredQuote" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <div class="mb-4 border-b border-gray-200 pb-4">
                            <h1 class="text-2xl font-bold text-gray-900">QUOTE FROM REVAMP</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ query.external_quote_id }}
                                <span v-if="query.external_source"> · Source: {{ query.external_source }}</span>
                            </p>
                        </div>

                        <dl class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div v-if="query.name">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Client</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ query.name }}</dd>
                            </div>
                            <div v-if="query.quote_client_email">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Email</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <Mail class="h-4 w-4 text-gray-400" />
                                    <a :href="`mailto:${query.quote_client_email}`" class="text-blue-600 hover:underline">{{ query.quote_client_email }}</a>
                                </dd>
                            </div>
                            <div v-if="query.quote_client_phone">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Phone</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <Phone class="h-4 w-4 text-gray-400" />
                                    <a :href="`tel:${query.quote_client_phone}`" class="text-blue-600 hover:underline">{{ query.quote_client_phone }}</a>
                                </dd>
                            </div>
                            <div v-if="query.job_location">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Job Location</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <MapPin class="h-4 w-4 text-gray-400" />
                                    <span>{{ query.job_location }}</span>
                                </dd>
                            </div>
                        </dl>

                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                        <th class="pb-2 pr-4">Description</th>
                                        <th class="pb-2 pr-4 text-right">Qty</th>
                                        <th class="pb-2 pr-4 text-right">Unit Price</th>
                                        <th class="pb-2 text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-for="[groupName, items] in groupedLineItems" :key="groupName">
                                        <tr v-if="groupName" class="bg-gray-50">
                                            <td colspan="4" class="py-2 pr-4 text-xs font-semibold uppercase tracking-wider text-gray-600">{{ groupName }}</td>
                                        </tr>
                                        <tr v-for="item in items" :key="item.description" class="border-b border-gray-100">
                                            <td class="py-2 pr-4 text-gray-900">{{ item.description }}</td>
                                            <td class="py-2 pr-4 text-right text-gray-600">{{ item.quantity }}</td>
                                            <td class="py-2 pr-4 text-right text-gray-600">{{ formatCurrency(item.unit_price) }}</td>
                                            <td class="py-2 text-right font-medium text-gray-900">{{ formatCurrency(item.line_total) }}</td>
                                        </tr>
                                    </template>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-300">
                                        <td colspan="3" class="py-3 pr-4 text-right text-sm font-semibold text-gray-900">TOTAL</td>
                                        <td class="py-3 text-right text-lg font-bold text-gray-900">{{ formatCurrency(query.quote_total_amount) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Fallback: plain text description for enquiries or quotes without structured data -->
                    <div v-if="!hasStructuredQuote" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h1 class="text-2xl font-bold text-gray-900">{{ query.name }} {{ query.surname }}</h1>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ isJob ? 'Received' : 'Submitted' }} {{ formatDate(query.created_at) }}
                            <span v-if="isJob && query.external_quote_id"> · Quote {{ query.external_quote_id }}</span>
                        </p>

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
                            <div v-if="isJob && query.job_location" class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">Job Location</dt>
                                <dd class="mt-1 flex items-center gap-2 text-sm text-gray-900">
                                    <MapPin class="h-4 w-4 text-gray-400" />
                                    <span>{{ query.job_location }}</span>
                                </dd>
                                <p v-if="hasCoordinates" class="mt-1 pl-6 text-xs text-gray-500">
                                    {{ query.job_latitude }}, {{ query.job_longitude }}
                                </p>
                            </div>
                        </dl>

                        <div class="mt-6">
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-500">
                                {{ isJob ? 'Quote details' : 'Description' }}
                            </dt>
                            <dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ query.description }}</dd>
                        </div>
                        <div v-if="isContractor" class="mt-6 border-t border-gray-200 pt-4">
                            <h3 class="text-xs font-medium uppercase tracking-wider text-gray-500">Company Details</h3>
                            <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div><dt class="text-xs text-gray-500">Company Name</dt><dd class="text-sm text-gray-900">{{ query.company_name || '—' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Registration No</dt><dd class="text-sm text-gray-900">{{ query.company_registration_no || '—' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Company Email</dt><dd class="text-sm text-gray-900">{{ query.company_email || '—' }}</dd></div>
                                <div><dt class="text-xs text-gray-500">Company Contact</dt><dd class="text-sm text-gray-900">{{ query.company_contact_number || '—' }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Company Address</dt><dd class="text-sm text-gray-900">{{ query.company_address || '—' }}</dd></div>
                                <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Company Website</dt><dd class="text-sm text-gray-900">{{ query.company_website || '—' }}</dd></div>
                            </dl>
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
                    <!-- Job query: accept / decline or final state -->
                    <div v-if="isJob" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-sm font-medium uppercase tracking-wider text-gray-500">Respond</h2>

                        <template v-if="isPending && canEdit">
                            <div class="space-y-3">
                                <button
                                    type="button"
                                    :disabled="isResponding"
                                    @click="acceptJob"
                                    class="flex w-full items-center justify-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <Check class="h-4 w-4" />
                                    Accept Job
                                </button>
                                <button
                                    type="button"
                                    :disabled="isResponding"
                                    @click="showDeclineDialog = true"
                                    class="flex w-full items-center justify-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <X class="h-4 w-4" />
                                    Decline
                                </button>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">First contractor to accept claims this job.</p>
                        </template>

                        <template v-else-if="isPending && !canEdit">
                            <p class="text-sm text-gray-500">You don't have permission to respond to this job.</p>
                        </template>

                        <div v-else class="space-y-2">
                            <div :class="responseMeta.classes" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold">
                                <CheckCircle2 v-if="query.response === 'accepted'" class="h-4 w-4" />
                                <Lock v-else-if="query.response === 'expired'" class="h-4 w-4" />
                                <X v-else class="h-4 w-4" />
                                {{ responseMeta.label }}
                            </div>
                            <p v-if="query.response === 'accepted'" class="text-sm text-gray-600">
                                Your company claimed this job{{ query.responded_at ? ' on ' + formatDate(query.responded_at) : '' }}.
                            </p>
                            <p v-else-if="query.response === 'expired'" class="text-sm text-gray-600">
                                This job was claimed by another contractor first, so it can no longer be accepted.
                            </p>
                            <p v-else-if="query.response === 'declined'" class="text-sm text-gray-600">
                                You declined this job{{ query.responded_at ? ' on ' + formatDate(query.responded_at) : '' }}.
                            </p>

                            <!-- Convert to Jobcard: only for accepted queries from Revamp with quote data -->
                            <button
                                v-if="query.response === 'accepted' && query.external_source && hasStructuredQuote && canEdit"
                                type="button"
                                :disabled="isConverting"
                                @click="convertToJobcard"
                                class="flex w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50 mt-3"
                            >
                                <Briefcase class="h-4 w-4" />
                                {{ isConverting ? 'Converting…' : 'Convert to Jobcard' }}
                            </button>
                        </div>
                    </div>

                    <div v-else-if="isContractor" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <h2 class="mb-4 text-sm font-medium uppercase tracking-wider text-gray-500">Contractor Approval</h2>
                        <button
                            v-if="query.response === 'pending' && canEdit"
                            type="button"
                            :disabled="isAcceptingContractor"
                            @click="acceptContractor"
                            class="flex w-full items-center justify-center gap-2 rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50"
                        >
                            {{ isAcceptingContractor ? 'Accepting…' : 'Accept and Create Customer/Contact' }}
                        </button>
                        <p v-else-if="query.response === 'accepted'" class="text-sm text-gray-600">
                            Accepted {{ query.accepted_at ? `on ${formatDate(query.accepted_at)}` : '' }}.
                            Customer ID: {{ query.accepted_customer_id ?? '—' }}, Contact ID: {{ query.accepted_contact_id ?? '—' }}.
                        </p>
                    </div>

                    <!-- Enquiry query: open/close management -->
                    <div v-else class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
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
                        </div>
                    </div>

                    <!-- Delete (both kinds) -->
                    <div v-if="canDelete" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                        <button
                            type="button"
                            @click="showDeleteDialog = true"
                            class="flex w-full items-center justify-center gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-100"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete {{ isJob ? 'Job' : 'Query' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Decline confirmation modal -->
        <Dialog :open="showDeclineDialog" @update:open="(value) => { if (!value && !isResponding) showDeclineDialog = false; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <X class="h-5 w-5" />
                        </span>
                        Decline job
                    </DialogTitle>
                    <DialogDescription>
                        Decline the job from <strong>{{ query.name }} {{ query.surname }}</strong>?
                        Other contractors can still accept it. This cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <button
                        type="button"
                        :disabled="isResponding"
                        @click="showDeclineDialog = false"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="isResponding"
                        @click="performDecline"
                        class="flex items-center gap-2 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <X class="h-4 w-4" />
                        <span>{{ isResponding ? 'Declining…' : 'Decline' }}</span>
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation modal -->
        <Dialog :open="showDeleteDialog" @update:open="(value) => { if (!value) cancelDelete(); }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-red-100 text-red-600">
                            <AlertTriangle class="h-5 w-5" />
                        </span>
                        Delete {{ isJob ? 'job' : 'query' }}
                    </DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the {{ isJob ? 'job' : 'query' }} from
                        <strong>{{ query.name }} {{ query.surname }}</strong>?
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
