<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, ArrowUp, ArrowDown, Settings, Package, User, Calendar, FileText, DollarSign, Hash } from 'lucide-vue-next';
import stockMovements from '@/routes/stock-movements';

interface Product {
    id: number;
    name: string;
    sku: string | null;
}

interface User {
    id: number;
    name: string;
}

interface Reference {
    id: number;
    [key: string]: any;
}

interface Company {
    id: number;
    name: string;
}

interface SerialNumber {
    id: number;
    serial_number: string;
    status: string;
}

interface StockMovement {
    id: number;
    product: Product;
    type: 'in' | 'out' | 'adjustment' | 'transfer';
    quantity: number;
    unit_cost: number | null;
    stock_before: number;
    stock_after: number;
    /** DB free-text or polymorphic model payload when relation is loaded */
    reference: string | Reference | null;
    reference_type: string | null;
    reference_id: number | null;
    to_company_id: number | null;
    to_company?: Company | null;
    serial_number?: SerialNumber | null;
    serialNumbers?: SerialNumber[];
    notes: string | null;
    user: User | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    movement: StockMovement;
}

const props = defineProps<Props>();

function getTypeIcon(type: string) {
    return type === 'in' ? ArrowUp : type === 'out' ? ArrowDown : Settings;
}

function getTypeColor(type: string) {
    return {
        'in': 'text-green-600 bg-green-50',
        'out': 'text-red-600 bg-red-50',
        'adjustment': 'text-blue-600 bg-blue-50',
        'transfer': 'text-yellow-600 bg-yellow-50',
    }[type] || 'text-gray-600 bg-gray-50';
}

function formatCurrency(amount: number | null | undefined): string {
    if (amount === null || amount === undefined) return 'N/A';
    return `R${Number(amount).toFixed(2)}`;
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-ZA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getReferenceLink(): string | null {
    if (!props.movement.reference_type || !props.movement.reference_id) {
        return null;
    }
    
    const type = props.movement.reference_type;
    const id = props.movement.reference_id;
    
    // Map reference types to routes
    const routeMap: Record<string, (id: number) => { url: string }> = {
        'purchase_order': (id: number) => ({ url: `/purchase-orders/${id}` }),
        'invoice': (id: number) => ({ url: `/invoices/${id}` }),
        'quote': (id: number) => ({ url: `/quotes/${id}` }),
        'jobcard': (id: number) => ({ url: `/jobcards/${id}` }),
    };
    
    const routeFn = routeMap[type];
    return routeFn ? routeFn(id).url : null;
}

function getReferenceDisplayName(): string {
    const raw = props.movement.reference;
    const type = props.movement.reference_type;
    const refId = props.movement.reference_id;

    if (!type) {
        if (raw === null || raw === undefined) return 'N/A';
        return typeof raw === 'string' ? raw : displayFromReferenceObject(raw);
    }

    if (raw === null || raw === undefined) {
        return refId != null ? `${type} #${refId}` : 'N/A';
    }

    if (typeof raw === 'string') {
        return raw || (refId != null ? `${type} #${refId}` : 'N/A');
    }

    return displayFromReferenceObject(raw);
}

function displayFromReferenceObject(ref: Reference): string {
    const type = props.movement.reference_type;
    const refId = props.movement.reference_id;
    if (ref.po_number) return `PO-${ref.po_number}`;
    if (ref.invoice_number) return String(ref.invoice_number);
    if (ref.quote_number) return String(ref.quote_number);
    if (ref.job_number) return String(ref.job_number);
    if (ref.title) return String(ref.title);
    return type && refId != null ? `${type} #${refId}` : 'N/A';
}

const referenceLinkHref = computed(() => getReferenceLink());
</script>

<template>
    <Head :title="`Stock Movement #${props.movement.id}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Stock Movements', href: stockMovements.index().url },
        { title: `Movement #${props.movement.id}`, href: '#' }
    ]">
        <div class="space-y-6 p-4">
            <!-- Header Section -->
            <div class="rounded-lg bg-white border border-gray-200 p-6 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Stock Movement #{{ props.movement.id }}</h1>
                        <p class="text-sm text-gray-500 mt-1">Movement Details</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            :href="stockMovements.index().url"
                            class="flex items-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                        >
                            <ArrowLeft class="h-4 w-4" />
                            Back
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Movement Information -->
            <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Movement Information</h2>
                    <p class="text-sm text-gray-600">Details about this stock movement</p>
                </div>
                <div class="p-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Type -->
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100">
                                    <component
                                        :is="getTypeIcon(props.movement.type)"
                                        class="h-5 w-5 text-gray-600"
                                    />
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Type</div>
                                <span
                                    :class="[
                                        'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-semibold capitalize mt-1',
                                        getTypeColor(props.movement.type)
                                    ]"
                                >
                                    <component
                                        :is="getTypeIcon(props.movement.type)"
                                        class="h-3 w-3"
                                    />
                                    {{ props.movement.type }}
                                </span>
                            </div>
                        </div>

                        <!-- Product -->
                        <div class="flex items-center gap-3">
                            <Package class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Product</div>
                                <div class="mt-1">
                                    <Link
                                        v-if="props.movement.product"
                                        :href="`/products/${props.movement.product.id}`"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {{ props.movement.product.name }}
                                    </Link>
                                    <span v-if="props.movement.product?.sku" class="text-sm text-gray-500 ml-2">
                                        ({{ props.movement.product.sku }})
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Quantity -->
                        <div class="flex items-center gap-3">
                            <Hash class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Quantity</div>
                                <div class="text-lg font-semibold text-gray-900 mt-1">
                                    {{ props.movement.type === 'in' ? '+' : props.movement.type === 'out' ? '-' : '' }}{{ Math.abs(props.movement.quantity) }}
                                </div>
                            </div>
                        </div>

                        <!-- Unit Cost -->
                        <div v-if="props.movement.unit_cost" class="flex items-center gap-3">
                            <DollarSign class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Unit Cost</div>
                                <div class="text-lg font-semibold text-gray-900 mt-1">
                                    {{ formatCurrency(props.movement.unit_cost) }}
                                </div>
                            </div>
                        </div>

                        <!-- Stock Before -->
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100">
                                    <span class="text-sm font-medium text-gray-600">Before</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Stock Before</div>
                                <div class="text-lg font-semibold text-gray-900 mt-1">
                                    {{ props.movement.stock_before }}
                                </div>
                            </div>
                        </div>

                        <!-- Stock After -->
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100">
                                    <span class="text-sm font-medium text-green-700">After</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Stock After</div>
                                <div class="text-lg font-semibold text-gray-900 mt-1">
                                    {{ props.movement.stock_after }}
                                </div>
                            </div>
                        </div>

                        <!-- Transfer Destination (for transfers) -->
                        <div v-if="props.movement.type === 'transfer' && props.movement.to_company" class="flex items-center gap-3">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-yellow-100">
                                    <span class="text-sm font-medium text-yellow-700">To</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Transferred To</div>
                                <div class="text-sm font-medium text-gray-900 mt-1">
                                    {{ props.movement.to_company.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Reference -->
                        <div v-if="props.movement.reference && props.movement.type !== 'transfer'" class="flex items-center gap-3">
                            <FileText class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Reference</div>
                                <div class="mt-1">
                                    <Link
                                        v-if="referenceLinkHref"
                                        :href="referenceLinkHref"
                                        class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                    >
                                        {{ getReferenceDisplayName() }}
                                    </Link>
                                    <span v-else class="text-sm text-gray-900">
                                        {{ getReferenceDisplayName() }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- User -->
                        <div v-if="props.movement.user" class="flex items-center gap-3">
                            <User class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Recorded By</div>
                                <div class="text-sm font-medium text-gray-900 mt-1">
                                    {{ props.movement.user.name }}
                                </div>
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="flex items-center gap-3">
                            <Calendar class="h-5 w-5 text-gray-400 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-500">Date & Time</div>
                                <div class="text-sm text-gray-900 mt-1">
                                    {{ formatDate(props.movement.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Serial Numbers -->
            <div v-if="(props.movement.serialNumbers && props.movement.serialNumbers.length > 0) || props.movement.serial_number" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Serial Numbers</h2>
                    <p class="text-sm text-gray-600">Serial numbers associated with this movement</p>
                </div>
                <div class="p-6">
                    <div v-if="props.movement.serialNumbers && props.movement.serialNumbers.length > 0" class="flex flex-wrap gap-2">
                        <span
                            v-for="serial in props.movement.serialNumbers"
                            :key="serial.id"
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                            :class="{
                                'bg-green-100 text-green-800': serial.status === 'available',
                                'bg-blue-100 text-blue-800': serial.status === 'sold',
                                'bg-gray-100 text-gray-800': !serial.status || serial.status === 'returned',
                                'bg-red-100 text-red-800': serial.status === 'damaged' || serial.status === 'scrapped',
                            }"
                        >
                            {{ serial.serial_number }}
                            <span class="ml-2 text-xs opacity-75">({{ serial.status }})</span>
                        </span>
                    </div>
                    <div v-else-if="props.movement.serial_number" class="flex flex-wrap gap-2">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                            :class="{
                                'bg-green-100 text-green-800': props.movement.serial_number.status === 'available',
                                'bg-blue-100 text-blue-800': props.movement.serial_number.status === 'sold',
                                'bg-gray-100 text-gray-800': !props.movement.serial_number.status || props.movement.serial_number.status === 'returned',
                                'bg-red-100 text-red-800': props.movement.serial_number.status === 'damaged' || props.movement.serial_number.status === 'scrapped',
                            }"
                        >
                            {{ props.movement.serial_number.serial_number }}
                            <span class="ml-2 text-xs opacity-75">({{ props.movement.serial_number.status }})</span>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="props.movement.notes" class="rounded-lg bg-white border border-gray-200 shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Notes</h2>
                    <p class="text-sm text-gray-600">Additional information about this movement</p>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ props.movement.notes }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

