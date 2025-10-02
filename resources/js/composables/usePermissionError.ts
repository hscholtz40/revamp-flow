import { ref } from 'vue';

interface PermissionError {
    module: string;
    ability: string;
    message: string;
}

const permissionError = ref<PermissionError | null>(null);
const showPermissionModal = ref(false);

export function usePermissionError() {
    const showError = (error: PermissionError) => {
        permissionError.value = error;
        showPermissionModal.value = true;
    };

    const hideError = () => {
        showPermissionModal.value = false;
        permissionError.value = null;
    };

    const getModuleDisplayName = (module: string) => {
        const moduleNames: Record<string, string> = {
            'customers': 'Customers',
            'users': 'Users',
            'groups': 'Groups',
            'contacts': 'Contacts',
        };
        return moduleNames[module] || module.charAt(0).toUpperCase() + module.slice(1);
    };

    const getAbilityDisplayName = (ability: string) => {
        const abilityNames: Record<string, string> = {
            'list': 'view the list of',
            'view': 'view',
            'create': 'create',
            'edit': 'edit',
            'delete': 'delete',
        };
        return abilityNames[ability] || ability;
    };

    return {
        permissionError,
        showPermissionModal,
        showError,
        hideError,
        getModuleDisplayName,
        getAbilityDisplayName,
    };
}
