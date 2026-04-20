import { ref } from 'vue';

/** Set on dragstart (queue or timeline job); read during dragover for drop preview. getData() is not available on dragover in browsers. */
export type DispatchDragSessionPayload = {
    id: number;
    kind: 'queue' | 'board';
    estimatedMinutes?: number;
};

export const dispatchDragSession = ref<DispatchDragSessionPayload | null>(null);
