<script setup lang="ts">
import { computed } from 'vue';
import { usePermissionError } from '@/composables/usePermissionError';

interface Props {
    module: string;
    ability: string;
    fallback?: 'modal' | 'hide' | 'disable';
}

const props = withDefaults(defineProps<Props>(), {
    fallback: 'modal',
});

const { showError } = usePermissionError();

// Get the current page props to check abilities
const page = (window as any).$page;
const hasPermission = computed(() => {
    if (!page?.props?.auth?.abilities) {
        return false;
    }

    const moduleAbilities = page.props.auth.abilities[props.module];
    if (!moduleAbilities) {
        return false;
    }

    return !!moduleAbilities[props.ability];
});

function handleClick(event: Event) {
    if (!hasPermission.value) {
        event.preventDefault();
        event.stopPropagation();
        
        if (props.fallback === 'modal') {
            showError({
                module: props.module,
                ability: props.ability,
                message: `You don't have permission to ${props.ability} ${props.module}.`,
            });
        }
    }
}
</script>

<template>
    <div
        v-if="fallback === 'hide' && !hasPermission"
        style="display: none;"
    >
        <slot />
    </div>
    
    <div
        v-else-if="fallback === 'disable' && !hasPermission"
        class="opacity-50 cursor-not-allowed"
        @click="handleClick"
    >
        <slot />
    </div>
    
    <div
        v-else
        @click="handleClick"
    >
        <slot />
    </div>
</template>
