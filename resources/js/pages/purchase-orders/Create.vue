<script setup lang="ts">
import { useNumberFormat } from '@/composables/useNumberFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { ref, computed, reactive } from 'vue';
import purchaseOrders from '@/routes/purchase-orders';

interface SupplierPickerSupplier {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    vat_number?: string | null;
}

interface Product {
    id: number;
    name: string;
    sku: string | null;
    cost_price: number;
    selling_price: number;
    stock_quantity: number;
}

interface Props {
    initialSupplier?: SupplierPickerSupplier | null;
    products?: Product[];
    taxRates?: { id: number; name: string; rate: number; is_default_purchasing: boolean }[];
    defaultPurchasingTaxRateId?: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string }[];
    defaultPurchasingAccountId: number | null;
    prefill?: {
        source_type?: 'quote' | 'jobcard' | null;
        source_id?: number | null;
        line_groups?: LineGroup[];
        items?: Array<{
            product_id: number | null;
            line_group_id?: number | null;
            quantity: number;
            unit_cost: number;
            description?: string | null;
            tax_rate_id?: number | null;
        }>;
    } | null;
    sourceSummary?: {
        type: 'quote' | 'jobcard';
        id: number;
        number: string;
    } | null;
}

const props = withDefaults(defineProps<Props>(), {
    initialSupplier: null,
    products: () => [],
    chartOfAccounts: () => [],
    defaultPurchasingAccountId: null,
    prefill: null,
    sourceSummary: null,
});

const { formatCurrency } = useNumberFormat();

const createLineItemUid = () =>
    `line-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;

// Track product search queries for each line item
const productSearchQueries = ref<Record<number, string>>({});
const productSearchFocused = ref<Record<number, boolean>>({});
const draggedItemIndex = ref<number | null>(null);
const dragOverItemIndex = ref<number | null>(null);
const dragOverGroupId = ref<number | null>(null);
const activeDragIndex = ref<number | null>(null);

interface LineItem {
    _uid: string;
    product_id: string | number;
    line_group_id?: number | null;
    quantity: number;
    unit_cost: number;
    description: string;
    total: number;
    tax_rate_id?: number | null;
    account_id?: number | null;
}

interface LineGroup {
    name: string;
    sort_order: number;
}

const form = useForm({
    supplier_id: props.initialSupplier?.id ?? '',
    source_type: props.prefill?.source_type ?? null,
    source_id: props.prefill?.source_id ?? null,
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    terms: '',
    line_groups: (props.prefill?.line_groups && props.prefill.line_groups.length > 0
        ? props.prefill.line_groups
        : [{ name: 'Items', sort_order: 0 }]) as LineGroup[],
    items: ((props.prefill?.items || []).map((item, index) => ({
        _uid: createLineItemUid(),
        product_id: item.product_id ?? '',
        line_group_id: item.line_group_id ?? 1,
        quantity: Number(item.quantity) || 1,
        unit_cost: Number(item.unit_cost) || 0,
        description: item.description || '',
        total: (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0),
        tax_rate_id: item.tax_rate_id ?? (props.defaultPurchasingTaxRateId || null),
        account_id: props.defaultPurchasingAccountId || null,
    })) as LineItem[]),
});

const supplierSearchQuery = ref(props.initialSupplier?.name ?? '');
const supplierSearchFocused = ref(false);
const filteredSuppliers = ref<SupplierPickerSupplier[]>([]);
const selectedSupplier = ref<SupplierPickerSupplier | null>(props.initialSupplier ?? null);
const showQuickCreateSupplierModal = ref(false);
const quickCreateSupplierForm = reactive({
    name: '',
    email: '',
    phone: '',
    vat_number: '',
    processing: false,
    errors: {} as Record<string, string>,
});

const handleSupplierSearch = async () => {
    if (!supplierSearchQuery.value.trim()) {
        filteredSuppliers.value = [];
        return;
    }

    try {
        const response = await fetch(`/suppliers/search?q=${encodeURIComponent(supplierSearchQuery.value)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            filteredSuppliers.value = await response.json();
        } else {
            filteredSuppliers.value = [];
        }
    } catch {
        filteredSuppliers.value = [];
    }
};

const handleSupplierBlur = () => {
    setTimeout(() => {
        supplierSearchFocused.value = false;
    }, 200);
};

const selectSupplier = (supplier: SupplierPickerSupplier) => {
    selectedSupplier.value = supplier;
    form.supplier_id = supplier.id;
    supplierSearchQuery.value = supplier.name;
    supplierSearchFocused.value = false;
    filteredSuppliers.value = [];
};

const clearSupplier = () => {
    selectedSupplier.value = null;
    form.supplier_id = '';
    supplierSearchQuery.value = '';
    filteredSuppliers.value = [];
};

const openQuickCreateSupplierModal = () => {
    quickCreateSupplierForm.name = supplierSearchQuery.value.trim();
    quickCreateSupplierForm.email = '';
    quickCreateSupplierForm.phone = '';
    quickCreateSupplierForm.vat_number = '';
    quickCreateSupplierForm.errors = {};
    showQuickCreateSupplierModal.value = true;
};

const quickCreateSupplier = async () => {
    quickCreateSupplierForm.processing = true;
    quickCreateSupplierForm.errors = {};

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const response = await fetch('/suppliers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                name: quickCreateSupplierForm.name,
                email: quickCreateSupplierForm.email || null,
                phone: quickCreateSupplierForm.phone || null,
                vat_number: quickCreateSupplierForm.vat_number || null,
                is_active: true,
            }),
        });

        if (response.ok) {
            const supplier = (await response.json()) as SupplierPickerSupplier;
            selectSupplier(supplier);
            showQuickCreateSupplierModal.value = false;
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            quickCreateSupplierForm.errors = Object.fromEntries(
                Object.entries(data.errors || {}).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)]),
            );
            return;
        }

        quickCreateSupplierForm.errors = { name: 'Failed to create supplier. Please try again.' };
    } catch {
        quickCreateSupplierForm.errors = { name: 'Failed to create supplier. Please try again.' };
    } finally {
        quickCreateSupplierForm.processing = false;
    }
};

function normalizeLineItemOrder() {
    const ordered: LineItem[] = [];
    for (let groupIndex = 0; groupIndex < form.line_groups.length; groupIndex++) {
        const groupId = groupIndex + 1;
        const groupItems = form.items.filter((item) => (item.line_group_id ?? 1) === groupId);
        ordered.push(...groupItems);
    }

    const ungroupedItems = form.items.filter((item) => !item.line_group_id || item.line_group_id > form.line_groups.length);
    ordered.push(...ungroupedItems.map((item) => ({ ...item, line_group_id: 1 })));
    form.items = ordered;
}

const groupedLineItems = computed(() =>
    form.line_groups.map((group, groupIndex) => {
        const groupId = groupIndex + 1;
        const items = form.items
            .map((item, index) => ({ item, index }))
            .filter(({ item }) => (item.line_group_id ?? 1) === groupId);
        return { group, groupIndex, groupId, items };
    }),
);

function addLineItem(groupIndex = 0) {
    form.items.push({
        _uid: createLineItemUid(),
        product_id: '',
        line_group_id: groupIndex + 1,
        quantity: 1,
        unit_cost: 0,
        description: '',
        total: 0,
        tax_rate_id: props.defaultPurchasingTaxRateId || null,
        account_id: props.defaultPurchasingAccountId || null,
    });
    normalizeLineItemOrder();
}

function addLineGroup() {
    const nextSortOrder = form.line_groups.length;
    form.line_groups.push({
        name: `Group ${nextSortOrder + 1}`,
        sort_order: nextSortOrder,
    });
}

function removeLineGroup(index: number) {
    if (form.line_groups.length <= 1) return;
    const removedGroupId = index + 1;
    form.line_groups.splice(index, 1);
    form.line_groups.forEach((group, idx) => {
        group.sort_order = idx;
    });
    form.items.forEach((item) => {
        if (item.line_group_id === removedGroupId || !item.line_group_id) {
            item.line_group_id = 1;
        } else if ((item.line_group_id ?? 0) > removedGroupId) {
            item.line_group_id = (item.line_group_id ?? 0) - 1;
        }
    });
    normalizeLineItemOrder();
}

function removeLineItem(index: number) {
    form.items.splice(index, 1);
    normalizeLineItemOrder();
    calculateTotals();
}

function onDragStart(itemIndex: number, event: DragEvent) {
    draggedItemIndex.value = itemIndex;
    activeDragIndex.value = itemIndex;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(itemIndex));
        const preview = document.createElement('div');
        preview.textContent = 'Moving line item';
        preview.className = 'pointer-events-none rounded border border-blue-300 bg-blue-50 px-2 py-1 text-xs text-blue-700 shadow';
        document.body.appendChild(preview);
        event.dataTransfer.setDragImage(preview, 10, 10);
        requestAnimationFrame(() => preview.remove());
    }
}

function onDragEnd() {
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

function onDragOver(event: DragEvent) {
    event.preventDefault();
}

function onDragEnterItem(itemIndex: number) {
    dragOverItemIndex.value = itemIndex;
    dragOverGroupId.value = null;
}

function onDragLeaveItem(itemIndex: number) {
    if (dragOverItemIndex.value === itemIndex) {
        dragOverItemIndex.value = null;
    }
}

function onDragEnterGroup(groupId: number) {
    dragOverGroupId.value = groupId;
}

function onDragLeaveGroup(groupId: number) {
    if (dragOverGroupId.value === groupId) {
        dragOverGroupId.value = null;
    }
}

function moveItem(sourceIndex: number, targetIndex: number, targetGroupId: number) {
    if (sourceIndex === targetIndex || sourceIndex < 0 || targetIndex < 0) return;
    const moved = form.items[sourceIndex];
    if (!moved) return;

    form.items.splice(sourceIndex, 1);
    moved.line_group_id = targetGroupId;
    const adjustedTarget = sourceIndex < targetIndex ? targetIndex - 1 : targetIndex;
    form.items.splice(adjustedTarget, 0, moved);
    normalizeLineItemOrder();
    calculateTotals();
}

function onDropOnItem(targetItemIndex: number, targetGroupId: number) {
    if (draggedItemIndex.value === null) return;
    moveItem(draggedItemIndex.value, targetItemIndex, targetGroupId);
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

function onDropInGroup(groupId: number) {
    if (draggedItemIndex.value === null) return;

    const sourceIndex = draggedItemIndex.value;
    const moved = form.items[sourceIndex];
    if (!moved) {
        draggedItemIndex.value = null;
        return;
    }

    form.items.splice(sourceIndex, 1);
    moved.line_group_id = groupId;

    const lastIndexInGroup = form.items.reduce((lastIndex, item, idx) => {
        return (item.line_group_id ?? 1) === groupId ? idx : lastIndex;
    }, -1);
    const insertIndex = lastIndexInGroup >= 0 ? lastIndexInGroup + 1 : form.items.length;
    form.items.splice(insertIndex, 0, moved);
    normalizeLineItemOrder();
    calculateTotals();
    draggedItemIndex.value = null;
    dragOverItemIndex.value = null;
    dragOverGroupId.value = null;
    activeDragIndex.value = null;
}

// Filter products based on search query
function filteredProducts(index: number) {
    const normalizeTerm = (value: string | null | undefined) => String(value ?? '').trim().toLocaleLowerCase();
    const query = normalizeTerm(productSearchQueries.value[index]);
    if (!query) return [];

    return props.products
        .filter((product) => {
            const nameMatch = normalizeTerm(product.name).includes(query);
            const skuMatch = normalizeTerm(product.sku).includes(query);
            return nameMatch || skuMatch;
        })
        .slice(0, 10);
}

// Get display name for selected product
function getProductDisplayName(productId: string | number | null | undefined) {
    if (!productId) return '';
    const product = props.products.find(p => p.id === parseInt(productId.toString()));
    if (!product) return '';
    return product.sku ? `${product.name} (${product.sku})` : product.name;
}

// Handle product search input
function handleProductSearch(index: number, event: Event) {
    const target = event.target as HTMLInputElement;
    productSearchQueries.value[index] = target.value;
    
    // If input is cleared, clear product selection
    if (!target.value) {
        clearProduct(index);
    }
}

// Handle product input focus
function handleProductFocus(index: number) {
    productSearchFocused.value[index] = true;
    const productId = form.items[index].product_id;
    if (productId) {
        // Show current product name/SKU in search
        const product = props.products.find(p => p.id === parseInt(productId.toString()));
        if (product) {
            productSearchQueries.value[index] = product.sku ? `${product.name} ${product.sku}` : product.name;
        }
    }
}

function getProductInputValue(index: number, item: LineItem) {
    if (productSearchFocused.value[index]) {
        return productSearchQueries.value[index] ?? getProductDisplayName(item.product_id);
    }

    return getProductDisplayName(item.product_id);
}

// Handle product input blur (with delay to allow click on dropdown)
function handleProductBlur(index: number) {
    setTimeout(() => {
        productSearchFocused.value[index] = false;
        productSearchQueries.value[index] = '';
    }, 200);
}

// Select product from search results
const createSelectEvent = (value: string) =>
    ({ target: { value } } as unknown as Event);

function selectProductFromSearch(index: number, product: Product) {
    selectProduct(index, createSelectEvent(product.id.toString()));
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Select custom item (no product)
function selectCustomItem(index: number) {
    selectProduct(index, createSelectEvent(''));
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Clear product selection
function clearProduct(index: number) {
    selectProduct(index, createSelectEvent(''));
    productSearchQueries.value[index] = '';
}

function selectProduct(index: number, event: Event) {
    const select = event.target as HTMLSelectElement;
    const productId = parseInt(select.value);
    
    if (productId) {
        const product = props.products.find(p => p.id === productId);
        if (product) {
            form.items[index].product_id = productId;
            form.items[index].unit_cost = product.cost_price || 0;
            form.items[index].description = product.name;
            calculateItemTotal(index);
        }
    } else {
        form.items[index].product_id = '';
        form.items[index].unit_cost = 0;
        form.items[index].description = '';
        form.items[index].total = 0;
    }
}

function calculateItemTotal(index: number) {
    const item = form.items[index];
    item.total = item.quantity * item.unit_cost;
    calculateTotals();
}

function calculateTotals() {
    // Totals are calculated server-side, but we can show a preview
}

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.quantity * item.unit_cost), 0);
});

const roundCurrency = (amount: number): number => {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
};

const taxAmount = computed(() => {
    return form.items.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity) || 0;
        const cost = Number(item.unit_cost) || 0;
        const lineTotal = qty * cost;
        const taxRate = (props.taxRates || []).find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            const lineTax = roundCurrency(lineTotal * (taxRate.rate / 100));
            return roundCurrency(sum + lineTax);
        }
        return sum;
    }, 0);
});

const total = computed(() => subtotal.value + taxAmount.value);

const roundingAdjustment = computed(() => {
    return form.items.reduce((sum, item) => {
        if ((item.description || '').trim().toLowerCase() !== 'rounding adjustment') {
            return sum;
        }
        return sum + (Number(item.total) || (Number(item.quantity) || 0) * (Number(item.unit_cost) || 0));
    }, 0);
});

function submit() {
    form.transform((data) => ({
        ...data,
        supplier_id: data.supplier_id === '' || data.supplier_id === null ? null : Number(data.supplier_id),
        source_id: data.source_id ? Number(data.source_id) : null,
        line_groups: data.line_groups.map((group, index) => ({
            name: group.name,
            sort_order: index,
        })),
        items: data.items.map((item) => {
            const { _uid, ...rest } = item as LineItem;
            return {
                ...rest,
                line_group_id: item.line_group_id ?? 1,
            };
        }),
    })).post(purchaseOrders.store().url);
}
</script>

<template>
    <Head title="Create Purchase Order" />
    <AppLayout :breadcrumbs="[
        { title: 'Purchase Orders', href: purchaseOrders.index().url },
        { title: 'Create Purchase Order', href: '#' }
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
                <h1 class="text-2xl font-bold text-gray-900">Create Purchase Order</h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div v-if="props.sourceSummary" class="rounded-lg border border-teal-200 bg-teal-50 p-4">
                    <p class="text-sm text-teal-900">
                        Creating this purchase order from
                        <span class="font-semibold">{{ props.sourceSummary.type.toUpperCase() }}</span>
                        <span class="font-semibold">{{ props.sourceSummary.number }}</span>.
                    </p>
                </div>
                <!-- Basic Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="relative">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <label class="block text-sm font-medium text-gray-700">
                                    Supplier <span class="text-red-500">*</span>
                                </label>
                                <button
                                    type="button"
                                    @click="openQuickCreateSupplierModal"
                                    class="text-xs font-medium text-blue-600 hover:text-blue-700 hover:underline"
                                >
                                    Quick Create Supplier
                                </button>
                            </div>
                            <div class="relative">
                                <input
                                    v-model="supplierSearchQuery"
                                    type="text"
                                    autocomplete="off"
                                    @input="handleSupplierSearch"
                                    @focus="supplierSearchFocused = true"
                                    @blur="handleSupplierBlur"
                                    :placeholder="selectedSupplier ? selectedSupplier.name : 'Search supplier by name, email, phone, or VAT number'"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.supplier_id }"
                                />
                                <button
                                    v-if="selectedSupplier"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                    @click.prevent="clearSupplier"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <div
                                v-if="supplierSearchFocused && (filteredSuppliers.length > 0 || supplierSearchQuery.trim())"
                                class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border border-gray-300 bg-white shadow-lg"
                            >
                                <div
                                    v-if="supplierSearchQuery.trim()"
                                    @mousedown.prevent="openQuickCreateSupplierModal"
                                    class="cursor-pointer border-b border-gray-200 bg-blue-50 px-4 py-2 hover:bg-blue-100"
                                >
                                    <div class="text-sm font-medium text-blue-700">Quick Create: "{{ supplierSearchQuery.trim() }}"</div>
                                </div>
                                <div
                                    v-for="supplier in filteredSuppliers"
                                    :key="supplier.id"
                                    class="cursor-pointer px-4 py-2 hover:bg-gray-100"
                                    @mousedown.prevent="selectSupplier(supplier)"
                                >
                                    <div class="font-medium">{{ supplier.name }}</div>
                                    <div class="text-xs text-gray-500">
                                        <span v-if="supplier.vat_number">{{ supplier.vat_number }}</span>
                                        <span v-if="supplier.email">
                                            <span v-if="supplier.vat_number"> • </span>{{ supplier.email }}
                                        </span>
                                        <span v-if="supplier.phone">
                                            <span v-if="supplier.email || supplier.vat_number"> • </span>{{ supplier.phone }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    v-if="supplierSearchQuery.trim() && filteredSuppliers.length === 0"
                                    class="px-4 py-2 text-sm text-gray-500"
                                >
                                    No suppliers found.
                                </div>
                            </div>
                            <div v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.supplier_id }}
                            </div>
                        </div>

                        <div
                            v-if="showQuickCreateSupplierModal"
                            class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
                            @click.self="showQuickCreateSupplierModal = false"
                        >
                            <div class="w-full max-w-md rounded-lg bg-white p-6" @click.stop>
                                <h3 class="mb-4 text-lg font-semibold">Quick Create Supplier</h3>
                                <form @submit.prevent="quickCreateSupplier" class="space-y-4">
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Name *</label>
                                        <input
                                            v-model="quickCreateSupplierForm.name"
                                            type="text"
                                            class="w-full rounded border px-3 py-2"
                                            required
                                        />
                                        <div v-if="quickCreateSupplierForm.errors.name" class="mt-1 text-sm text-red-600">
                                            {{ quickCreateSupplierForm.errors.name }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                        <input
                                            v-model="quickCreateSupplierForm.email"
                                            type="email"
                                            class="w-full rounded border px-3 py-2"
                                        />
                                        <div v-if="quickCreateSupplierForm.errors.email" class="mt-1 text-sm text-red-600">
                                            {{ quickCreateSupplierForm.errors.email }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                        <input
                                            v-model="quickCreateSupplierForm.phone"
                                            type="text"
                                            class="w-full rounded border px-3 py-2"
                                        />
                                        <div v-if="quickCreateSupplierForm.errors.phone" class="mt-1 text-sm text-red-600">
                                            {{ quickCreateSupplierForm.errors.phone }}
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-700">VAT Number</label>
                                        <input
                                            v-model="quickCreateSupplierForm.vat_number"
                                            type="text"
                                            class="w-full rounded border px-3 py-2"
                                        />
                                        <div v-if="quickCreateSupplierForm.errors.vat_number" class="mt-1 text-sm text-red-600">
                                            {{ quickCreateSupplierForm.errors.vat_number }}
                                        </div>
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button
                                            type="submit"
                                            :disabled="quickCreateSupplierForm.processing"
                                            class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                                        >
                                            {{ quickCreateSupplierForm.processing ? 'Creating...' : 'Create' }}
                                        </button>
                                        <button
                                            type="button"
                                            @click="showQuickCreateSupplierModal = false"
                                            class="flex-1 rounded border px-4 py-2 hover:bg-gray-50"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Order Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.order_date"
                                type="date"
                                required
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.order_date }"
                            />
                            <div v-if="form.errors.order_date" class="mt-1 text-sm text-red-600">
                                {{ form.errors.order_date }}
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Expected Delivery Date
                            </label>
                            <input
                                v-model="form.expected_delivery_date"
                                type="date"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="bg-white rounded-lg border p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900">Line Items</h2>
                    </div>

                    <div class="mb-4 rounded border border-gray-200 p-3">
                        <div class="mb-2 flex items-center justify-between">
                            <h3 class="text-sm font-medium text-gray-800">Line Groups</h3>
                            <button type="button" @click="addLineGroup" class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-gray-50">
                                + Add Group
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div v-for="(group, groupIndex) in form.line_groups" :key="groupIndex" class="flex items-center gap-2">
                                <input
                                    v-model="group.name"
                                    type="text"
                                    class="w-full rounded border px-2 py-1 text-sm"
                                    placeholder="Group name"
                                />
                                <button
                                    type="button"
                                    @click="removeLineGroup(groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                    :disabled="form.line_groups.length === 1"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.errors.items" class="text-red-500 text-sm mb-4">
                        {{ form.errors.items }}
                    </div>

                    <!-- Table Header -->
                    <div
                        v-if="form.items.length > 0"
                        class="hidden md:grid md:grid-cols-[3.5rem_1fr_6.5rem_8rem_8rem_5.5rem_2rem] gap-2 px-3 pb-2 text-xs font-medium text-gray-500 uppercase tracking-wider border-b"
                    >
                        <div>Qty</div>
                        <div>Description</div>
                        <div>Cost</div>
                        <div>Tax</div>
                        <div>Account</div>
                        <div class="text-right">Total</div>
                        <div></div>
                    </div>

                    <div v-if="form.items.length > 0" class="space-y-4">
                        <div
                            v-for="groupBlock in groupedLineItems"
                            :key="groupBlock.groupId"
                            class="rounded border border-gray-200 transition-colors"
                            :class="{ 'border-blue-300 bg-blue-50/30': dragOverGroupId === groupBlock.groupId && dragOverItemIndex === null }"
                            @dragover="onDragOver"
                            @dragenter.prevent="onDragEnterGroup(groupBlock.groupId)"
                            @dragleave="onDragLeaveGroup(groupBlock.groupId)"
                            @drop="onDropInGroup(groupBlock.groupId)"
                        >
                            <div class="flex items-center justify-between bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700">
                                <span>{{ groupBlock.group.name || `Group ${groupBlock.groupIndex + 1}` }}</span>
                                <button
                                    type="button"
                                    @click.stop="addLineItem(groupBlock.groupIndex)"
                                    class="rounded border px-2 py-1 text-xs text-gray-700 hover:bg-white"
                                >
                                    + Add line item
                                </button>
                            </div>

                            <div
                                v-for="({ item, index }) in groupBlock.items"
                                :key="item._uid || index"
                                class="border-t border-gray-100 py-3 px-1 transition-colors"
                                :class="{ 'bg-blue-50/60': dragOverItemIndex === index, 'opacity-60': activeDragIndex === index }"
                                draggable="true"
                                @dragstart="onDragStart(index, $event)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver"
                                @dragenter.prevent="onDragEnterItem(index)"
                                @dragleave="onDragLeaveItem(index)"
                                @drop.stop="onDropOnItem(index, groupBlock.groupId)"
                            >
                                <div class="grid grid-cols-1 md:grid-cols-[3.5rem_1fr_6.5rem_8rem_8rem_5.5rem_2rem] gap-2 items-start">
                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Qty</label>
                                        <input
                                            v-model.number="item.quantity"
                                            @input="calculateItemTotal(index)"
                                            type="number"
                                            min="1"
                                            required
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-center focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div class="relative min-w-0">
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Description</label>
                                        <input
                                            type="text"
                                            :value="getProductInputValue(index, item)"
                                            @input="handleProductSearch(index, $event)"
                                            @focus="handleProductFocus(index)"
                                            @blur="handleProductBlur(index)"
                                            placeholder="Type description or search products..."
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 pr-8 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        />
                                        <svg
                                            v-if="item.product_id"
                                            @click="clearProduct(index)"
                                            class="absolute right-2 top-8 md:top-1/2 md:-translate-y-1/2 h-4 w-4 text-gray-400 hover:text-gray-600 cursor-pointer"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <div
                                            v-if="productSearchFocused[index] && productSearchQueries[index] && (filteredProducts(index).length > 0 || !item.product_id)"
                                            class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-auto"
                                        >
                                            <div
                                                v-if="!item.product_id"
                                                @mousedown.prevent="selectCustomItem(index)"
                                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-gray-600 italic border-b border-gray-100"
                                            >
                                                Use custom item
                                            </div>
                                            <div
                                                v-for="product in filteredProducts(index)"
                                                :key="product.id"
                                                @mousedown.prevent="selectProductFromSearch(index, product)"
                                                class="px-3 py-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                                            >
                                                <div class="font-medium text-gray-900">{{ product.name }}</div>
                                                <div v-if="product.sku" class="text-sm text-gray-500">SKU: {{ product.sku }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Cost</label>
                                        <input
                                            v-model.number="item.unit_cost"
                                            @input="calculateItemTotal(index)"
                                            type="number"
                                            step="0.01"
                                            required
                                            class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Tax</label>
                                        <select
                                            v-model="item.tax_rate_id"
                                            class="w-full rounded border border-gray-300 px-1 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        >
                                            <option :value="null">None</option>
                                            <option v-for="tr in (props.taxRates || [])" :key="tr.id" :value="tr.id">
                                                {{ tr.name }} ({{ tr.rate }}%)
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Account</label>
                                        <select
                                            v-model="item.account_id"
                                            class="w-full rounded border border-gray-300 px-1 py-1.5 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                        >
                                            <option :value="null">None</option>
                                            <option v-for="acc in props.chartOfAccounts" :key="acc.id" :value="acc.id">
                                                {{ acc.account_code }} - {{ acc.account_name }}
                                            </option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-xs text-gray-500 mb-1 md:hidden">Total</label>
                                        <div class="text-right text-sm font-medium text-gray-700 py-1.5">
                                            {{ formatCurrency((Number(item.quantity) || 0) * (Number(item.unit_cost) || 0)) }}
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-center gap-2 md:pt-1.5">
                                        <DragHandleIcon />
                                        <button
                                            type="button"
                                            @click="removeLineItem(index)"
                                            class="text-gray-400 hover:text-red-600 transition-colors"
                                            :disabled="form.items.length === 1"
                                            :class="{ 'opacity-30 cursor-not-allowed': form.items.length === 1 }"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="addLineItem(0)"
                        class="mt-3 w-full rounded border-2 border-dashed border-gray-300 py-2 text-sm text-gray-500 hover:border-blue-400 hover:text-blue-600 transition-colors"
                    >
                        + Add Line Item
                    </button>
                </div>

                <!-- Totals -->
                <div v-if="form.items.length > 0" class="bg-white rounded-lg border p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Totals</h2>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">{{ formatCurrency(subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax:</span>
                            <span class="font-medium">{{ formatCurrency(taxAmount) }}</span>
                        </div>
                        <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between">
                            <span class="text-gray-600">Rounding Adjustment:</span>
                            <span class="font-medium">{{ formatCurrency(roundingAdjustment) }}</span>
                        </div>
                        <div class="flex justify-between text-lg font-semibold border-t pt-2">
                            <span>Total:</span>
                            <span>{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Additional Information</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Internal notes about this purchase order..."
                            ></textarea>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Terms</label>
                            <textarea
                                v-model="form.terms"
                                rows="3"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Payment terms and conditions..."
                            ></textarea>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t pt-6">
                    <Link
                        :href="purchaseOrders.index().url"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || form.items.length === 0"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Purchase Order' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

