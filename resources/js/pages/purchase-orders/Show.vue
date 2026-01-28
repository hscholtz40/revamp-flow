<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Package, Building2, Calendar, FileText, CheckCircle, XCircle, Download, Mail } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import purchaseOrders from '@/routes/purchase-orders';
import products from '@/routes/products';
import suppliers from '@/routes/suppliers';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    track_stock: boolean;
    track_batches: boolean;
    track_serial_numbers: boolean;
    batches?: Batch[];
    serialNumbers?: SerialNumber[];
}

interface Batch {
    id: number;
    batch_number: string;
    expiry_date: string | null;
}

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface PurchaseOrderItem {
    id: number;
    product: Product;
    quantity: number;
    unit_cost: number;
    total: number;
    quantity_received: number;
    description: string | null;
    product_batch_id: number | null;
    serial_number_ids: number[] | null;
}

interface Supplier {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface PurchaseOrder {
    id: number;
    po_number: string;
    supplier: Supplier;
    order_date: string;
    expected_delivery_date: string | null;
    received_date: string | null;
    status: 'draft' | 'sent' | 'received' | 'cancelled';
    subtotal: number;
    tax_amount: number;
    total: number;
    notes: string | null;
    terms: string | null;
    user: User | null;
    items: PurchaseOrderItem[];
    created_at: string;
    updated_at: string;
}

interface PdfTemplate {
    id: number;
    name: string;
}

interface Props {
    purchaseOrder: PurchaseOrder;
    pdfTemplates?: PdfTemplate[];
    defaultTemplateId?: number | null;
}

const props = defineProps<Props>();

const showReceiveModal = ref(false);
const showEmailModal = ref(false);
const showTemplateModal = ref(false);
const selectedTemplateId = ref<number | null>(props.defaultTemplateId ?? null);
const emailResult = ref({
    success: false,
    email: '',
    message: ''
});

const receiveForm = useForm({
    items: props.purchaseOrder.items.map(item => ({
        id: item.id,
        quantity_received: 0, // Start with 0 for new receiving
        product_batch_id: null, // Don't pre-select batch
        serial_number_ids: [], // Don't pre-select serial numbers
        custom_serial_numbers: [] as string[],
    })),
});

const statusForm = useForm({
    status: props.purchaseOrder.status,
});

const emailForm = useForm({
    email: props.purchaseOrder.supplier.email || '',
    customMessage: '',
    template_id: null as number | null,
});

function updateStatus(newStatus: string) {
    if (confirm(`Are you sure you want to change the status to "${newStatus}"?`)) {
        statusForm.status = newStatus;
        statusForm.put(purchaseOrders.updateStatus(props.purchaseOrder.id).url);
    }
}

function openReceiveModal() {
    receiveForm.items = props.purchaseOrder.items.map(item => ({
        id: item.id,
        quantity_received: 0, // Always start with 0 for new receiving
        product_batch_id: null, // Don't pre-select batch
        serial_number_ids: [], // Don't pre-select serial numbers
        custom_serial_numbers: [] as string[],
    }));
    showReceiveModal.value = true;
}

function addCustomSerial(index: number) {
    const item = receiveForm.items[index];
    if (!item.custom_serial_numbers) {
        item.custom_serial_numbers = [];
    }
    item.custom_serial_numbers.push('');
}

function removeCustomSerial(itemIndex: number, serialIndex: number) {
    receiveForm.items[itemIndex].custom_serial_numbers.splice(serialIndex, 1);
}

function getProductSerialNumbers(itemId: number): SerialNumber[] {
    const purchaseOrderItem = props.purchaseOrder.items.find(i => i.id === itemId);
    if (!purchaseOrderItem || !purchaseOrderItem.product) {
        return [];
    }
    const product = purchaseOrderItem.product;
    
    // Try different possible property names (camelCase and snake_case)
    const serials = product.serialNumbers || (product as any).serial_numbers || [];
    
    if (!Array.isArray(serials)) {
        return [];
    }
    
    // Filter out serial numbers that were already received in previous receiving sessions
    const alreadyReceivedSerialIds = purchaseOrderItem.serial_number_ids || [];
    
    // Only return serial numbers that are available and haven't been received yet
    return serials.filter(serial => 
        serial.status === 'available' || serial.status === 'in_stock'
    ).filter(serial => 
        !alreadyReceivedSerialIds.includes(serial.id)
    );
}

function submitReceiveItems() {
    receiveForm.post(purchaseOrders.receiveItems(props.purchaseOrder.id).url, {
        onSuccess: () => {
            showReceiveModal.value = false;
        },
    });
}

function getStatusColor(status: string) {
    return {
        'draft': 'bg-gray-100 text-gray-800',
        'sent': 'bg-blue-100 text-blue-800',
        'received': 'bg-green-100 text-green-800',
        'cancelled': 'bg-red-100 text-red-800',
    }[status] || 'bg-gray-100 text-gray-800';
}

const isFullyReceived = computed(() => {
    return props.purchaseOrder.items.every(item => item.quantity_received >= item.quantity);
});

const canReceive = computed(() => {
    return props.purchaseOrder.status !== 'cancelled' && props.purchaseOrder.status !== 'received';
});

const downloadPDF = () => {
    const url = new URL(purchaseOrders.downloadPdf(props.purchaseOrder.id).url, window.location.origin);
    if (selectedTemplateId.value) {
        url.searchParams.set('template_id', selectedTemplateId.value.toString());
    }
    window.open(url.toString(), '_blank');
    showTemplateModal.value = false;
    selectedTemplateId.value = null;
};

const sendEmail = () => {
    emailForm.post(purchaseOrders.email(props.purchaseOrder.id).url, {
        onSuccess: (page) => {
            showEmailModal.value = false;
            emailResult.value = {
                success: true,
                email: emailForm.email,
                message: page.props.flash?.success || 'Email sent successfully'
            };
            emailForm.reset();
        },
        onError: (errors) => {
            emailResult.value = {
                success: false,
                email: emailForm.email,
                message: errors.message || 'Failed to send email'
            };
        }
    });
};
</script>

<template>
    <Head :title="`Purchase Order ${props.purchaseOrder.po_number}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Purchase Orders', href: purchaseOrders.index().url },
        { title: props.purchaseOrder.po_number, href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="purchaseOrders.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Purchase Orders
                </Link>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.purchaseOrder.po_number }}</h1>
                        <p class="text-gray-600">Purchase Order Details</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="getStatusColor(props.purchaseOrder.status)" class="inline-flex rounded-full px-3 py-1 text-sm font-semibold capitalize">
                            {{ props.purchaseOrder.status }}
                        </span>
                        <button
                            v-if="!props.pdfTemplates || props.pdfTemplates.length === 0"
                            @click="downloadPDF"
                            class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Download class="h-4 w-4" />
                            Download PDF
                        </button>
                        <button
                            v-else
                            @click="showTemplateModal = true"
                            class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Download class="h-4 w-4" />
                            Download PDF
                        </button>
                        <button
                            @click="showEmailModal = true"
                            class="flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <Mail class="h-4 w-4" />
                            Email
                        </button>
                        <button
                            v-if="canReceive"
                            @click="openReceiveModal"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Receive Items
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <!-- Main Content -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Supplier Information -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Supplier Information</h2>
                        <div class="flex items-center gap-3">
                            <Building2 class="h-5 w-5 text-gray-400" />
                            <div>
                                <div class="font-medium text-gray-900">
                                    <Link
                                        v-if="props.purchaseOrder.supplier && props.purchaseOrder.supplier.id"
                                        :href="suppliers.show(props.purchaseOrder.supplier.id).url"
                                        class="text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ props.purchaseOrder.supplier.name }}
                                    </Link>
                                    <span v-else>{{ props.purchaseOrder.supplier?.name || '-' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Items</h2>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Quantity</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Cost</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Received</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="item in props.purchaseOrder.items" :key="item.id">
                                        <td class="whitespace-nowrap px-4 py-4">
                                            <div class="flex items-center gap-2">
                                                <Package class="h-4 w-4 text-gray-400" />
                                                <div>
                                                    <div class="font-medium text-gray-900">
                                                        <Link
                                                            v-if="item.product && item.product.id"
                                                            :href="products.show(item.product.id).url"
                                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                                        >
                                                            {{ item.product.name }}
                                                        </Link>
                                                        <span v-else>{{ item.product.name }}</span>
                                                    </div>
                                                    <div v-if="item.product.sku" class="text-xs text-gray-500">{{ item.product.sku }}</div>
                                                    <div v-if="item.description" class="text-xs text-gray-500">{{ item.description }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-gray-900">
                                            {{ item.quantity }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm text-gray-900">
                                            R{{ Number(item.unit_cost).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                            <span :class="item.quantity_received >= item.quantity ? 'text-green-600' : 'text-yellow-600'" class="font-medium">
                                                {{ item.quantity_received }} / {{ item.quantity }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-medium text-gray-900">
                                            R{{ Number(item.total).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-right text-sm font-medium text-gray-900">Subtotal</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-medium text-gray-900">
                                            R{{ Number(props.purchaseOrder.subtotal).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                        </td>
                                    </tr>
                                    <tr v-if="props.purchaseOrder.tax_amount > 0">
                                        <td colspan="4" class="px-4 py-4 text-right text-sm font-medium text-gray-900">Tax</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-sm font-medium text-gray-900">
                                            R{{ Number(props.purchaseOrder.tax_amount).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-4 py-4 text-right text-lg font-bold text-gray-900">Total</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-right text-lg font-bold text-gray-900">
                                            R{{ Number(props.purchaseOrder.total).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Notes and Terms -->
                    <div v-if="props.purchaseOrder.notes || props.purchaseOrder.terms" class="grid gap-6 md:grid-cols-2">
                        <div v-if="props.purchaseOrder.notes" class="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 class="mb-2 text-sm font-semibold text-gray-900">Notes</h3>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ props.purchaseOrder.notes }}</p>
                        </div>
                        <div v-if="props.purchaseOrder.terms" class="rounded-lg border border-gray-200 bg-white p-6">
                            <h3 class="mb-2 text-sm font-semibold text-gray-900">Terms</h3>
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ props.purchaseOrder.terms }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Status Management -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h3 class="mb-4 text-sm font-semibold text-gray-900">Status Management</h3>
                        <div class="space-y-2">
                            <button
                                v-for="status in ['draft', 'sent', 'received', 'cancelled']"
                                :key="status"
                                @click="updateStatus(status)"
                                :class="[
                                    'w-full rounded-md px-3 py-2 text-left text-sm font-medium transition-colors',
                                    props.purchaseOrder.status === status
                                        ? 'bg-blue-100 text-blue-800 border border-blue-200'
                                        : 'bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-200'
                                ]"
                            >
                                {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                            </button>
                        </div>
                    </div>

                    <!-- Order Details -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h3 class="mb-4 text-sm font-semibold text-gray-900">Order Details</h3>
                        <div class="space-y-3 text-sm">
                            <div>
                                <div class="text-gray-500">Order Date</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.purchaseOrder.order_date).toLocaleDateString() }}</div>
                            </div>
                            <div v-if="props.purchaseOrder.expected_delivery_date">
                                <div class="text-gray-500">Expected Delivery</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.purchaseOrder.expected_delivery_date).toLocaleDateString() }}</div>
                            </div>
                            <div v-if="props.purchaseOrder.received_date">
                                <div class="text-gray-500">Received Date</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.purchaseOrder.received_date).toLocaleDateString() }}</div>
                            </div>
                            <div v-if="props.purchaseOrder.user">
                                <div class="text-gray-500">Created By</div>
                                <div class="font-medium text-gray-900">{{ props.purchaseOrder.user.name }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Created</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.purchaseOrder.created_at).toLocaleDateString() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receive Items Modal -->
        <div
            v-if="showReceiveModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
            @click.self="showReceiveModal = false"
        >
            <div class="w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl">
                <h2 class="mb-4 text-xl font-bold text-gray-900">Receive Items</h2>
                <form @submit.prevent="submitReceiveItems" class="space-y-4">
                    <div class="max-h-96 space-y-3 overflow-y-auto">
                        <div
                            v-for="(item, index) in receiveForm.items"
                            :key="item.id"
                            class="rounded-lg border border-gray-200 p-4"
                        >
                            <div class="mb-2 font-medium text-gray-900">
                                {{ props.purchaseOrder.items.find(i => i.id === item.id)?.product.name }}
                            </div>
                            <div class="mb-2 text-sm text-gray-500">
                                Ordered: {{ props.purchaseOrder.items.find(i => i.id === item.id)?.quantity }} | 
                                Already Received: {{ props.purchaseOrder.items.find(i => i.id === item.id)?.quantity_received }}
                            </div>
                            
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Quantity Received
                                    </label>
                                    <input
                                        v-model.number="receiveForm.items[index].quantity_received"
                                        type="number"
                                        min="0"
                                        :max="(props.purchaseOrder.items.find(i => i.id === item.id)?.quantity || 0) - (props.purchaseOrder.items.find(i => i.id === item.id)?.quantity_received || 0)"
                                        class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                <!-- Batch Selection -->
                                <div v-if="props.purchaseOrder.items.find(i => i.id === item.id)?.product.track_batches">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Batch (Optional)
                                    </label>
                                    <select
                                        v-model.number="receiveForm.items[index].product_batch_id"
                                        class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option :value="null">No Batch</option>
                                        <option
                                            v-for="batch in props.purchaseOrder.items.find(i => i.id === item.id)?.product.batches || []"
                                            :key="batch.id"
                                            :value="batch.id"
                                        >
                                            {{ batch.batch_number }}
                                            <span v-if="batch.expiry_date">
                                                (Exp: {{ new Date(batch.expiry_date).toLocaleDateString() }})
                                            </span>
                                        </option>
                                    </select>
                                </div>

                                <!-- Serial Number Selection -->
                                <div v-if="props.purchaseOrder.items.find(i => i.id === item.id)?.product.track_serial_numbers">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Serial Numbers
                                        <span class="text-xs text-gray-500">
                                            (Select or enter {{ receiveForm.items[index].quantity_received || 0 }} serial number(s))
                                        </span>
                                    </label>
                                    
                                    <!-- Existing Serial Numbers -->
                                    <div v-if="getProductSerialNumbers(item.id) && getProductSerialNumbers(item.id).length > 0" class="mb-3 max-h-40 space-y-2 overflow-y-auto rounded border border-gray-300 p-2">
                                        <label
                                            v-for="serial in getProductSerialNumbers(item.id)"
                                            :key="serial.id"
                                            class="flex items-center gap-2 rounded px-2 py-1 hover:bg-gray-50"
                                        >
                                            <input
                                                type="checkbox"
                                                :value="serial.id"
                                                v-model="receiveForm.items[index].serial_number_ids"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                                :disabled="(receiveForm.items[index].serial_number_ids.length + (receiveForm.items[index].custom_serial_numbers?.filter(s => s.trim()).length || 0)) >= (receiveForm.items[index].quantity_received || 0) && !receiveForm.items[index].serial_number_ids.includes(serial.id)"
                                            />
                                            <span class="text-sm text-gray-900">{{ serial.serial_number }}</span>
                                        </label>
                                    </div>
                                    <div v-else class="mb-3 text-sm text-gray-500 italic">
                                        No existing serial numbers found for this product.
                                    </div>
                                    
                                    <!-- Custom Serial Number Entry -->
                                    <div class="mb-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <label class="block text-sm font-medium text-gray-700">
                                                Enter Custom Serial Numbers
                                            </label>
                                            <button
                                                type="button"
                                                @click="addCustomSerial(index)"
                                                class="text-sm text-blue-600 hover:text-blue-800"
                                            >
                                                + Add Serial
                                            </button>
                                        </div>
                                        <div v-if="receiveForm.items[index].custom_serial_numbers && receiveForm.items[index].custom_serial_numbers.length > 0" class="space-y-2">
                                            <div
                                                v-for="(customSerial, serialIndex) in receiveForm.items[index].custom_serial_numbers"
                                                :key="serialIndex"
                                                class="flex items-center gap-2"
                                            >
                                                <input
                                                    v-model="receiveForm.items[index].custom_serial_numbers[serialIndex]"
                                                    type="text"
                                                    placeholder="Enter serial number"
                                                    class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                                    :disabled="(receiveForm.items[index].serial_number_ids.length + (receiveForm.items[index].custom_serial_numbers?.filter((s, idx) => idx !== serialIndex && s.trim()).length || 0)) >= (receiveForm.items[index].quantity_received || 0)"
                                                />
                                                <button
                                                    type="button"
                                                    @click="removeCustomSerial(index, serialIndex)"
                                                    class="text-red-600 hover:text-red-800"
                                                >
                                                    ×
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="mt-1 text-xs text-gray-500">
                                        Total: {{ (receiveForm.items[index].serial_number_ids.length + (receiveForm.items[index].custom_serial_numbers?.filter(s => s.trim()).length || 0)) }} / {{ receiveForm.items[index].quantity_received || 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t pt-4">
                        <button
                            type="button"
                            @click="showReceiveModal = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="receiveForm.processing"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50"
                        >
                            {{ receiveForm.processing ? 'Receiving...' : 'Receive Items' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Email Modal -->
        <div v-if="showEmailModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Email Purchase Order</h3>
                    </div>
                    <form @submit.prevent="sendEmail" class="px-6 py-4">
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Email Address</label>
                            <input
                                v-model="emailForm.email"
                                type="email"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="supplier@example.com"
                            />
                            <div v-if="emailForm.errors.email" class="mt-1 text-sm text-red-600">{{ emailForm.errors.email }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Custom Message (Optional)</label>
                            <textarea
                                v-model="emailForm.customMessage"
                                rows="4"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Add a custom message to include in the email..."
                            ></textarea>
                        </div>
                        <div v-if="props.pdfTemplates && props.pdfTemplates.length > 0" class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700">PDF Template</label>
                            <select
                                v-model="emailForm.template_id"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">Default Template</option>
                                <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                            <button
                                type="button"
                                @click="showEmailModal = false; emailForm.reset();"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="emailForm.processing"
                                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ emailForm.processing ? 'Sending...' : 'Send Email' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Template Selection Modal -->
        <div v-if="showTemplateModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="w-full max-w-md rounded-lg bg-white shadow-xl">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-lg font-semibold text-gray-900">Select PDF Template</h3>
                    </div>
                    <div class="px-6 py-4">
                        <div v-if="props.pdfTemplates && props.pdfTemplates.length > 0" class="mb-4">
                            <label class="mb-2 block text-sm font-medium text-gray-700">Choose Template</label>
                            <select
                                v-model="selectedTemplateId"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">Default Template</option>
                                <option v-for="template in props.pdfTemplates" :key="template.id" :value="template.id">
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <div v-else class="mb-4 text-sm text-gray-500">
                            No custom templates available. The default template will be used.
                        </div>
                        <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4">
                            <button
                                type="button"
                                @click="showTemplateModal = false; selectedTemplateId = null;"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                @click="downloadPDF"
                                class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                            >
                                Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

