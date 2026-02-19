<template>
    <Head :title="`Credit Note ${creditNote.credit_note_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Credit Notes', href: '/credit-notes' },
        { title: creditNote.credit_note_number, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Header -->
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ creditNote.credit_note_number }}</h1>
                        <p v-if="creditNote.title" class="mt-1 text-sm text-gray-500">{{ creditNote.title }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            :class="statusBadgeClass"
                            class="inline-flex rounded-full px-3 py-1 text-sm font-semibold"
                        >
                            {{ creditNote.status.charAt(0).toUpperCase() + creditNote.status.slice(1) }}
                        </span>
                        <div v-if="statusUpdateOptions.length > 0" class="relative">
                            <select
                                v-model="selectedStatus"
                                @change="onStatusChange"
                                class="rounded-md border border-gray-300 bg-white px-3 py-2 pr-8 text-sm font-medium text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <option value="" disabled>Update status</option>
                                <option
                                    v-for="opt in statusUpdateOptions"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                            <ChevronDown class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500" />
                        </div>
                        <Link
                            :href="`/credit-notes/${creditNote.id}/edit`"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            <Edit class="h-4 w-4" />
                            Edit
                        </Link>
                        <button
                            type="button"
                            @click="confirmDelete"
                            class="inline-flex items-center gap-2 rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Customer -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Customer</h2>
                            <p class="text-sm text-gray-600">Customer for this credit note</p>
                        </div>
                        <div class="p-6">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Customer</label>
                                    <p class="text-sm text-gray-900">
                                        <Link
                                            :href="`/customers/${creditNote.customer.id}`"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ creditNote.customer.name }}
                                        </Link>
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Email</label>
                                    <p class="text-sm text-gray-900">{{ creditNote.customer.email || '—' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Linked invoice -->
                    <div
                        v-if="creditNote.invoice"
                        class="rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Linked Invoice</h2>
                            <p class="text-sm text-gray-600">Invoice this credit note applies to</p>
                        </div>
                        <div class="p-6">
                            <Link
                                :href="`/invoices/${creditNote.invoice.id}`"
                                class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                <FileText class="h-4 w-4" />
                                {{ creditNote.invoice.invoice_number }}
                                <span v-if="creditNote.invoice.title" class="text-gray-500">– {{ creditNote.invoice.title }}</span>
                                <span class="text-gray-500">({{ formatCurrency(creditNote.invoice.total) }})</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Line items -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                            <p class="text-sm text-gray-600">Credit note lines and amounts</p>
                        </div>
                        <div class="overflow-x-auto p-6">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                                        <th class="w-16 px-3 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">Qty</th>
                                        <th class="w-24 px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit price</th>
                                        <th class="w-24 px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Discount</th>
                                        <th class="w-28 px-3 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tax</th>
                                        <th class="w-24 px-3 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="item in creditNote.line_items" :key="item.id">
                                        <td class="px-3 py-3 text-sm text-gray-900">
                                            <span>{{ item.description }}</span>
                                            <span
                                                v-if="item.product"
                                                class="ml-1 text-xs text-gray-500"
                                            >
                                                {{ item.product.sku ? `(${item.product.sku})` : '' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-center text-sm text-gray-900">{{ item.quantity }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-900">
                                            {{ formatCurrency(item.unit_price) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm">
                                            <span v-if="(item.discount_percentage || 0) > 0" class="text-red-600">{{ item.discount_percentage }}%</span>
                                            <span v-else-if="(item.discount_amount || 0) > 0" class="text-red-600">{{ formatCurrency(item.discount_amount) }}</span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-900">
                                            <span
                                                v-if="item.tax_rate"
                                                class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700"
                                            >
                                                {{ item.tax_rate.name }} ({{ item.tax_rate.rate }}%)
                                            </span>
                                            <span v-else class="text-gray-400">—</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm font-medium text-gray-900">
                                            {{ formatCurrency(item.total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div
                        v-if="creditNote.notes"
                        class="rounded-lg border border-gray-200 bg-white shadow-sm"
                    >
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                        </div>
                        <div class="p-6">
                            <p class="whitespace-pre-wrap text-sm text-gray-600">{{ creditNote.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Details -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Details</h2>
                            <p class="text-sm text-gray-600">Credit note information</p>
                        </div>
                        <div class="space-y-4 p-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Date</label>
                                <p class="mt-1 text-sm text-gray-900">{{ formatDate(creditNote.credit_note_date) }}</p>
                            </div>
                            <div v-if="creditNote.reference">
                                <label class="block text-sm font-medium text-gray-500">Reference</label>
                                <p class="mt-1 text-sm text-gray-900">{{ creditNote.reference }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Company</label>
                                <p class="mt-1 text-sm text-gray-900">{{ creditNote.company.name }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Totals</h2>
                            <p class="text-sm text-gray-600">Amounts and remaining credit</p>
                        </div>
                        <div class="space-y-2 p-6">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-medium">{{ formatCurrency(creditNote.subtotal) }}</span>
                            </div>
                            <div v-if="(creditNote.discount_amount || 0) > 0" class="flex justify-between">
                                <span class="text-gray-600">Discount</span>
                                <span class="font-medium text-red-600">-{{ formatCurrency(creditNote.discount_amount) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax</span>
                                <span class="font-medium">{{ formatCurrency(creditNote.tax_amount) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-semibold">
                                <span>Total</span>
                                <span>{{ formatCurrency(creditNote.total) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2">
                                <span class="text-gray-600">Remaining credit</span>
                                <span class="font-semibold text-green-700">{{ formatCurrency(creditNote.remaining_credit) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Edit, Trash2, FileText, ChevronDown } from 'lucide-vue-next';

interface Props {
    creditNote: {
        id: number;
        credit_note_number: string;
        title: string | null;
        description: string | null;
        status: string;
        status_color: string;
        credit_note_date: string;
        subtotal: number;
        discount_amount: number;
        tax_amount: number;
        total: number;
        remaining_credit: number;
        reference: string | null;
        notes: string | null;
        formatted_total: string;
        customer: { id: number; name: string; email: string };
        invoice: { id: number; invoice_number: string; title: string; total: number } | null;
        company: { id: number; name: string };
        line_items: Array<{
            id: number;
            description: string;
            quantity: number;
            unit_price: number;
            discount_amount: number;
            discount_percentage: number;
            tax_amount: number;
            total: number;
            product: { id: number; name: string; sku: string } | null;
            tax_rate: { id: number; name: string; rate: number } | null;
        }>;
    };
}

const props = defineProps<Props>();

const creditNote = computed(() => props.creditNote);

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-blue-100 text-blue-800',
    authorised: 'bg-green-100 text-green-800',
    paid: 'bg-purple-100 text-purple-800',
    voided: 'bg-red-100 text-red-800',
};

const statusBadgeClass = computed(() => statusColors[creditNote.value.status] ?? 'bg-gray-100 text-gray-800');

const statusTransitions: Record<string, { value: string; label: string }[]> = {
    draft: [{ value: 'submitted', label: 'Submit' }, { value: 'voided', label: 'Void' }],
    submitted: [{ value: 'authorised', label: 'Authorise' }, { value: 'voided', label: 'Void' }],
    authorised: [{ value: 'paid', label: 'Mark as paid' }, { value: 'voided', label: 'Void' }],
    paid: [{ value: 'voided', label: 'Void' }],
    voided: [],
};

const statusUpdateOptions = computed(() => statusTransitions[creditNote.value.status] ?? []);

const selectedStatus = ref('');

function onStatusChange() {
    if (!selectedStatus.value) return;
    router.patch(`/credit-notes/${creditNote.value.id}/status`, { status: selectedStatus.value });
    selectedStatus.value = '';
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(amount ?? 0);
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZA');
}

function confirmDelete() {
    if (confirm(`Are you sure you want to delete credit note "${creditNote.value.credit_note_number}"?`)) {
        router.delete(`/credit-notes/${creditNote.value.id}`);
    }
}
</script>
