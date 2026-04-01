<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useNumberFormat } from '@/composables/useNumberFormat';
import { matchesProductSearch } from '@/composables/productSearch';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import invoices from '@/routes/invoices';

interface Customer {
    id: number;
    name: string;
    terms?: string;
}

interface Product {
    id: number;
    name: string;
    sku?: string | null;
    price: number;
}

const props = defineProps<{
    customers: Customer[];
    products: Product[];
    selectedCustomer?: Customer | null;
    printPdfUrl?: string | null;
    defaultSalesTaxRate: { id: number; name: string; rate: number } | null;
    defaultSalesAccountLabel: string | null;
    defaultTermsConditions?: string | null;
}>();

const { formatCurrency } = useNumberFormat();

const page = usePage();

const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];
const basePosPaymentMethods = [
    { value: 'cash', label: 'Cash' },
    { value: 'card', label: 'Card' },
    { value: 'eft', label: 'EFT' },
];
const paymentMethodOptions = computed(() => {
    const pm = page.props.auth?.payment_methods;
    const filtered = !pm ? [...basePosPaymentMethods] : basePosPaymentMethods.filter((m) => pm[m.value as 'cash' | 'card' | 'eft']);
    return [...filtered, { value: 'account', label: 'Account' }];
});

const showProductSuggestions = ref<Record<number, boolean>>({});
const pendingPrintWindow = ref<Window | null>(null);
const customerSearchQuery = ref(props.selectedCustomer?.name || '');
const customerSearchFocused = ref(false);
const filteredCustomers = ref<Customer[]>([]);
const selectedCustomer = ref<Customer | null>(props.selectedCustomer || null);

const form = useForm({
    customer_id: props.selectedCustomer?.id ? String(props.selectedCustomer.id) : '',
    order_number: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date().toISOString().split('T')[0],
    terms: (props.selectedCustomer?.terms || 'COD').trim() || 'COD',
    terms_conditions: props.defaultTermsConditions || '',
    notes: '',
    payment_method: '',
    amount_paid: 0,
    tendered_amount: 0,
    line_items: [
        {
            product_id: null as number | null,
            description: '',
            quantity: 1,
            unit_price: 0,
            discount_amount: 0,
            discount_percentage: 0,
        },
    ],
});

watch(
    paymentMethodOptions,
    (opts) => {
        const v = form.payment_method;
        if (v && !opts.some((o) => o.value === v)) {
            form.payment_method = '';
        }
    },
    { immediate: true }
);

const parseTermsDays = (terms: string) => {
    if (/^cod$/i.test(terms)) return 0;
    const net = terms.match(/net\s*(\d+)/i);
    if (net) return Number(net[1]) || 0;
    const num = terms.match(/(\d+)/);
    return num ? Number(num[1]) || 0 : 0;
};

const applyDueDate = () => {
    const d = new Date(form.invoice_date || new Date().toISOString().split('T')[0]);
    if (Number.isNaN(d.getTime())) return;
    d.setDate(d.getDate() + parseTermsDays(form.terms));
    form.due_date = d.toISOString().split('T')[0];
};

watch(() => form.customer_id, (id) => {
    const customer = props.customers.find(c => c.id === Number(id));
    form.terms = (customer?.terms || 'COD').trim() || 'COD';
    applyDueDate();
});
watch(() => form.invoice_date, applyDueDate);
watch(() => form.terms, applyDueDate);
applyDueDate();

const handleCustomerSearch = async () => {
    if (!customerSearchQuery.value.trim()) {
        filteredCustomers.value = [];
        return;
    }

    try {
        const response = await fetch(`/customers/search?q=${encodeURIComponent(customerSearchQuery.value)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (response.ok) {
            filteredCustomers.value = await response.json();
        } else {
            filteredCustomers.value = [];
        }
    } catch {
        filteredCustomers.value = [];
    }
};

const handleCustomerBlur = () => {
    setTimeout(() => {
        customerSearchFocused.value = false;
    }, 200);
};

const selectCustomer = (customer: Customer) => {
    selectedCustomer.value = customer;
    form.customer_id = String(customer.id);
    customerSearchQuery.value = customer.name;
    customerSearchFocused.value = false;
    filteredCustomers.value = [];
};

const clearCustomer = () => {
    selectedCustomer.value = null;
    form.customer_id = '';
    customerSearchQuery.value = '';
    filteredCustomers.value = [];
};

const addLineItem = () => {
    form.line_items.push({
        product_id: null,
        description: '',
        quantity: 1,
        unit_price: 0,
        discount_amount: 0,
        discount_percentage: 0,
    });
};

const removeLineItem = (index: number) => {
    if (form.line_items.length > 1) {
        form.line_items.splice(index, 1);
    }
};

const onProductChange = (index: number) => {
    const row = form.line_items[index];
    const product = props.products.find(p => p.id === row.product_id);
    if (product) {
        row.description = product.name;
        row.unit_price = Number(product.price) || 0;
    }
};

const productSuggestions = (index: number) => {
    const query = form.line_items[index]?.description || '';
    return props.products.filter(product => matchesProductSearch(product, query)).slice(0, 8);
};

const handleDescriptionInput = (index: number) => {
    showProductSuggestions.value[index] = true;
};

const handleDescriptionBlur = (index: number) => {
    setTimeout(() => {
        showProductSuggestions.value[index] = false;
    }, 200);
};

const selectProductSuggestion = (index: number, product: Product) => {
    const item = form.line_items[index];
    if (!item) return;
    item.product_id = product.id;
    item.description = product.name;
    item.unit_price = Number(product.price) || 0;
    showProductSuggestions.value[index] = false;
};

const unlinkProduct = (index: number) => {
    const item = form.line_items[index];
    if (!item) return;
    item.product_id = null;
};

const lineTotal = (item: any) => {
    const qty = Number(item.quantity) || 0;
    const price = Number(item.unit_price) || 0;
    const subtotal = qty * price;
    const discPct = Number(item.discount_percentage) || 0;
    const discAmt = discPct > 0 ? subtotal * (discPct / 100) : (Number(item.discount_amount) || 0);
    return subtotal - discAmt;
};

const roundCurrency = (amount: number): number => Math.round((amount + Number.EPSILON) * 100) / 100;

const subtotal = computed(() => form.line_items.reduce((sum, i) => sum + lineTotal(i), 0));

const defaultTaxRate = computed(() => Number(props.defaultSalesTaxRate?.rate || 0));

/** Per-line tax, matching `InvoicesController::storePos` (rounded per line, then summed). */
const taxAmount = computed(() => {
    if (!props.defaultSalesTaxRate?.id || defaultTaxRate.value <= 0) {
        return 0;
    }
    return form.line_items.reduce((sum: number, item: any) => {
        const lineTax = roundCurrency(lineTotal(item) * (defaultTaxRate.value / 100));
        return roundCurrency(sum + lineTax);
    }, 0);
});

const roundToNearestTenCents = (amount: number): number => Math.round(amount * 10) / 10;

const baseTotalBeforeRounding = computed(() => subtotal.value + taxAmount.value);
const roundedTargetTotal = computed(() => roundToNearestTenCents(baseTotalBeforeRounding.value));
const roundingAdjustment = computed(() => roundedTargetTotal.value - baseTotalBeforeRounding.value);

const total = computed(() => subtotal.value + taxAmount.value + roundingAdjustment.value);

watch(total, (newTotal) => {
    if (form.payment_method === 'account') {
        form.amount_paid = 0;
        form.tendered_amount = 0;
        return;
    }

    form.amount_paid = Number(newTotal.toFixed(2));
    if (form.payment_method === 'cash' && form.tendered_amount < form.amount_paid) {
        form.tendered_amount = form.amount_paid;
    }
}, { immediate: true });

watch(() => form.payment_method, (method) => {
    if (method === 'account') {
        form.amount_paid = 0;
        form.tendered_amount = 0;
        return;
    }

    form.amount_paid = Number(total.value.toFixed(2));
    if (method === 'cash' && form.tendered_amount < form.amount_paid) {
        form.tendered_amount = form.amount_paid;
    }
});

const changeDue = computed(() => {
    if (form.payment_method !== 'cash') return 0;
    return Math.max(0, (Number(form.tendered_amount) || 0) - (Number(form.amount_paid) || 0));
});

const hasAtLeastOneLineItem = computed(() => form.line_items.length > 0);
const areLineItemsValid = computed(() =>
    form.line_items.length > 0 &&
    form.line_items.every((item) =>
        (item.description || '').trim().length > 0 &&
        Number(item.quantity) >= 1 &&
        Number(item.unit_price) >= 0
    )
);

const canCompleteSale = computed(() =>
    Boolean(form.customer_id) &&
    Boolean(form.payment_method) &&
    Boolean(form.invoice_date) &&
    Boolean(form.due_date) &&
    Boolean((form.terms || '').trim()) &&
    (form.payment_method === 'account' || Number(form.amount_paid) > 0) &&
    hasAtLeastOneLineItem.value &&
    areLineItemsValid.value
);

const makeEmptyLineItem = () => ({
    product_id: null as number | null,
    description: '',
    quantity: 1,
    unit_price: 0,
    discount_amount: 0,
    discount_percentage: 0,
});

const resetSale = () => {
    const defaultCustomer = props.selectedCustomer || null;
    selectedCustomer.value = defaultCustomer;
    customerSearchQuery.value = defaultCustomer?.name || '';
    customerSearchFocused.value = false;
    filteredCustomers.value = [];
    showProductSuggestions.value = {};

    form.customer_id = defaultCustomer?.id ? String(defaultCustomer.id) : '';
    form.order_number = '';
    form.invoice_date = new Date().toISOString().split('T')[0];
    form.terms = (defaultCustomer?.terms || 'COD').trim() || 'COD';
    form.notes = '';
    form.payment_method = '';
    form.line_items = [makeEmptyLineItem()];
    applyDueDate();
    form.amount_paid = Number(total.value.toFixed(2));
    form.tendered_amount = form.amount_paid;
};

const submit = () => {
    if (!canCompleteSale.value) {
        return;
    }

    // Open immediately within user interaction to avoid popup blockers.
    pendingPrintWindow.value = window.open('', '_blank');

    form.post('/invoices/pos', {
        preserveState: false,
        onSuccess: (inertiaPage) => {
            const printUrl = (inertiaPage.props as any)?.printPdfUrl || null;
            if (printUrl) {
                if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
                    pendingPrintWindow.value.location.href = printUrl;
                } else {
                    window.open(printUrl, '_blank');
                }
            } else if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
                pendingPrintWindow.value.close();
            }
            pendingPrintWindow.value = null;
            resetSale();
        },
        onError: () => {
            if (pendingPrintWindow.value && !pendingPrintWindow.value.closed) {
                pendingPrintWindow.value.close();
            }
            pendingPrintWindow.value = null;
        },
    });
};
</script>

<template>
    <Head title="Point of Sale" />

    <AppLayout :breadcrumbs="[
        { title: 'Invoices', href: invoices.index().url },
        { title: 'POS', href: '#' }
    ]">
        <div class="p-4 space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Point of Sale</h1>
                <Link :href="invoices.index().url" class="rounded border px-3 py-2 text-sm">Back to Invoices</Link>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <div class="bg-white border rounded-lg p-5 grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div class="relative">
                            <label class="block text-sm mb-1">Customer *</label>
                            <div class="relative">
                                <input
                                    v-model="customerSearchQuery"
                                    @input="handleCustomerSearch"
                                    @focus="customerSearchFocused = true"
                                    @blur="handleCustomerBlur"
                                    type="text"
                                    :placeholder="selectedCustomer ? selectedCustomer.name : 'Search customer by name, email, phone, or account code'"
                                    class="w-full rounded border px-3 py-2"
                                    required
                                />
                                <button
                                    v-if="selectedCustomer"
                                    @click.prevent="clearCustomer"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                >
                                    &times;
                                </button>
                            </div>
                            <div
                                v-if="customerSearchFocused && (filteredCustomers.length > 0 || customerSearchQuery)"
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
                            >
                                <div
                                    v-for="customer in filteredCustomers"
                                    :key="customer.id"
                                    @mousedown.prevent="selectCustomer(customer)"
                                    class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
                                >
                                    <div class="font-medium">{{ customer.name }}</div>
                                </div>
                            </div>
                            <div v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.customer_id }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Invoice Date *</label>
                            <input v-model="form.invoice_date" type="date" class="w-full rounded border px-3 py-2" required />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Order Number</label>
                            <input
                                v-model="form.order_number"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Optional"
                            />
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Payment Terms</label>
                            <select v-model="form.terms" class="w-full rounded border px-3 py-2">
                                <option v-for="term in paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Due Date</label>
                            <input v-model="form.due_date" type="date" class="w-full rounded border px-3 py-2 bg-gray-50" readonly />
                        </div>
                    </div>

                    <div class="bg-white border rounded-lg p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="font-semibold">Line Items</div>
                            <div class="text-xs text-gray-500">
                                Tax and account use company defaults
                                <span v-if="props.defaultSalesTaxRate"> ({{ props.defaultSalesTaxRate.name }} {{ props.defaultSalesTaxRate.rate }}%)</span>
                            </div>
                        </div>
                        <div v-if="props.defaultSalesAccountLabel" class="text-xs text-gray-500 -mt-1">
                            Default account: {{ props.defaultSalesAccountLabel }}
                        </div>

                        <div v-for="(item, index) in form.line_items" :key="index" class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end rounded-md border p-3">
                            <div class="md:col-span-2 relative">
                                <label class="text-xs text-gray-500">Description</label>
                                <div class="flex items-center gap-1">
                                    <input
                                        v-model="item.description"
                                        @input="handleDescriptionInput(index)"
                                        @focus="showProductSuggestions[index] = true"
                                        @blur="handleDescriptionBlur(index)"
                                        class="w-full rounded border px-2 py-1.5"
                                        placeholder="Type description or search product..."
                                        required
                                    />
                                    <span
                                        v-if="item.product_id"
                                        class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-xs text-blue-700 border border-blue-200"
                                        :title="String(item.product_id)"
                                    >
                                        Linked
                                        <button type="button" @click="unlinkProduct(index)" class="ml-1 text-blue-500 hover:text-blue-700">&times;</button>
                                    </span>
                                </div>

                                <div
                                    v-if="showProductSuggestions[index] && productSuggestions(index).length > 0"
                                    class="absolute z-20 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-48 overflow-auto"
                                >
                                    <div
                                        v-for="product in productSuggestions(index)"
                                        :key="product.id"
                                        @mousedown.prevent="selectProductSuggestion(index, product)"
                                        class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b border-gray-50 last:border-b-0"
                                    >
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="font-medium text-gray-900">{{ product.name }}</span>
                                                <span v-if="product.sku" class="text-gray-400 ml-1 text-xs">({{ product.sku }})</span>
                                            </div>
                                            <span class="text-gray-500 text-xs ml-2">{{ formatCurrency(Number(product.price || 0)) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Qty</label>
                                <input v-model.number="item.quantity" type="number" min="1" class="w-full rounded border px-2 py-1.5" required />
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Price</label>
                                <input v-model.number="item.unit_price" type="number" step="0.01" class="w-full rounded border px-2 py-1.5" required />
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-medium">{{ formatCurrency(lineTotal(item)) }}</span>
                                <button type="button" class="text-red-600 text-sm" :disabled="form.line_items.length === 1" @click="removeLineItem(index)">Remove</button>
                            </div>
                            <div>
                                <label class="text-xs text-gray-500">Discount %</label>
                                <input v-model.number="item.discount_percentage" type="number" step="0.01" min="0" max="100" class="w-full rounded border px-2 py-1.5" />
                            </div>
                        </div>

                        <button type="button" class="rounded border px-3 py-1.5 text-sm" @click="addLineItem">+ Add Item</button>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white border rounded-lg p-5 space-y-6 lg:sticky lg:top-4">
                        <div class="space-y-3">
                        <div class="font-semibold">Payment</div>
                        <div>
                            <label class="block text-sm mb-1">Payment Method *</label>
                            <div class="grid grid-cols-4 gap-2">
                                <button
                                    v-for="method in paymentMethodOptions"
                                    :key="method.value"
                                    type="button"
                                    @click="form.payment_method = method.value"
                                    :class="[
                                        'rounded-md border px-3 py-2 text-sm font-medium transition-colors',
                                        form.payment_method === method.value
                                            ? 'border-blue-500 bg-blue-50 text-blue-700'
                                            : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                    ]"
                                >
                                    {{ method.label }}
                                </button>
                            </div>
                        </div>
                        <div v-if="form.payment_method !== 'account'">
                            <label class="block text-sm mb-1">Amount Paid *</label>
                            <input v-model.number="form.amount_paid" type="number" step="0.01" min="0.01" class="w-full rounded border px-3 py-2" required />
                        </div>
                        <div v-else class="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                            Account sale selected. No payment will be recorded.
                        </div>
                        <div v-if="form.payment_method === 'cash'">
                            <label class="block text-sm mb-1">Cash Tendered</label>
                            <input v-model.number="form.tendered_amount" type="number" step="0.01" min="0" class="w-full rounded border px-3 py-2" />
                            <p class="text-sm mt-1">Change: <span class="font-semibold">{{ formatCurrency(changeDue) }}</span></p>
                        </div>
                        <div>
                            <label class="block text-sm mb-1">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="w-full rounded border px-3 py-2" />
                        </div>
                        </div>

                        <div class="space-y-2">
                            <div class="font-semibold">Totals</div>
                            <div class="flex justify-between"><span>Subtotal</span><span>{{ formatCurrency(subtotal) }}</span></div>
                            <div class="flex justify-between">
                                <span>Tax <span v-if="props.defaultSalesTaxRate">({{ props.defaultSalesTaxRate.rate }}%)</span></span>
                                <span>{{ formatCurrency(taxAmount) }}</span>
                            </div>
                            <div
                                v-if="Math.abs(roundingAdjustment) >= 0.0001"
                                class="flex justify-between text-sm text-gray-600"
                            >
                                <span>Rounding</span>
                                <span>{{ formatCurrency(roundingAdjustment) }}</span>
                            </div>
                            <div class="flex justify-between border-t pt-2 text-lg font-semibold"><span>Total</span><span>{{ formatCurrency(total) }}</span></div>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing || !canCompleteSale"
                            class="w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {{ form.processing ? 'Processing...' : 'Complete Sale' }}
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
