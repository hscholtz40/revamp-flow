import { beforeEach, describe, expect, it } from 'vitest';
import { usePermissionError } from './usePermissionError';

describe('usePermissionError', () => {
    beforeEach(() => {
        const permissionError = usePermissionError();
        permissionError.hideError();
    });

    it('shows and hides the permission modal state', () => {
        const permissionError = usePermissionError();

        permissionError.showError({
            module: 'customers',
            ability: 'edit',
            message: 'Not allowed',
        });

        expect(permissionError.showPermissionModal.value).toBe(true);
        expect(permissionError.permissionError.value?.module).toBe('customers');

        permissionError.hideError();

        expect(permissionError.showPermissionModal.value).toBe(false);
        expect(permissionError.permissionError.value).toBeNull();
    });

    it('maps known and unknown modules and abilities', () => {
        const permissionError = usePermissionError();

        expect(permissionError.getModuleDisplayName('users')).toBe('Users');
        expect(permissionError.getModuleDisplayName('purchase-orders')).toBe('Purchase-orders');
        expect(permissionError.getAbilityDisplayName('create')).toBe('create');
        expect(permissionError.getAbilityDisplayName('archive')).toBe('archive');
    });
});
