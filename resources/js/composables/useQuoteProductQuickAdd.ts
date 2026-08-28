import { useAuthAbility } from '@/composables/useAuthAbilities';
import { getCsrfToken } from '@/lib/csrf';
import { ref, watch, type Ref } from 'vue';
import { toast } from 'vue-sonner';
import type { QuickCreateProductForm } from '@/components/QuickCreateProductModal.vue';

export interface QuoteLineProduct {
    id: number;
    name: string;
    price: number;
    cost?: number | null;
    type: string;
    sku?: string | null;
    supplier_id?: number | null;
}

function mapValidationErrors(errors: Record<string, string | string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)]),
    );
}

export function useQuoteProductQuickAdd(options: {
    initialProducts: QuoteLineProduct[];
    getLineDescription: (index: number) => string;
    getLineUnitPrice: (index: number) => number;
    getLineCost: (index: number) => number;
    applyProductToLine: (index: number, product: QuoteLineProduct) => void;
    closeSuggestions?: (index: number) => void;
    onProductApplied?: () => void;
}) {
    const canProductsCreate = useAuthAbility('products', 'create');
    const availableProducts = ref<QuoteLineProduct[]>([...options.initialProducts]);
    const showQuickCreateProductModal = ref(false);
    const quickCreateLineIndex = ref<number | null>(null);
    const productQuickCreateForm = ref<QuickCreateProductForm>({
        name: '',
        type: 'product',
        price: 0,
        cost: 0,
        category: '',
        processing: false,
        errors: {},
    });

    watch(
        () => options.initialProducts,
        (products) => {
            availableProducts.value = [...products];
        },
        { deep: true },
    );

    function canQuickAddProduct(index: number): boolean {
        if (!canProductsCreate.value) {
            return false;
        }

        return options.getLineDescription(index).trim().length >= 2;
    }

    function quickAddProductLabel(index: number): string {
        return options.getLineDescription(index).trim();
    }

    function openQuickCreateProduct(index: number) {
        options.closeSuggestions?.(index);
        quickCreateLineIndex.value = index;
        productQuickCreateForm.value = {
            name: options.getLineDescription(index).trim(),
            type: 'product',
            price: options.getLineUnitPrice(index) || 0,
            cost: options.getLineCost(index) || 0,
            category: '',
            processing: false,
            errors: {},
        };
        showQuickCreateProductModal.value = true;
    }

    async function submitQuickCreateProduct() {
        const index = quickCreateLineIndex.value;
        if (index === null) {
            return;
        }

        const form = productQuickCreateForm.value;
        form.processing = true;
        form.errors = {};

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
                    name: form.name,
                    type: form.type,
                    price: form.price,
                    cost: form.cost || null,
                    category: form.category || null,
                    unit: 'piece',
                    track_stock: form.type === 'product',
                    stock_quantity: 0,
                    min_stock_level: 0,
                    is_active: true,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.id) {
                const product: QuoteLineProduct = {
                    id: data.id,
                    name: data.name,
                    price: Number(data.price ?? form.price),
                    cost: data.cost != null ? Number(data.cost) : form.cost,
                    type: data.type ?? form.type,
                    sku: data.sku ?? null,
                    supplier_id: data.supplier_id ?? null,
                };

                availableProducts.value = [...availableProducts.value, product].sort((a, b) =>
                    a.name.localeCompare(b.name),
                );
                options.applyProductToLine(index, product);
                options.onProductApplied?.();
                showQuickCreateProductModal.value = false;
                quickCreateLineIndex.value = null;
                toast.success(`Product "${product.name}" added`);
                return;
            }

            if (response.status === 422 && data.errors) {
                form.errors = mapValidationErrors(data.errors);
                return;
            }

            if (response.status === 403) {
                form.errors = { name: 'You do not have permission to create products.' };
                return;
            }

            form.errors = { name: 'Could not create product.' };
        } catch {
            form.errors = { name: 'Could not create product.' };
        } finally {
            form.processing = false;
        }
    }

    return {
        availableProducts: availableProducts as Ref<QuoteLineProduct[]>,
        canProductsCreate,
        canQuickAddProduct,
        openQuickCreateProduct,
        productQuickCreateForm,
        quickAddProductLabel,
        showQuickCreateProductModal,
        submitQuickCreateProduct,
    };
}
