import { reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';

const page = reactive({
    props: {
        auth: {
            abilities: {
                invoices: {
                    view: true,
                    edit: false,
                },
            },
        },
    },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
}));

import { useAuthAbility } from './useAuthAbilities';

describe('useAuthAbility', () => {
    it('reads known permissions from shared page props', () => {
        expect(useAuthAbility('invoices', 'view').value).toBe(true);
        expect(useAuthAbility('invoices', 'edit').value).toBe(false);
    });

    it('returns false for missing modules or abilities', () => {
        expect(useAuthAbility('quotes', 'view').value).toBe(false);
        expect(useAuthAbility('invoices', 'delete').value).toBe(false);
    });
});
