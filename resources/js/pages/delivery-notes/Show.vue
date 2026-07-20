<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download, Edit, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import deliveryNotes from '@/routes/delivery-notes';
import jobcards from '@/routes/jobcards';

interface LineItem {
    id: number;
    description: string;
    quantity: number;
    line_group_id?: number | null;
    product?: { id: number; name: string; sku: string | null } | null;
}

interface Props {
    deliveryNote: {
        id: number;
        delivery_note_number: string;
        delivery_date: string | null;
        delivery_address: string | null;
        status: 'draft' | 'sent' | 'delivered' | 'cancelled';
        notes: string | null;
        created_at: string;
        updated_at: string;
        jobcard?: { id: number; job_number: string; title: string } | null;
        customer?: { id: number; name: string } | null;
        contact?: { id: number; name: string } | null;
        user?: { id: number; name: string } | null;
        line_items: LineItem[];
        line_groups?: { id: number; name: string; sort_order?: number }[];
    };
    pdfTemplates?: Array<{ id: number; name: string; is_default: boolean }>;
    defaultTemplateId?: number | null;
}

const props = defineProps<Props>();

const canEdit = useAuthAbility('delivery-notes', 'edit');
const canDelete = useAuthAbility('delivery-notes', 'delete');

const showTemplateModal = ref(false);
const selectedTemplateId = ref<number | null>(props.defaultTemplateId ?? null);

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

const groupedLineItems = computed(() => {
    const groups = [...(props.deliveryNote.line_groups || [])].sort((a, b) => Number(a.sort_order ?? 0) - Number(b.sort_order ?? 0));
    const items = props.deliveryNote.line_items || [];
    const fallbackGroupId = groups[0]?.id ?? 1;

    if (groups.length === 0) {
        return [{ groupName: 'Items', items }];
    }

    return groups
        .map((group) => ({
            groupName: group.name || 'Items',
            items: items.filter((item) => (item.line_group_id ?? fallbackGroupId) === group.id),
        }))
        .filter((group) => group.items.length > 0);
});

const updateStatus = (status: string) => {
    router.put(deliveryNotes.updateStatus(props.deliveryNote.id).url, { status });
};

const downloadPdf = () => {
    const url = new URL(deliveryNotes.downloadPdf(props.deliveryNote.id).url, window.location.origin);
    if (selectedTemplateId.value) {
        url.searchParams.set('template_id', selectedTemplateId.value.toString());
    }
    window.open(url.toString(), '_blank');
    showTemplateModal.value = false;
    selectedTemplateId.value = null;
};

const deleteDeliveryNote = () => {
    if (confirm('Are you sure you want to delete this delivery note?')) {
        router.delete(deliveryNotes.destroy(props.deliveryNote.id).url);
    }
};
</script>

<template>
    <Head :title="`Delivery Note ${props.deliveryNote.delivery_note_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Jobcards', href: jobcards.index().url },
        { title: props.deliveryNote.jobcard?.job_number ?? 'Jobcard', href: props.deliveryNote.jobcard ? jobcards.show(props.deliveryNote.jobcard.id).url : jobcards.index().url },
        { title: props.deliveryNote.delivery_note_number, href: '#' },
    ]">
        <div class="space-y-6 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ props.deliveryNote.delivery_note_number }}</h1>
                    <p class="text-sm text-gray-600">
                        Delivery note for jobcard
                        <Link
                            v-if="props.deliveryNote.jobcard"
                            :href="jobcards.show(props.deliveryNote.jobcard.id).url"
                            class="text-blue-600 hover:underline"
                        >
                            {{ props.deliveryNote.jobcard.job_number }}
                        </Link>
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="props.deliveryNote.jobcard"
                        :href="jobcards.show(props.deliveryNote.jobcard.id).url"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Jobcard
                    </Link>
                    <button
                        v-if="props.pdfTemplates && props.pdfTemplates.length > 1"
                        @click="showTemplateModal = true"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <Download class="h-4 w-4" />
                        Download PDF
                    </button>
                    <button
                        v-else
                        @click="downloadPdf"
                        class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        <Download class="h-4 w-4" />
                        Download PDF
                    </button>
                    <Link
                        v-if="canEdit"
                        :href="deliveryNotes.edit(props.deliveryNote.id).url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        v-if="canDelete"
                        @click="deleteDeliveryNote"
                        class="inline-flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Delivery Details</h2>
                        </div>
                        <div class="grid gap-4 p-6 md:grid-cols-2">
                            <div>
                                <p class="text-sm text-gray-600">Customer</p>
                                <p class="font-medium text-gray-900">{{ props.deliveryNote.customer?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Contact</p>
                                <p class="font-medium text-gray-900">{{ props.deliveryNote.contact?.name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Delivery Date</p>
                                <p class="font-medium text-gray-900">{{ props.deliveryNote.delivery_date ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Delivery Address</p>
                                <p class="font-medium text-gray-900 whitespace-pre-line">{{ props.deliveryNote.delivery_address || '—' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Items</h2>
                        </div>
                        <div class="p-6 space-y-6">
                            <div v-for="(group, index) in groupedLineItems" :key="index">
                                <h3 v-if="groupedLineItems.length > 1" class="mb-2 text-sm font-semibold text-gray-700">{{ group.groupName }}</h3>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Description</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Product</th>
                                                <th class="px-3 py-2 text-right text-xs font-medium uppercase text-gray-500">Qty</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            <tr v-for="item in group.items" :key="item.id">
                                                <td class="px-3 py-2 text-sm text-gray-900">{{ item.description }}</td>
                                                <td class="px-3 py-2 text-sm text-gray-600">
                                                    <span v-if="item.product">{{ item.product.name }}<span v-if="item.product.sku"> ({{ item.product.sku }})</span></span>
                                                    <span v-else>—</span>
                                                </td>
                                                <td class="px-3 py-2 text-right text-sm text-gray-900">{{ item.quantity }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.deliveryNote.notes" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                        </div>
                        <div class="p-6 text-sm text-gray-700 whitespace-pre-line">{{ props.deliveryNote.notes }}</div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Status</h2>
                        </div>
                        <div class="p-6">
                            <p class="mb-3 text-sm text-gray-600">
                                Current:
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium capitalize text-gray-800">{{ props.deliveryNote.status }}</span>
                            </p>
                            <div v-if="canEdit" class="flex flex-wrap gap-2">
                                <button
                                    v-for="status in statusOptions"
                                    :key="status.value"
                                    @click="updateStatus(status.value)"
                                    :disabled="props.deliveryNote.status === status.value"
                                    class="rounded-md border px-3 py-1 text-sm capitalize disabled:opacity-50"
                                    :class="props.deliveryNote.status === status.value ? 'border-blue-200 bg-blue-50 text-blue-800' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                                >
                                    {{ status.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-600/50">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-lg">
                <h3 class="mb-4 text-lg font-medium text-gray-900">Select PDF Template</h3>
                <select v-model="selectedTemplateId" class="mb-4 w-full rounded border border-gray-300 px-3 py-2">
                    <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                        {{ template.name }}{{ template.is_default ? ' (Default)' : '' }}
                    </option>
                </select>
                <div class="flex justify-end gap-2">
                    <button @click="showTemplateModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm">Cancel</button>
                    <button @click="downloadPdf" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white">Download</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
