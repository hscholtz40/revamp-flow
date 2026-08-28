import { useAuthAbility } from '@/composables/useAuthAbilities';
import { getCsrfToken } from '@/lib/csrf';
import { ref, type Ref } from 'vue';
import { toast } from 'vue-sonner';
import type { QuickCreateSupplierForm } from '@/components/QuickCreateSupplierModal.vue';

export interface QuoteLineSupplier {
    id: number;
    name: string;
}

function mapValidationErrors(errors: Record<string, string | string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)]),
    );
}

export function useQuoteSupplierQuickAdd(options: {
    initialSuppliers: QuoteLineSupplier[];
    applySupplierToLine: (index: number, supplierId: number) => void;
    getSuggestedName?: (index: number) => string;
    onSupplierApplied?: () => void;
}) {
    const canSuppliersCreate = useAuthAbility('suppliers', 'create');
    const availableSuppliers = ref<QuoteLineSupplier[]>([...options.initialSuppliers]);
    const showQuickCreateSupplierModal = ref(false);
    const quickCreateLineIndex = ref<number | null>(null);
    const supplierQuickCreateForm = ref<QuickCreateSupplierForm>({
        name: '',
        email: '',
        phone: '',
        vat_number: '',
        processing: false,
        errors: {},
    });

    function openQuickCreateSupplier(index: number) {
        quickCreateLineIndex.value = index;
        supplierQuickCreateForm.value = {
            name: options.getSuggestedName?.(index)?.trim() ?? '',
            email: '',
            phone: '',
            vat_number: '',
            processing: false,
            errors: {},
        };
        showQuickCreateSupplierModal.value = true;
    }

    async function submitQuickCreateSupplier() {
        const index = quickCreateLineIndex.value;
        if (index === null) {
            return;
        }

        const form = supplierQuickCreateForm.value;
        form.processing = true;
        form.errors = {};

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
                    name: form.name,
                    email: form.email || null,
                    phone: form.phone || null,
                    vat_number: form.vat_number || null,
                    is_active: true,
                }),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && data.id) {
                const supplier: QuoteLineSupplier = {
                    id: data.id,
                    name: data.name,
                };

                availableSuppliers.value = [...availableSuppliers.value, supplier].sort((a, b) =>
                    a.name.localeCompare(b.name),
                );
                options.applySupplierToLine(index, supplier.id);
                options.onSupplierApplied?.();
                showQuickCreateSupplierModal.value = false;
                quickCreateLineIndex.value = null;
                toast.success(`Supplier "${supplier.name}" added`);
                return;
            }

            if (response.status === 422 && data.errors) {
                form.errors = mapValidationErrors(data.errors);
                return;
            }

            if (response.status === 403) {
                form.errors = { name: 'You do not have permission to create suppliers.' };
                return;
            }

            form.errors = { name: 'Could not create supplier.' };
        } catch {
            form.errors = { name: 'Could not create supplier.' };
        } finally {
            form.processing = false;
        }
    }

    return {
        availableSuppliers: availableSuppliers as Ref<QuoteLineSupplier[]>,
        canSuppliersCreate,
        openQuickCreateSupplier,
        showQuickCreateSupplierModal,
        submitQuickCreateSupplier,
        supplierQuickCreateForm,
    };
}
