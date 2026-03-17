<template>
    <Head :title="`Credit Note ${creditNote.credit_note_number}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Credit Notes', href: '/credit-notes' },
        { title: creditNote.credit_note_number, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Status Management</h2>
                    <p class="text-sm text-gray-600">Update credit note status and track progress</p>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-medium text-gray-700">Current Status:</span>
                            <div class="flex items-center gap-2">
                                <button
                                    v-for="status in statusOptions"
                                    :key="status.value"
                                    @click="updateStatus(status.value)"
                                    :class="[
                                        'px-3 py-1 text-sm font-medium rounded-md transition-colors',
                                        creditNote.status === status.value
                                            ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-200'
                                    ]"
                                >
                                    {{ status.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ creditNote.credit_note_number }}</h1>
                        <p v-if="creditNote.title" class="mt-1 text-sm text-gray-500">{{ creditNote.title }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="statusBadgeClass" class="inline-flex rounded-full px-3 py-1 text-sm font-semibold">
                            {{ formatStatus(creditNote.status) }}
                        </span>
                        <button
                            v-if="canAddRefund"
                            type="button"
                            @click="showRefundModal = true"
                            class="inline-flex items-center gap-2 rounded-md bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700"
                        >
                            <Wallet class="h-4 w-4" />
                            Add Refund
                        </button>
                        <Link
                            :href="`/credit-notes/${creditNote.id}/edit`"
                            class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Edit class="h-4 w-4" />
                            Edit
                        </Link>
                        <button
                            type="button"
                            @click="confirmDelete"
                            class="inline-flex items-center gap-2 rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
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
                                        <Link :href="`/customers/${creditNote.customer.id}`" class="text-blue-600 hover:text-blue-800 hover:underline">
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

                    <div v-if="creditNote.invoice" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Linked Invoice</h2>
                            <p class="text-sm text-gray-600">Invoice this credit note applies to</p>
                        </div>
                        <div class="p-6">
                            <Link :href="`/invoices/${creditNote.invoice.id}`" class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 hover:underline">
                                <FileText class="h-4 w-4" />
                                {{ creditNote.invoice.invoice_number }}
                                <span v-if="creditNote.invoice.title" class="text-gray-500">- {{ creditNote.invoice.title }}</span>
                                <span class="text-gray-500">({{ formatCurrency(creditNote.invoice.total) }})</span>
                            </Link>
                        </div>
                    </div>

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
                                            <span>{{ item.description }}{{ (item.product?.sku || item.product?.barcode) ? ` (${item.product.sku || item.product.barcode})` : '' }}</span>
                                        </td>
                                        <td class="px-3 py-3 text-center text-sm text-gray-900">{{ item.quantity }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm text-gray-900">{{ formatCurrency(item.unit_price) }}</td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm">
                                            <span v-if="(item.discount_percentage || 0) > 0" class="text-red-600">{{ item.discount_percentage }}%</span>
                                            <span v-else-if="(item.discount_amount || 0) > 0" class="text-red-600">{{ formatCurrency(item.discount_amount) }}</span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-900">
                                            <span v-if="item.tax_rate" class="inline-flex items-center rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700">
                                                {{ item.tax_rate.name }} ({{ item.tax_rate.rate }}%)
                                            </span>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-3 text-right text-sm font-medium text-gray-900">{{ formatCurrency(item.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-if="creditNote.notes" class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                        </div>
                        <div class="p-6">
                            <p class="whitespace-pre-wrap text-sm text-gray-600">{{ creditNote.notes }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
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
                            <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                                <span class="text-gray-600">Rounding Adjustment</span>
                                <span class="font-medium">{{ formatCurrency(roundingAdjustment) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2 text-lg font-semibold">
                                <span>Total</span>
                                <span>{{ formatCurrency(creditNote.total) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Refunded</span>
                                <span class="font-medium text-purple-700">-{{ formatCurrency(totalRefunded) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-2">
                                <span class="text-gray-600">Remaining credit</span>
                                <span class="font-semibold text-green-700">{{ formatCurrency(creditNote.remaining_credit) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                            <h2 class="text-lg font-semibold text-gray-900">Refund Payments</h2>
                            <p class="text-sm text-gray-600">Optional refunds issued against this credit note</p>
                        </div>
                        <div class="p-6">
                            <div v-if="creditNote.payments && creditNote.payments.length > 0" class="space-y-2">
                                <div v-for="payment in creditNote.payments" :key="payment.id" class="flex items-center justify-between rounded border border-gray-200 p-3">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ formatCurrency(payment.amount) }}</p>
                                        <p class="text-xs text-gray-500">{{ payment.payment_method.toUpperCase() }} - {{ formatDate(payment.payment_date) }}</p>
                                        <p v-if="payment.notes" class="text-xs text-gray-500">{{ payment.notes }}</p>
                                    </div>
                                    <button type="button" @click="removeRefundPayment(payment.id)" class="text-xs text-red-600 hover:text-red-800">
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500">No refund payments recorded.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showRefundModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click="showRefundModal = false">
            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
                <h3 class="text-lg font-semibold text-gray-900">Add Refund Payment</h3>
                <p class="mt-1 text-sm text-gray-500">Remaining credit: {{ formatCurrency(creditNote.remaining_credit) }}</p>

                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Amount</label>
                        <input
                            v-model="refundForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="creditNote.remaining_credit"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p v-if="refundForm.errors.amount" class="mt-1 text-xs text-red-600">{{ refundForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Payment Method</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="method in paymentMethodOptions"
                                :key="method.value"
                                type="button"
                                @click="refundForm.payment_method = method.value"
                                :class="[
                                    'rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                    refundForm.payment_method === method.value
                                        ? 'border-blue-500 bg-blue-50 text-blue-700'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                ]"
                            >
                                {{ method.label }}
                            </button>
                        </div>
                        <p v-if="refundForm.errors.payment_method" class="mt-1 text-xs text-red-600">{{ refundForm.errors.payment_method }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Refund Date</label>
                        <input v-model="refundForm.payment_date" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="refundForm.errors.payment_date" class="mt-1 text-xs text-red-600">{{ refundForm.errors.payment_date }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notes (optional)</label>
                        <textarea v-model="refundForm.notes" rows="3" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                        <p v-if="refundForm.errors.notes" class="mt-1 text-xs text-red-600">{{ refundForm.errors.notes }}</p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="showRefundModal = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700">
                        Cancel
                    </button>
                    <button type="button" @click="addRefundPayment" :disabled="refundForm.processing" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700 disabled:opacity-50">
                        {{ refundForm.processing ? 'Saving...' : 'Add Refund' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Edit, Trash2, FileText, Wallet } from 'lucide-vue-next';

interface Payment {
    id: number;
    amount: number;
    payment_method: string;
    payment_date: string;
    notes?: string | null;
}

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
        payments: Payment[];
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
const showRefundModal = ref(false);

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'submitted', label: 'Submitted' },
    { value: 'authorised', label: 'Authorised' },
    { value: 'paid', label: 'Paid' },
    { value: 'voided', label: 'Voided' },
];

const paymentMethodOptions = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'eft', label: 'EFT' },
];

const refundForm = useForm({
    amount: Number(creditNote.value.remaining_credit || 0),
    payment_method: '',
    payment_date: new Date().toISOString().split('T')[0],
    notes: '',
});

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-blue-100 text-blue-800',
    authorised: 'bg-green-100 text-green-800',
    paid: 'bg-purple-100 text-purple-800',
    voided: 'bg-red-100 text-red-800',
};

const statusBadgeClass = computed(() => statusColors[creditNote.value.status] ?? 'bg-gray-100 text-gray-800');
const canAddRefund = computed(() => Number(creditNote.value.remaining_credit || 0) > 0 && creditNote.value.status !== 'voided');
const totalRefunded = computed(() => (creditNote.value.payments || []).reduce((sum, p) => sum + Number(p.amount || 0), 0));
const roundingAdjustment = computed(() =>
    (creditNote.value.line_items || []).reduce((sum, item) => {
        if ((item.description || '').trim().toLowerCase() !== 'rounding adjustment') {
            return sum;
        }
        return sum + (Number(item.total) || (Number(item.quantity) || 0) * (Number(item.unit_price) || 0));
    }, 0)
);

function updateStatus(status: string) {
    if (creditNote.value.status === status) return;
    router.patch(`/credit-notes/${creditNote.value.id}/status`, { status });
}

function addRefundPayment() {
    refundForm.post(`/credit-notes/${creditNote.value.id}/payments`, {
        onSuccess: () => {
            showRefundModal.value = false;
            refundForm.reset();
            refundForm.amount = Number(creditNote.value.remaining_credit || 0);
            refundForm.payment_date = new Date().toISOString().split('T')[0];
        },
    });
}

function removeRefundPayment(paymentId: number) {
    if (!confirm('Remove this refund payment?')) return;
    router.delete(`/credit-notes/${creditNote.value.id}/payments/${paymentId}`);
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR' }).format(amount ?? 0);
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZA');
}

function formatStatus(status: string): string {
    return status.replace('_', ' ').toUpperCase();
}

function confirmDelete() {
    if (confirm(`Are you sure you want to delete credit note "${creditNote.value.credit_note_number}"?`)) {
        router.delete(`/credit-notes/${creditNote.value.id}`);
    }
}
</script>
