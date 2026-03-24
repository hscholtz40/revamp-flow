import { usePage } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

function readAbility(
    abilities: Record<string, Record<string, boolean>> | null | undefined,
    module: string,
    ability: string,
): boolean {
    if (!abilities) {
        return false;
    }
    const mod = abilities[module];

    return !!(mod && mod[ability]);
}

/** Reactive check against shared Inertia `auth.abilities` (same source as the sidebar). */
export function useAuthAbility(module: string, ability: string): ComputedRef<boolean> {
    const page = usePage();

    return computed(() =>
        readAbility(
            (page.props.auth as { abilities?: Record<string, Record<string, boolean>> | null } | undefined)?.abilities ??
                null,
            module,
            ability,
        ),
    );
}
