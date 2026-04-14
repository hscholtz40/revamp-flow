import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('vue-sonner', () => ({
    toast: {
        error: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends Record<string, unknown>>(initial: T) => {
        const form = {
            ...initial,
            errors: {} as Record<string, string>,
            clearErrors() {
                form.errors = {};
            },
            setError(errors: Record<string, string>) {
                form.errors = errors;
            },
            reset() {
                Object.assign(form, initial);
                form.errors = {};
            },
            data() {
                return {
                    name: form.name,
                    email: form.email,
                    phone: form.phone,
                    terms: form.terms,
                };
            },
        };

        return form;
    },
}));

import { useCustomerLookup } from './useCustomerLookup';
import { toast } from 'vue-sonner';

describe('useCustomerLookup', () => {
    beforeEach(() => {
        vi.mocked(toast.error).mockReset();
        document.head.innerHTML = '<meta name="csrf-token" content="csrf-token-value">';
        vi.stubGlobal('fetch', vi.fn());
    });

    it('loads matching customers from the search endpoint', async () => {
        const onSelected = vi.fn();
        const composable = useCustomerLookup({
            customers: [],
            onCustomerSelected: onSelected,
        });

        composable.customerSearchQuery.value = 'Acme';
        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            json: async () => [{ id: 1, name: 'Acme Ltd', email: 'acme@example.com', phone: '' }],
        } as Response);

        await composable.handleCustomerSearch();

        expect(fetch).toHaveBeenCalledWith('/customers/search?q=Acme', expect.any(Object));
        expect(composable.filteredCustomers.value[0]?.name).toBe('Acme Ltd');
        expect(onSelected).not.toHaveBeenCalled();
    });

    it('creates and selects a customer from the quick-create flow', async () => {
        const onSelected = vi.fn();
        const composable = useCustomerLookup({
            customers: [],
            onCustomerSelected: onSelected,
        });

        composable.customerSearchQuery.value = 'Quick Customer';
        composable.quickCreateForm.name = 'Quick Customer';
        composable.quickCreateForm.email = 'quick@example.com';

        vi.mocked(fetch).mockResolvedValueOnce({
            ok: true,
            json: async () => ({
                success: true,
                customer: { id: 7, name: 'Quick Customer', email: 'quick@example.com', phone: '' },
            }),
        } as Response);

        await composable.quickCreateCustomer();

        expect(fetch).toHaveBeenCalledWith(
            '/customers/quick-create',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({
                    'X-CSRF-TOKEN': 'csrf-token-value',
                }),
            }),
        );
        expect(composable.selectedCustomer.value?.id).toBe(7);
        expect(onSelected).toHaveBeenCalledWith(expect.objectContaining({ id: 7 }));
        expect(composable.showQuickCreateModal.value).toBe(false);
    });

    it('surfaces quick-create validation errors without showing a toast', async () => {
        const composable = useCustomerLookup({
            customers: [],
            onCustomerSelected: vi.fn(),
        });

        vi.mocked(fetch).mockResolvedValueOnce({
            ok: false,
            json: async () => ({
                errors: {
                    email: 'Email is required.',
                },
            }),
        } as Response);

        await composable.quickCreateCustomer();

        expect(composable.quickCreateForm.errors).toEqual({
            email: 'Email is required.',
        });
        expect(toast.error).not.toHaveBeenCalled();
    });
});
