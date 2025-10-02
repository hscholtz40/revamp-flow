import { usePermissionError } from '@/composables/usePermissionError';

export function checkPermission(module: string, ability: string): boolean {
    // Get the current page props to check abilities
    const page = (window as any).$page;
    if (!page?.props?.auth?.abilities) {
        return false;
    }

    const moduleAbilities = page.props.auth.abilities[module];
    if (!moduleAbilities) {
        return false;
    }

    return !!moduleAbilities[ability];
}

export function requirePermission(module: string, ability: string): boolean {
    const hasPermission = checkPermission(module, ability);
    
    if (!hasPermission) {
        const { showError } = usePermissionError();
        showError({
            module,
            ability,
            message: `You don't have permission to ${ability} ${module}.`,
        });
    }
    
    return hasPermission;
}

export function canAccess(module: string, ability: string): boolean {
    return checkPermission(module, ability);
}
