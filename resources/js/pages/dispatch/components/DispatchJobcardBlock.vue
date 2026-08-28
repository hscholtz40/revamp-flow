<script setup lang="ts">
import type { DispatchConflict, DispatchJobcard } from '@/types/dispatch-board';
import { jobcardStatusBadgeClass } from '@/lib/jobcardStatuses';
import { computed } from 'vue';

const props = defineProps<{
    job: DispatchJobcard;
    compact?: boolean;
    conflictIds?: Set<number>;
}>();

const displayTitle = computed(() => props.job.job_number || `Jobcard #${props.job.id}`);
const customerShort = computed(() => {
    const c = props.job.customer;
    if (!c) return '';
    return c.city || (c.address ? String(c.address).split(',')[0]?.trim() : '') || c.name;
});
const statusBadge = computed(() => jobcardStatusBadgeClass(props.job.status ?? 'new'));
const priorityBadge = computed(() => {
    const p = props.job.priority ?? 'normal';
    if (p === 'urgent') return 'border-red-300 bg-red-50 text-red-800';
    if (p === 'high') return 'border-orange-300 bg-orange-50 text-orange-900';
    if (p === 'low') return 'border-slate-200 bg-slate-50 text-slate-600';
    return 'border-slate-200 bg-white text-slate-700';
});
const hasConflict = computed(() => props.conflictIds?.has(props.job.id) ?? false);
</script>

<template>
    <div
        class="flex h-full min-h-[52px] flex-col overflow-hidden rounded-md border border-slate-200 bg-white px-1.5 py-1 text-left shadow-sm"
        :class="hasConflict ? 'ring-2 ring-amber-400' : ''"
    >
        <div class="flex items-start justify-between gap-1">
            <span class="truncate text-[11px] font-bold leading-tight text-slate-900">{{ displayTitle }}</span>
            <span v-if="hasConflict" class="shrink-0 text-amber-600" title="Scheduling conflict">!</span>
        </div>
        <div v-if="!compact" class="truncate text-[10px] leading-tight text-slate-600">{{ job.customer?.name }}</div>
        <div v-if="!compact" class="truncate text-[10px] text-slate-500">{{ customerShort }}</div>
        <div class="mt-auto flex flex-wrap gap-0.5 pt-0.5">
            <span class="rounded px-1 py-0.5 text-[9px] font-semibold uppercase tracking-wide" :class="statusBadge">
                {{ (job.status ?? 'new').replace(/_/g, ' ') }}
            </span>
            <span
                v-if="job.priority && job.priority !== 'normal'"
                class="rounded border px-1 py-0.5 text-[9px] font-semibold uppercase"
                :class="priorityBadge"
            >
                {{ job.priority }}
            </span>
        </div>
    </div>
</template>
