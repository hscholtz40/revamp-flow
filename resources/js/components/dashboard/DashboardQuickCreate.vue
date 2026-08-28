<script setup lang="ts">
import QuickCreateCustomerModal from '@/components/QuickCreateCustomerModal.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import { useDashboardQuickActions } from '@/composables/useDashboardQuickActions';
import { getCsrfToken } from '@/lib/csrf';
import { createQuickCreateCustomerDefaults } from '@/types/customers';
import { useForm, usePage } from '@inertiajs/vue3';
import { FolderTree, Package, Truck, UserPlus } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';

interface CategoryOption {
    id: number;
    name: string;
    color: string;
}

const props = defineProps<{
    categoryOptions?: CategoryOption[];
}>();

const page = usePage();
const isAdministrator = computed(
    () => !!(page.props.auth as { user?: { is_administrator?: boolean } } | undefined)?.user?.is_administrator,
);

const canCustomersCreate = useAuthAbility('customers', 'create');
const canSuppliersCreate = useAuthAbility('suppliers', 'create');
const canProductsCreate = useAuthAbility('products', 'create');
const canCategoriesCreate = computed(() => isAdministrator.value);
const { isEnabled: isQuickActionEnabled } = useDashboardQuickActions();

const showCustomerModal = ref(false);
const showSupplierModal = ref(false);
const showCategoryModal = ref(false);
const showProductModal = ref(false);

const quickCreateCustomerForm = useForm(createQuickCreateCustomerDefaults());

const supplierForm = reactive({
    name: '',
    email: '',
    phone: '',
    vat_number: '',
    processing: false,
    errors: {} as Record<string, string>,
});

const categoryForm = reactive({
    name: '',
    color: '#3B82F6',
    processing: false,
    errors: {} as Record<string, string>,
});

const productForm = reactive({
    name: '',
    type: 'product' as 'product' | 'service',
    price: 0,
    category: '',
    processing: false,
    errors: {} as Record<string, string>,
});

const colorOptions = [
    { name: 'Blue', value: '#3B82F6' },
    { name: 'Green', value: '#10B981' },
    { name: 'Yellow', value: '#F59E0B' },
    { name: 'Purple', value: '#8B5CF6' },
    { name: 'Orange', value: '#F97316' },
    { name: 'Red', value: '#EF4444' },
];

function resetSupplierForm() {
    supplierForm.name = '';
    supplierForm.email = '';
    supplierForm.phone = '';
    supplierForm.vat_number = '';
    supplierForm.errors = {};
}

function resetCategoryForm() {
    categoryForm.name = '';
    categoryForm.color = '#3B82F6';
    categoryForm.errors = {};
}

function resetProductForm() {
    productForm.name = '';
    productForm.type = 'product';
    productForm.price = 0;
    productForm.category = '';
    productForm.errors = {};
}

function mapValidationErrors(errors: Record<string, string | string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)]),
    );
}

async function quickCreateCustomer() {
    quickCreateCustomerForm.clearErrors();

    try {
        const response = await fetch('/customers/quick-create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify(quickCreateCustomerForm.data()),
        });

        const data = await response.json().catch(() => ({}));
        if (response.ok && data.success && data.customer) {
            showCustomerModal.value = false;
            quickCreateCustomerForm.reset();
            quickCreateCustomerForm.clearErrors();
            Object.assign(quickCreateCustomerForm, createQuickCreateCustomerDefaults());
            toast.success(`Customer "${data.customer.name}" created`);
            return;
        }

        if (data.errors) {
            quickCreateCustomerForm.setError(data.errors);
            return;
        }

        toast.error('Could not create customer.');
    } catch {
        toast.error('Could not create customer.');
    }
}

async function quickCreateSupplier() {
    supplierForm.processing = true;
    supplierForm.errors = {};

    try {
        const response = await fetch('/suppliers', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                name: supplierForm.name,
                email: supplierForm.email || null,
                phone: supplierForm.phone || null,
                vat_number: supplierForm.vat_number || null,
                is_active: true,
            }),
        });

        if (response.ok) {
            const supplier = await response.json();
            showSupplierModal.value = false;
            resetSupplierForm();
            toast.success(`Supplier "${supplier.name}" created`);
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            supplierForm.errors = mapValidationErrors(data.errors || {});
            return;
        }

        supplierForm.errors = { name: 'Failed to create supplier.' };
    } catch {
        supplierForm.errors = { name: 'Failed to create supplier.' };
    } finally {
        supplierForm.processing = false;
    }
}

async function quickCreateCategory() {
    categoryForm.processing = true;
    categoryForm.errors = {};

    try {
        const response = await fetch('/administration/categories/quick-create', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                name: categoryForm.name,
                color: categoryForm.color,
                is_active: true,
            }),
        });

        if (response.ok) {
            const category = await response.json();
            showCategoryModal.value = false;
            resetCategoryForm();
            toast.success(`Category "${category.name}" created`);
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            categoryForm.errors = mapValidationErrors(data.errors || {});
            return;
        }

        if (response.status === 403) {
            categoryForm.errors = { name: 'You do not have permission to create categories.' };
            return;
        }

        categoryForm.errors = { name: 'Failed to create category.' };
    } catch {
        categoryForm.errors = { name: 'Failed to create category.' };
    } finally {
        categoryForm.processing = false;
    }
}

async function quickCreateProduct() {
    productForm.processing = true;
    productForm.errors = {};

    try {
        const response = await fetch('/products', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: JSON.stringify({
                name: productForm.name,
                type: productForm.type,
                price: productForm.price,
                category: productForm.category || null,
                unit: 'piece',
                track_stock: productForm.type === 'product',
                stock_quantity: 0,
                min_stock_level: 0,
                is_active: true,
            }),
        });

        if (response.ok) {
            const product = await response.json();
            showProductModal.value = false;
            resetProductForm();
            toast.success(`Product "${product.name}" created`);
            return;
        }

        if (response.status === 422) {
            const data = await response.json();
            productForm.errors = mapValidationErrors(data.errors || {});
            return;
        }

        productForm.errors = { name: 'Failed to create product.' };
    } catch {
        productForm.errors = { name: 'Failed to create product.' };
    } finally {
        productForm.processing = false;
    }
}

function openCustomerModal() {
    Object.assign(quickCreateCustomerForm, createQuickCreateCustomerDefaults());
    quickCreateCustomerForm.clearErrors();
    showCustomerModal.value = true;
}

function openSupplierModal() {
    resetSupplierForm();
    showSupplierModal.value = true;
}

function openCategoryModal() {
    resetCategoryForm();
    showCategoryModal.value = true;
}

function openProductModal() {
    resetProductForm();
    showProductModal.value = true;
}
</script>

<template>
    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
        <Button
            v-if="canCustomersCreate && isQuickActionEnabled('customer')"
            type="button"
            variant="outline"
            class="w-full justify-start"
            @click="openCustomerModal"
        >
            <UserPlus class="mr-2 h-4 w-4" />
            Quick Create Customer
        </Button>
        <Button
            v-if="canSuppliersCreate && isQuickActionEnabled('supplier')"
            type="button"
            variant="outline"
            class="w-full justify-start"
            @click="openSupplierModal"
        >
            <Truck class="mr-2 h-4 w-4" />
            Quick Create Supplier
        </Button>
        <Button
            v-if="canCategoriesCreate && isQuickActionEnabled('category')"
            type="button"
            variant="outline"
            class="w-full justify-start"
            @click="openCategoryModal"
        >
            <FolderTree class="mr-2 h-4 w-4" />
            Quick Create Category
        </Button>
        <Button
            v-if="canProductsCreate && isQuickActionEnabled('product')"
            type="button"
            variant="outline"
            class="w-full justify-start"
            @click="openProductModal"
        >
            <Package class="mr-2 h-4 w-4" />
            Quick Create Product
        </Button>
    </div>

    <QuickCreateCustomerModal
        v-model="showCustomerModal"
        :form="quickCreateCustomerForm"
        @submit="quickCreateCustomer"
    />

    <div
        v-if="showSupplierModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="showSupplierModal = false"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
            <h3 class="mb-4 text-lg font-semibold">Quick Create Supplier</h3>
            <form class="space-y-4" @submit.prevent="quickCreateSupplier">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name *</span>
                    <input v-model="supplierForm.name" type="text" required class="w-full rounded border px-3 py-2" />
                    <p v-if="supplierForm.errors.name" class="mt-1 text-sm text-red-600">{{ supplierForm.errors.name }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Email</span>
                    <input v-model="supplierForm.email" type="email" class="w-full rounded border px-3 py-2" />
                    <p v-if="supplierForm.errors.email" class="mt-1 text-sm text-red-600">{{ supplierForm.errors.email }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Phone</span>
                    <input v-model="supplierForm.phone" type="text" class="w-full rounded border px-3 py-2" />
                    <p v-if="supplierForm.errors.phone" class="mt-1 text-sm text-red-600">{{ supplierForm.errors.phone }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">VAT number</span>
                    <input v-model="supplierForm.vat_number" type="text" class="w-full rounded border px-3 py-2" />
                    <p v-if="supplierForm.errors.vat_number" class="mt-1 text-sm text-red-600">{{ supplierForm.errors.vat_number }}</p>
                </label>
                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="supplierForm.processing"
                    >
                        {{ supplierForm.processing ? 'Creating...' : 'Create' }}
                    </button>
                    <button type="button" class="flex-1 rounded border px-4 py-2 hover:bg-gray-50" @click="showSupplierModal = false">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        v-if="showCategoryModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="showCategoryModal = false"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
            <h3 class="mb-4 text-lg font-semibold">Quick Create Category</h3>
            <form class="space-y-4" @submit.prevent="quickCreateCategory">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name *</span>
                    <input v-model="categoryForm.name" type="text" required class="w-full rounded border px-3 py-2" />
                    <p v-if="categoryForm.errors.name" class="mt-1 text-sm text-red-600">{{ categoryForm.errors.name }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Colour *</span>
                    <select v-model="categoryForm.color" class="w-full rounded border px-3 py-2">
                        <option v-for="option in colorOptions" :key="option.value" :value="option.value">
                            {{ option.name }}
                        </option>
                    </select>
                    <p v-if="categoryForm.errors.color" class="mt-1 text-sm text-red-600">{{ categoryForm.errors.color }}</p>
                </label>
                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="categoryForm.processing"
                    >
                        {{ categoryForm.processing ? 'Creating...' : 'Create' }}
                    </button>
                    <button type="button" class="flex-1 rounded border px-4 py-2 hover:bg-gray-50" @click="showCategoryModal = false">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        v-if="showProductModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="showProductModal = false"
    >
        <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl" @click.stop>
            <h3 class="mb-4 text-lg font-semibold">Quick Create Product</h3>
            <form class="space-y-4" @submit.prevent="quickCreateProduct">
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Name *</span>
                    <input v-model="productForm.name" type="text" required class="w-full rounded border px-3 py-2" />
                    <p v-if="productForm.errors.name" class="mt-1 text-sm text-red-600">{{ productForm.errors.name }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Type *</span>
                    <select v-model="productForm.type" class="w-full rounded border px-3 py-2">
                        <option value="product">Product</option>
                        <option value="service">Service</option>
                    </select>
                    <p v-if="productForm.errors.type" class="mt-1 text-sm text-red-600">{{ productForm.errors.type }}</p>
                </label>
                <label class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Price *</span>
                    <input v-model.number="productForm.price" type="number" min="0" step="0.01" required class="w-full rounded border px-3 py-2" />
                    <p v-if="productForm.errors.price" class="mt-1 text-sm text-red-600">{{ productForm.errors.price }}</p>
                </label>
                <label v-if="(props.categoryOptions?.length ?? 0) > 0" class="block">
                    <span class="mb-1 block text-sm font-medium text-gray-700">Category</span>
                    <select v-model="productForm.category" class="w-full rounded border px-3 py-2">
                        <option value="">No category</option>
                        <option v-for="category in props.categoryOptions" :key="category.id" :value="category.name">
                            {{ category.name }}
                        </option>
                    </select>
                    <p v-if="productForm.errors.category" class="mt-1 text-sm text-red-600">{{ productForm.errors.category }}</p>
                </label>
                <div class="flex gap-3 pt-2">
                    <button
                        type="submit"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        :disabled="productForm.processing"
                    >
                        {{ productForm.processing ? 'Creating...' : 'Create' }}
                    </button>
                    <button type="button" class="flex-1 rounded border px-4 py-2 hover:bg-gray-50" @click="showProductModal = false">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
