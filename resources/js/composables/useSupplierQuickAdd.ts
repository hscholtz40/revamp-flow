import { useAuthAbility } from '@/composables/useAuthAbilities';
import { getCsrfToken } from '@/lib/csrf';
import { ref, type Ref } from 'vue';
import { toast } from 'vue-sonner';
import type { QuickCreateSupplierForm } from '@/components/QuickCreateSupplierModal.vue';

export interface SupplierQuickAddOption {
    id: number;
    name: string;
}

function mapValidationErrors(errors: Record<string, string | string[]>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(errors).map(([key, value]) => [key, Array.isArray(value) ? String(value[0]) : String(value)]),
    );
}

export function useSupplierQuickAdd(options: {
    initialSuppliers: SupplierQuickAddOption[];
    onCreated: (supplier: SupplierQuickAddOption) => void;
    getSuggestedName?: () => string;
    canSubmit?: () => boolean;
}) {
    const canSuppliersCreate = useAuthAbility('suppliers', 'create');
    const availableSuppliers = ref<SupplierQuickAddOption[]>([...options.initialSuppliers]);
    const showQuickCreateSupplierModal = ref(false);
    const supplierQuickCreateForm = ref<QuickCreateSupplierForm>({
        name: '',
        email: '',
        phone: '',
        vat_number: '',
        processing: false,
        errors: {},
    });

    function openQuickCreateSupplier() {
        supplierQuickCreateForm.value = {
            name: options.getSuggestedName?.()?.trim() ?? '',
            email: '',
            phone: '',
            vat_number: '',
            processing: false,
            errors: {},
        };
        showQuickCreateSupplierModal.value = true;
    }

    async function submitQuickCreateSupplier() {
        if (options.canSubmit && !options.canSubmit()) {
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
                const supplier: SupplierQuickAddOption = {
                    id: data.id,
                    name: data.name,
                };

                availableSuppliers.value = [...availableSuppliers.value, supplier].sort((a, b) =>
                    a.name.localeCompare(b.name),
                );
                options.onCreated(supplier);
                showQuickCreateSupplierModal.value = false;
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
        availableSuppliers: availableSuppliers as Ref<SupplierQuickAddOption[]>,
        canSuppliersCreate,
        openQuickCreateSupplier,
        showQuickCreateSupplierModal,
        submitQuickCreateSupplier,
        supplierQuickCreateForm,
    };
}
