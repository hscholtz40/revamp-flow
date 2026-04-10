<template>
    <Head title="Credit Notes" />

    <AppLayout :breadcrumbs="[{ title: 'Credit Notes', href: '/credit-notes' }]">
        <div class="bg-blue-50 border-b border-blue-200 px-4 py-3">
            <div class="flex items-center gap-2 text-sm text-blue-700">
                <span class="font-medium">Viewing credit notes for:</span>
                <span class="font-semibold">{{ props.currentCompany.name }}</span>
            </div>
        </div>

        <div class="p-4">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Credit Notes</h1>
                <Link
                    v-if="canCreditNotesCreate"
                    href="/credit-notes/create"
                    class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    New Credit Note
                </Link>
            </div>

            <div class="bg-white rounded-lg border p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search credit notes..."
                            class="w-full rounded border px-3 py-2"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select v-model="statusFilter" class="w-full rounded border px-3 py-2">
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="submitted">Submitted</option>
                            <option value="authorised">Authorised</option>
                            <option value="paid">Paid</option>
                            <option value="voided">Voided</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                        <select v-model="customerFilter" class="w-full rounded border px-3 py-2">
                            <option value="">All Customers</option>
                            <option v-for="c in props.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded bg-gray-500 px-4 py-2 text-white hover:bg-gray-600"
                        >
                            Clear Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg border overflow-hidden">
                <div class="overflow-x-auto">
                    <table data-list-view-table="true" class="w-full">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('credit_note_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Number <span>{{ sortIndicator('credit_note_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Customer <span>{{ sortIndicator('customer_name') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('invoice_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Invoice <span>{{ sortIndicator('invoice_number') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('credit_note_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Date <span>{{ sortIndicator('credit_note_date') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Status <span>{{ sortIndicator('status') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Total <span>{{ sortIndicator('total') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <button @click="toggleSort('remaining_credit')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                        Remaining <span>{{ sortIndicator('remaining_credit') }}</span>
                                    </button>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Reference</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Subtotal</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Tax</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider" data-col-default-visible="false">Updated</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="cn in (props.creditNotes?.data || [])"
                                :key="cn.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(`/credit-notes/${cn.id}`)"
                                @keydown.enter.prevent="router.visit(`/credit-notes/${cn.id}`)"
                                @keydown.space.prevent="router.visit(`/credit-notes/${cn.id}`)"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ cn.credit_note_number }}</div>
                                    <div class="text-sm text-gray-500">{{ cn.title || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ cn.customer?.name || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ cn.invoice?.invoice_number || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatDate(cn.credit_note_date) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="getStatusBadgeClass(cn.status)" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full">
                                        {{ cn.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ formatCurrency(cn.total) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ formatCurrency(cn.remaining_credit) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ cn.reference || '-' }}</div>
                                </td>
                                <td class="px-6 py-4" style="display: none;">
                                    <div class="text-sm text-gray-900 truncate max-w-xs">{{ cn.description || '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ formatCurrency(cn.subtotal || 0) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ formatCurrency(cn.tax_amount || 0) }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ cn.created_at ? formatDate(cn.created_at) : '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap" style="display: none;">
                                    <div class="text-sm text-gray-900">{{ cn.updated_at ? formatDate(cn.updated_at) : '-' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" @click.stop>
                                    <div class="flex items-center gap-2">
                                        <Link
                                            v-if="canCreditNotesEdit"
                                            :href="`/credit-notes/${cn.id}/edit`"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="canCreditNotesDelete"
                                            @click="deleteCreditNote(cn)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="props.creditNotes?.links && props.creditNotes.links.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <Link
                                v-if="props.creditNotes?.prev_page_url"
                                :href="props.creditNotes.prev_page_url"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Previous
                            </Link>
                            <Link
                                v-if="props.creditNotes?.next_page_url"
                                :href="props.creditNotes.next_page_url"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                            >
                                Next
                            </Link>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ props.creditNotes.from ?? 0 }}</span>
                                    to
                                    <span class="font-medium">{{ props.creditNotes.to ?? 0 }}</span>
                                    of
                                    <span class="font-medium">{{ props.creditNotes.total ?? 0 }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <template v-for="(link, index) in (props.creditNotes?.links || [])" :key="index">
                                        <Link
                                            v-if="link.url"
                                            :href="link.url"
                                            v-html="link.label"
                                            :class="[
                                                'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                                link.active
                                                    ? 'z-10 bg-blue-50 border-blue-500 text-blue-600'
                                                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                                            ]"
                                        />
                                        <span
                                            v-else
                                            v-html="link.label"
                                            class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400 cursor-not-allowed"
                                        />
                                    </template>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { useNumberFormat } from '@/composables/useNumberFormat';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Edit, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Customer {
    id: number;
    name: string;
}

interface Invoice {
    id: number;
    invoice_number: string;
}

interface CreditNote {
    id: number;
    credit_note_number: string;
    title: string | null;
    reference?: string | null;
    description?: string | null;
    subtotal?: number;
    tax_amount?: number;
    created_at?: string;
    updated_at?: string;
    customer?: Customer;
    invoice?: Invoice | null;
    status: string;
    credit_note_date: string;
    total: number;
    remaining_credit: number;
}

interface PaginatedCreditNotes {
    data: CreditNote[];
    links?: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    prev_page_url?: string | null;
    next_page_url?: string | null;
    from?: number;
    to?: number;
    total?: number;
}

interface Props {
    creditNotes: PaginatedCreditNotes;
    customers: Customer[];
    currentCompany: { id: number; name: string };
    filters: {
        status?: string;
        customer_id?: string;
        search?: string;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
}

const props = defineProps<Props>();
const { formatCurrency } = useNumberFormat();

const canCreditNotesCreate = useAuthAbility('credit-notes', 'create');
const canCreditNotesEdit = useAuthAbility('credit-notes', 'edit');
const canCreditNotesDelete = useAuthAbility('credit-notes', 'delete');

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const customerFilter = ref(props.filters.customer_id || '');
const sortBy = ref(props.filters.sort_by || 'credit_note_number');
const sortDir = ref<'asc' | 'desc'>(props.filters.sort_dir || 'desc');

const applyFilters = () => {
    router.get('/credit-notes', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        customer_id: customerFilter.value || undefined,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([search, statusFilter, customerFilter], () => {
    applyFilters();
});

const clearFilters = () => {
    search.value = '';
    statusFilter.value = '';
    customerFilter.value = '';
};

const toggleSort = (field: string) => {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
};

const sortIndicator = (field: string): string => {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
};

const deleteCreditNote = (cn: CreditNote) => {
    if (confirm(`Are you sure you want to delete "${cn.credit_note_number}"?`)) {
        router.delete(`/credit-notes/${cn.id}`);
    }
};

const formatDate = (date: string): string => {
    return new Date(date).toLocaleDateString('en-ZA');
};

const getStatusBadgeClass = (status: string): string => {
    const classes: Record<string, string> = {
        draft: 'bg-gray-100 text-gray-800',
        submitted: 'bg-blue-100 text-blue-800',
        authorised: 'bg-green-100 text-green-800',
        paid: 'bg-purple-100 text-purple-800',
        voided: 'bg-red-100 text-red-800',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};
</script>
