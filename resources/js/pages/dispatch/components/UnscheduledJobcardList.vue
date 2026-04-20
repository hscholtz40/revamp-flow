<script setup lang="ts">
import type { DispatchJobcard } from '@/types/dispatch-board';
import { dispatchDragSession } from '@/pages/dispatch/composables/useDispatchDragSession';
import DispatchJobcardBlock from './DispatchJobcardBlock.vue';

defineProps<{
    jobcards: DispatchJobcard[];
    conflictIds?: Set<number>;
}>();

const emit = defineEmits<{
    select: [job: DispatchJobcard];
}>();

const onDragStart = (e: DragEvent, job: DispatchJobcard) => {
    const estimatedMinutes = job.estimated_duration_minutes ?? 60;
    dispatchDragSession.value = { id: job.id, kind: 'queue', estimatedMinutes };
    e.dataTransfer?.setData(
        'application/x-jobcard-dispatch',
        JSON.stringify({ id: job.id, kind: 'queue', estimatedMinutes }),
    );
    e.dataTransfer!.effectAllowed = 'move';
};

const onDragEnd = () => {
    dispatchDragSession.value = null;
};
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-3 py-2">
            <h3 class="text-sm font-semibold text-slate-900">Queue</h3>
            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">{{ jobcards.length }}</span>
        </div>
        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-2">
            <button
                v-for="job in jobcards"
                :key="job.id"
                type="button"
                draggable="true"
                class="block w-full rounded-lg border border-slate-200 bg-white text-left transition hover:border-indigo-300 hover:shadow-sm"
                @dragstart="onDragStart($event, job)"
                @dragend="onDragEnd"
                @click="emit('select', job)"
            >
                <div class="p-2">
                    <DispatchJobcardBlock :job="job" :compact="false" :conflict-ids="conflictIds" />
                    <div class="mt-2 space-y-0.5 border-t border-slate-100 pt-2 text-[10px] text-slate-600">
                        <p v-if="job.due_date">Due: {{ job.due_date }}</p>
                        <p v-else-if="job.start_date">Start: {{ job.start_date }}</p>
                        <p v-if="job.estimated_duration_minutes">Est. {{ job.estimated_duration_minutes }} min</p>
                    </div>
                </div>
            </button>
            <p v-if="jobcards.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-3 text-center text-xs text-slate-500">
                No jobcards in this queue.
            </p>
        </div>
    </div>
</template>
