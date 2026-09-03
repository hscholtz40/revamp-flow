import { beforeEach, describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

const page = reactive({
    props: {
        auth: {
            abilities: {
                suppliers: {
                    create: true,
                },
            },
        },
    },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
}));

vi.mock('vue-sonner', () => ({
    toast: {
        success: vi.fn(),
        error: vi.fn(),
    },
}));

import { useSupplierQuickAdd } from './useSupplierQuickAdd';
import { toast } from 'vue-sonner';

describe('useSupplierQuickAdd', () => {
    beforeEach(() => {
        vi.mocked(toast.success).mockReset();
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-value">';
        vi.stubGlobal('fetch', vi.fn());
    });

    it('creates a supplier and assigns it via onCreated', async () => {
        const onCreated = vi.fn();
        const composable = useSupplierQuickAdd({
            initialSuppliers: [{ id: 1, name: 'Existing Co' }],
            getSuggestedName: () => 'Widget Service',
            onCreated,
        });

        composable.openQuickCreateSupplier();

        expect(composable.showQuickCreateSupplierModal.value).toBe(true);
        expect(composable.supplierQuickCreateForm.value.name).toBe('Widget Service');

        composable.supplierQuickCreateForm.value.email = 'sales@new-supplier.example';

        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                id: 42,
                name: 'Widget Service',
                email: 'sales@new-supplier.example',
            }),
        } as Response);

        await composable.submitQuickCreateSupplier();

        expect(fetch).toHaveBeenCalledWith('/suppliers', expect.objectContaining({
            method: 'POST',
        }));
        expect(onCreated).toHaveBeenCalledWith({ id: 42, name: 'Widget Service' });
        expect(composable.availableSuppliers.value.map((supplier) => supplier.id)).toEqual([1, 42]);
        expect(composable.showQuickCreateSupplierModal.value).toBe(false);
        expect(toast.success).toHaveBeenCalled();
    });

    it('maps validation errors from a 422 response', async () => {
        const composable = useSupplierQuickAdd({
            initialSuppliers: [],
            onCreated: vi.fn(),
        });

        composable.openQuickCreateSupplier();
        composable.supplierQuickCreateForm.value.name = '';

        vi.mocked(fetch).mockResolvedValueOnce({
            ok: false,
            status: 422,
            json: async () => ({
                errors: { name: ['The name field is required.'] },
            }),
        } as Response);

        await composable.submitQuickCreateSupplier();

        expect(composable.supplierQuickCreateForm.value.errors.name).toBe('The name field is required.');
        expect(composable.showQuickCreateSupplierModal.value).toBe(true);
    });
});
