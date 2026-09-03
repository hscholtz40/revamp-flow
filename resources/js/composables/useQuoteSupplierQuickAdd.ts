import { ref } from 'vue';
import {
    useSupplierQuickAdd,
    type SupplierQuickAddOption,
} from '@/composables/useSupplierQuickAdd';

export type QuoteLineSupplier = SupplierQuickAddOption;

export function useQuoteSupplierQuickAdd(options: {
    initialSuppliers: QuoteLineSupplier[];
    applySupplierToLine: (index: number, supplierId: number) => void;
    getSuggestedName?: (index: number) => string;
    onSupplierApplied?: () => void;
}) {
    const quickCreateLineIndex = ref<number | null>(null);

    const {
        availableSuppliers,
        canSuppliersCreate,
        openQuickCreateSupplier: openModal,
        showQuickCreateSupplierModal,
        submitQuickCreateSupplier,
        supplierQuickCreateForm,
    } = useSupplierQuickAdd({
        initialSuppliers: options.initialSuppliers,
        getSuggestedName: () => {
            const index = quickCreateLineIndex.value;
            if (index === null) {
                return '';
            }

            return options.getSuggestedName?.(index) ?? '';
        },
        canSubmit: () => quickCreateLineIndex.value !== null,
        onCreated: (supplier) => {
            const index = quickCreateLineIndex.value;
            if (index === null) {
                return;
            }

            options.applySupplierToLine(index, supplier.id);
            options.onSupplierApplied?.();
            quickCreateLineIndex.value = null;
        },
    });

    function openQuickCreateSupplier(index: number) {
        quickCreateLineIndex.value = index;
        openModal();
    }

    return {
        availableSuppliers,
        canSuppliersCreate,
        openQuickCreateSupplier,
        showQuickCreateSupplierModal,
        submitQuickCreateSupplier,
        supplierQuickCreateForm,
    };
}
