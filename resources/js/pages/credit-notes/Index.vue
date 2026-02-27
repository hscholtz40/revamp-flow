<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Eye, Edit, Trash2, FileText } from 'lucide-vue-next';

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
    customer: Customer;
    invoice: Invoice | null;
    status: string;
    status_color: string;
    credit_note_date: string;
    total: number;
    remaining_credit: number;
    formatted_total: string;
}

interface Props {
    creditNotes: {
        data: CreditNote[];
        links: any[];
        prev_page_url: string | null;
        next_page_url: string | null;
        current_page: number;
        last_page: number;
    };
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

const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const customerFilter = ref(props.filters.customer_id || '');
const sortBy = ref(props.filters.sort_by || 'credit_note_number');
const sortDir = ref<'asc' | 'desc'>(props.filters.sort_dir || 'asc');

function applyFilters() {
    router.get('/credit-notes', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        customer_id: customerFilter.value || undefined,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    }, { preserveState: true, replace: true });
}

function toggleSort(field: string) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
}

function sortIndicator(field: string): string {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

function deleteCreditNote(cn: CreditNote) {
    if (confirm(`Are you sure you want to delete "${cn.credit_note_number}"?`)) {
        router.delete(`/credit-notes/${cn.id}`);
    }
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(amount);
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZA');
}

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-blue-100 text-blue-800',
    authorised: 'bg-green-100 text-green-800',
    paid: 'bg-purple-100 text-purple-800',
    voided: 'bg-red-100 text-red-800',
};
</script>

<template>
    <Head title="Credit Notes" />

    <AppLayout :breadcrumbs="[{ title: 'Credit Notes', href: '/credit-notes' }]">
        <div class="p-4">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Credit Notes</h1>
                    <p class="text-gray-600">Manage credit notes for your invoices</p>
                </div>
                <Link href="/credit-notes/create" class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                    <Plus class="h-4 w-4" />
                    New Credit Note
                </Link>
            </div>

            <div class="mb-4 flex flex-wrap gap-3">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search credit notes..."
                    class="rounded border border-gray-300 px-3 py-2 text-sm"
                    @keyup.enter="applyFilters"
                />
                <select v-model="statusFilter" @change="applyFilters" class="rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="authorised">Authorised</option>
                    <option value="paid">Paid</option>
                    <option value="voided">Voided</option>
                </select>
                <select v-model="customerFilter" @change="applyFilters" class="rounded border border-gray-300 px-3 py-2 text-sm">
                    <option value="">All Customers</option>
                    <option v-for="c in props.customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div v-if="props.creditNotes.data.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <FileText class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No credit notes found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first credit note.</p>
                <div class="mt-6">
                    <Link href="/credit-notes/create" class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                        <Plus class="h-4 w-4" />
                        New Credit Note
                    </Link>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('credit_note_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Number <span>{{ sortIndicator('credit_note_number') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('customer_name')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Customer <span>{{ sortIndicator('customer_name') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('invoice_number')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Invoice <span>{{ sortIndicator('invoice_number') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('credit_note_date')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Date <span>{{ sortIndicator('credit_note_date') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('total')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Total <span>{{ sortIndicator('total') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('remaining_credit')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Remaining <span>{{ sortIndicator('remaining_credit') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <button @click="toggleSort('status')" class="inline-flex items-center gap-1 hover:text-gray-700">
                                    Status <span>{{ sortIndicator('status') }}</span>
                                </button>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="cn in props.creditNotes.data" :key="cn.id" class="hover:bg-gray-50">
                            <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                <Link :href="`/credit-notes/${cn.id}`" class="text-blue-600 hover:text-blue-900">
                                    {{ cn.credit_note_number }}
                                </Link>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ cn.customer?.name }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                <Link v-if="cn.invoice" :href="`/invoices/${cn.invoice.id}`" class="text-blue-600 hover:text-blue-900">
                                    {{ cn.invoice.invoice_number }}
                                </Link>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ formatDate(cn.credit_note_date) }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">{{ formatCurrency(cn.total) }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ formatCurrency(cn.remaining_credit) }}</td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span :class="[statusColors[cn.status] || 'bg-gray-100 text-gray-800', 'inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize']">
                                    {{ cn.status }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="`/credit-notes/${cn.id}`" class="text-blue-600 hover:text-blue-900">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                    <Link :href="`/credit-notes/${cn.id}/edit`" class="text-indigo-600 hover:text-indigo-900">
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                    <button @click="deleteCreditNote(cn)" class="text-red-600 hover:text-red-900">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="props.creditNotes.last_page > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3">
                    <div class="flex gap-2">
                        <Link v-if="props.creditNotes.prev_page_url" :href="props.creditNotes.prev_page_url" class="rounded border px-3 py-1 text-sm hover:bg-gray-50">Previous</Link>
                        <Link v-if="props.creditNotes.next_page_url" :href="props.creditNotes.next_page_url" class="rounded border px-3 py-1 text-sm hover:bg-gray-50">Next</Link>
                    </div>
                    <span class="text-sm text-gray-500">Page {{ props.creditNotes.current_page }} of {{ props.creditNotes.last_page }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
