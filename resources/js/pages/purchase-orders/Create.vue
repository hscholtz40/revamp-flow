<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import purchaseOrders from '@/routes/purchase-orders';

interface Product {
    id: number;
    name: string;
    sku: string | null;
    cost_price: number;
    selling_price: number;
    stock_quantity: number;
}

interface Supplier {
    id: number;
    name: string;
}

interface Props {
    supplier_id?: number;
    suppliers?: Supplier[];
    products?: Product[];
    taxRates?: { id: number; name: string; rate: number; is_default_purchasing: boolean }[];
    defaultPurchasingTaxRateId?: number | null;
    chartOfAccounts: { id: number; account_code: string; account_name: string; account_type: string }[];
    defaultPurchasingAccountId: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    suppliers: () => [],
    products: () => [],
    chartOfAccounts: () => [],
    defaultPurchasingAccountId: null,
});

// Track product search queries for each line item
const productSearchQueries = ref<Record<number, string>>({});
const productSearchFocused = ref<Record<number, boolean>>({});
const draggedItemIndex = ref<number | null>(null);
const dragOverItemIndex = ref<number | null>(null);
const dragOverGroupId = ref<number | null>(null);
const activeDragIndex = ref<number | null>(null);

interface LineItem {
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
    supplier_id: props.supplier_id || '',
    order_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: '',
    notes: '',
    terms: '',
    line_groups: [
        { name: 'Items', sort_order: 0 },
    ] as LineGroup[],
    items: [] as LineItem[],
});

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
function selectProductFromSearch(index: number, product: Product) {
    selectProduct(index, { target: { value: product.id.toString() } } as Event);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Select custom item (no product)
function selectCustomItem(index: number) {
    selectProduct(index, { target: { value: '' } } as Event);
    productSearchQueries.value[index] = '';
    productSearchFocused.value[index] = false;
}

// Clear product selection
function clearProduct(index: number) {
    selectProduct(index, { target: { value: '' } } as Event);
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

const taxAmount = computed(() => {
    return form.items.reduce((sum: number, item: any) => {
        const qty = Number(item.quantity) || 0;
        const cost = Number(item.unit_cost) || 0;
        const lineTotal = qty * cost;
        const taxRate = (props.taxRates || []).find(tr => tr.id === item.tax_rate_id);
        if (taxRate) {
            return sum + Math.ceil(lineTotal * (taxRate.rate / 100) * 100) / 100;
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
        line_groups: data.line_groups.map((group, index) => ({
            name: group.name,
            sort_order: index,
        })),
        items: data.items.map((item) => ({
            ...item,
            line_group_id: item.line_group_id ?? 1,
        })),
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
                <!-- Basic Information -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Supplier <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.supplier_id"
                                required
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.supplier_id }"
                            >
                                <option value="">Select a supplier</option>
                                <option v-for="supplier in props.suppliers" :key="supplier.id" :value="supplier.id">
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.supplier_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.supplier_id }}
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
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Items</h2>
                        <button
                            type="button"
                            @click="addLineItem(0)"
                            class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Plus class="h-4 w-4" />
                            Add Item
                        </button>
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
                                <input v-model="group.name" type="text" class="w-full rounded border px-2 py-1 text-sm" />
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

                    <div v-if="form.errors.items" class="mb-4 text-sm text-red-600">
                        {{ form.errors.items }}
                    </div>

                    <div v-if="form.items.length === 0" class="py-8 text-center text-sm text-gray-500">
                        No items added yet. Click "Add Item" to start.
                    </div>

                    <div v-else>
                        <div class="relative">
                            <div class="mb-2 hidden md:grid md:grid-cols-[1fr_4.5rem_7rem_8rem_11rem_7rem_2rem] gap-2 px-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <div>Description</div>
                                <div>Qty</div>
                                <div>Unit Cost</div>
                                <div>Tax</div>
                                <div>Account</div>
                                <div class="text-right">Total</div>
                                <div></div>
                            </div>

                            <div class="space-y-4">
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
                                        :key="index"
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
                                    <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_4.5rem_7rem_8rem_11rem_7rem_2rem] gap-2 items-start">
                                        <div class="relative">
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Description</label>
                                            <input
                                                type="text"
                                                :value="getProductInputValue(index, item)"
                                                @input="handleProductSearch(index, $event)"
                                                @focus="handleProductFocus(index)"
                                                @blur="handleProductBlur(index)"
                                                placeholder="Type description or search by SKU..."
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 pr-8 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            />
                                            <svg v-if="item.product_id" @click="clearProduct(index)" class="absolute right-2 top-8 md:top-1/2 md:-translate-y-1/2 h-4 w-4 text-gray-400 hover:text-gray-600 cursor-pointer" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            <div
                                                v-if="productSearchFocused[index] && productSearchQueries[index] && (filteredProducts(index).length > 0 || !item.product_id)"
                                                class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
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
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Qty</label>
                                            <input
                                                v-model.number="item.quantity"
                                                @input="calculateItemTotal(index)"
                                                type="number"
                                                min="1"
                                                required
                                                class="w-full rounded border border-gray-300 px-2 py-1.5 text-sm text-center focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                            />
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Unit Cost</label>
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
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Tax</label>
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
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Account</label>
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
                                            <label class="mb-1 block text-xs text-gray-500 md:hidden">Total</label>
                                            <div class="py-1.5 text-right text-sm font-medium text-gray-900">
                                                R{{ (item.quantity * item.unit_cost).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-center gap-2 md:pt-1.5">
                                            <svg class="h-5 w-5 cursor-grab rounded border border-gray-300 bg-gray-100 p-0.5 text-gray-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <circle cx="6" cy="5" r="1.2" />
                                                <circle cx="6" cy="10" r="1.2" />
                                                <circle cx="6" cy="15" r="1.2" />
                                                <circle cx="12" cy="5" r="1.2" />
                                                <circle cx="12" cy="10" r="1.2" />
                                                <circle cx="12" cy="15" r="1.2" />
                                            </svg>
                                            <button
                                                type="button"
                                                @click="removeLineItem(index)"
                                                class="text-gray-400 hover:text-red-600 transition-colors"
                                                :disabled="form.items.length === 1"
                                                :class="{ 'opacity-30 cursor-not-allowed': form.items.length === 1 }"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="form.items.length > 0" class="mt-6 border-t pt-4">
                        <div class="flex justify-end">
                            <div class="w-64 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Subtotal</span>
                                    <span class="text-sm font-medium">R{{ subtotal.toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Tax</span>
                                    <span class="text-sm font-medium">R{{ taxAmount.toFixed(2) }}</span>
                                </div>
                                <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Rounding Adjustment</span>
                                    <span class="text-sm font-medium">R{{ roundingAdjustment.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t pt-2">
                                    <span class="text-base font-semibold">Total</span>
                                    <span class="text-base font-bold text-green-600">R{{ total.toFixed(2) }}</span>
                                </div>
                            </div>
                        </div>
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

