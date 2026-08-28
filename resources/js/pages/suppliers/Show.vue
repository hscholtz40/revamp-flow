<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { useNumberFormat } from '@/composables/useNumberFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Building2, Mail, Phone, MapPin, Edit, ArrowLeft, Package, FileText, Landmark } from 'lucide-vue-next';
import suppliers from '@/routes/suppliers';

interface Supplier {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    postal_code: string | null;
    country: string | null;
    vat_number: string | null;
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    bank_sort_code: string | null;
    notes: string | null;
    is_active: boolean;
    products?: Array<{ id: number; name: string; sku: string | null }>;
    created_at: string;
    updated_at: string;
}

interface Props {
    supplier: Supplier;
    purchaseOrders: {
        data: Array<{ id: number; po_number: string; status: string; total: number; created_at: string }>;
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}

const props = defineProps<Props>();

const canSuppliersEdit = useAuthAbility('suppliers', 'edit');
const canPurchaseOrdersList = useAuthAbility('purchase-orders', 'list');
const { formatCurrency } = useNumberFormat();

const hasBankDetails = computed(() =>
    Boolean(
        props.supplier.bank_name
        || props.supplier.bank_account_name
        || props.supplier.bank_account_number
        || props.supplier.bank_sort_code,
    ),
);
</script>

<template>
    <Head :title="props.supplier.name" />
    <AppLayout :breadcrumbs="[
        { title: 'Suppliers', href: suppliers.index().url },
        { title: props.supplier.name, href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="suppliers.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Suppliers
                </Link>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ props.supplier.name }}</h1>
                        <p class="text-gray-600">Supplier Details</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="canSuppliersEdit"
                            :href="suppliers.edit(props.supplier.id).url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <Edit class="mr-2 inline h-4 w-4" />
                            Edit
                        </Link>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <!-- Main Information -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Contact Information -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Contact Information</h2>
                        <div class="space-y-4">
                            <div v-if="props.supplier.email" class="flex items-center gap-3">
                                <Mail class="h-5 w-5 text-gray-400" />
                                <div>
                                    <div class="text-sm text-gray-500">Email</div>
                                    <div class="font-medium text-gray-900">{{ props.supplier.email }}</div>
                                </div>
                            </div>
                            <div v-if="props.supplier.phone" class="flex items-center gap-3">
                                <Phone class="h-5 w-5 text-gray-400" />
                                <div>
                                    <div class="text-sm text-gray-500">Phone</div>
                                    <div class="font-medium text-gray-900">{{ props.supplier.phone }}</div>
                                </div>
                            </div>
                            <div v-if="props.supplier.address || props.supplier.city" class="flex items-start gap-3">
                                <MapPin class="h-5 w-5 text-gray-400" />
                                <div>
                                    <div class="text-sm text-gray-500">Address</div>
                                    <div class="font-medium text-gray-900">
                                        <div v-if="props.supplier.address">{{ props.supplier.address }}</div>
                                        <div v-if="props.supplier.city || props.supplier.country">
                                            {{ [props.supplier.city, props.supplier.state, props.supplier.postal_code, props.supplier.country].filter(Boolean).join(', ') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="hasBankDetails"
                        class="rounded-lg border border-gray-200 bg-white p-6"
                    >
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Bank Details for Payments</h2>
                        <div class="space-y-4">
                            <div v-if="props.supplier.bank_name" class="flex items-center gap-3">
                                <Landmark class="h-5 w-5 text-gray-400" />
                                <div>
                                    <div class="text-sm text-gray-500">Bank Name</div>
                                    <div class="font-medium text-gray-900">{{ props.supplier.bank_name }}</div>
                                </div>
                            </div>
                            <div v-if="props.supplier.bank_account_name">
                                <div class="text-sm text-gray-500">Account Name</div>
                                <div class="font-medium text-gray-900">{{ props.supplier.bank_account_name }}</div>
                            </div>
                            <div v-if="props.supplier.bank_account_number">
                                <div class="text-sm text-gray-500">Account Number</div>
                                <div class="font-medium text-gray-900">{{ props.supplier.bank_account_number }}</div>
                            </div>
                            <div v-if="props.supplier.bank_sort_code">
                                <div class="text-sm text-gray-500">Branch Code</div>
                                <div class="font-medium text-gray-900">{{ props.supplier.bank_sort_code }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="props.supplier.notes" class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Notes</h2>
                        <p class="text-gray-700 whitespace-pre-wrap">{{ props.supplier.notes }}</p>
                    </div>

                    <!-- Products -->
                    <div v-if="props.supplier.products && props.supplier.products.length > 0" class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Products</h2>
                        <div class="space-y-2">
                            <div v-for="product in props.supplier.products" :key="product.id" class="flex items-center gap-2">
                                <Package class="h-4 w-4 text-gray-400" />
                                <span class="text-gray-900">{{ product.name }}</span>
                                <span v-if="product.sku" class="text-sm text-gray-500">({{ product.sku }})</span>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Orders -->
                    <div v-if="canPurchaseOrdersList" class="rounded-lg border border-gray-200 bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Purchase Orders</h2>
                        <p class="mb-4 text-sm text-gray-600">Complete purchase order history for this supplier.</p>
                        <div v-if="(props.purchaseOrders?.data || []).length === 0" class="py-4 text-sm text-gray-500">
                            No purchase orders found.
                        </div>
                        <div class="space-y-3">
                            <div v-for="po in props.purchaseOrders?.data || []" :key="po.id" class="flex items-center justify-between border-b pb-3 last:border-0 last:pb-0">
                                <div>
                                    <Link :href="`/purchase-orders/${po.id}`" class="font-medium text-blue-700 hover:text-blue-900 hover:underline">
                                        {{ po.po_number }}
                                    </Link>
                                    <div class="text-sm text-gray-500">{{ new Date(po.created_at).toLocaleDateString() }}</div>
                                </div>
                                <div class="text-right">
                                    <div class="font-medium text-gray-900">{{ formatCurrency(Number(po.total || 0)) }}</div>
                                    <span :class="{
                                        'bg-green-100 text-green-800': po.status === 'received',
                                        'bg-blue-100 text-blue-800': po.status === 'sent',
                                        'bg-yellow-100 text-yellow-800': po.status === 'draft',
                                        'bg-red-100 text-red-800': po.status === 'cancelled',
                                    }" class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize">
                                        {{ po.status }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-if="props.purchaseOrders?.last_page > 1" class="mt-4 border-t border-gray-200 pt-4">
                            <div class="flex items-center justify-end gap-1">
                                <Link
                                    v-for="link in props.purchaseOrders?.links || []"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :preserve-scroll="true"
                                    :class="[
                                        'px-3 py-1 text-sm rounded-md',
                                        link.active
                                            ? 'bg-blue-600 text-white'
                                            : link.url
                                                ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                    ]"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Status -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h3 class="mb-4 text-sm font-semibold text-gray-900">Status</h3>
                        <span
                            :class="props.supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                            class="inline-flex rounded-full px-3 py-1 text-sm font-semibold"
                        >
                            {{ props.supplier.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Details -->
                    <div class="rounded-lg border border-gray-200 bg-white p-6">
                        <h3 class="mb-4 text-sm font-semibold text-gray-900">Details</h3>
                        <div class="space-y-3 text-sm">
                            <div v-if="props.supplier.vat_number">
                                <div class="text-gray-500">VAT Number</div>
                                <div class="font-medium text-gray-900">{{ props.supplier.vat_number }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Created</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.supplier.created_at).toLocaleDateString() }}</div>
                            </div>
                            <div>
                                <div class="text-gray-500">Last Updated</div>
                                <div class="font-medium text-gray-900">{{ new Date(props.supplier.updated_at).toLocaleDateString() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

