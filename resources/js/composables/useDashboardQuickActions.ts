import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export type DashboardQuickActionKey =
    | 'quote'
    | 'invoice'
    | 'pos'
    | 'jobcard'
    | 'customer'
    | 'supplier'
    | 'category'
    | 'product';

export function createDefaultDashboardQuickActions(): Record<DashboardQuickActionKey, boolean> {
    return {
        quote: true,
        invoice: true,
        pos: true,
        jobcard: true,
        customer: true,
        supplier: true,
        category: true,
        product: true,
    };
}

export function useDashboardQuickActions() {
    const page = usePage();

    const actions = computed(
        () =>
            (page.props.auth as { user?: { dashboard_quick_actions?: Record<string, boolean> } } | undefined)
                ?.user?.dashboard_quick_actions ?? createDefaultDashboardQuickActions(),
    );

    const isEnabled = (key: DashboardQuickActionKey): boolean => actions.value[key] !== false;

    return {
        actions,
        isEnabled,
    };
}
