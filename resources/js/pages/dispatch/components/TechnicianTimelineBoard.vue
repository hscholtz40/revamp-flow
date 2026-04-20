<script setup lang="ts">
import type { DispatchJobcard, DispatchLookup } from '@/types/dispatch-board';
import { dispatchDragSession } from '@/pages/dispatch/composables/useDispatchDragSession';
import DispatchJobcardBlock from './DispatchJobcardBlock.vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    boardDate: string;
    users: DispatchLookup[];
    jobcards: DispatchJobcard[];
    conflictIds?: Set<number>;
    dayStartHour?: number;
    dayEndHour?: number;
    slotMinutes?: number;
    showAllHours?: boolean;
}>();

const emit = defineEmits<{
    select: [job: DispatchJobcard];
    'drop-jobcard': [payload: { jobcardId: number; assignedToUserId: number | null; start: string; end: string | null }];
    'move-jobcard': [payload: { jobcardId: number; assignedToUserId: number | null; start: string; end: string | null }];
    'toggle-all-hours': [value: boolean];
}>();

const dayStartHour = computed(() => (props.showAllHours ? 0 : props.dayStartHour ?? 6));
const dayEndHour = computed(() => (props.showAllHours ? 24 : props.dayEndHour ?? 20));
const slotMinutes = computed(() => props.slotMinutes ?? 15);
const slotWidthPx = 28;

const totalMinutes = computed(() => (dayEndHour.value - dayStartHour.value) * 60);

const slotCount = computed(() => Math.ceil(totalMinutes.value / slotMinutes.value));

const timelineWidthPx = computed(() => slotCount.value * slotWidthPx);

const atLocal = (dayIso: string, hour: number, minute: number): Date => {
    const [y, m, d] = dayIso.split('-').map(Number);
    return new Date(y, m - 1, d, hour, minute, 0, 0);
};

const slotHeaders = computed(() => {
    const list: { label: string; key: string }[] = [];
    for (let i = 0; i < slotCount.value; i++) {
        const minutesInto = i * slotMinutes.value;
        const totalFromMidnight = dayStartHour.value * 60 + minutesInto;
        const h = Math.floor(totalFromMidnight / 60);
        const m = totalFromMidnight % 60;
        const label = m === 0 ? `${String(h).padStart(2, '0')}:00` : '';
        list.push({ label, key: `slot-${i}` });
    }
    return list;
});

const rowUsers = computed(() => {
    const unassigned: DispatchLookup = { id: -1, name: 'Unassigned' };
    return [unassigned, ...props.users];
});

const jobsForRow = (row: DispatchLookup) => {
    return props.jobcards.filter((j) => {
        if (!j.scheduled_start_at) return false;
        if (row.id === -1) {
            return !j.assigned_to_user_id;
        }
        return j.assigned_to_user_id === row.id;
    });
};

const minutesFromDayStart = (iso: string): number => {
    const dt = new Date(iso);
    if (Number.isNaN(dt.getTime())) return 0;
    const start = atLocal(props.boardDate, dayStartHour.value, 0);
    return Math.max(0, (dt.getTime() - start.getTime()) / 60000);
};

const jobStyle = (job: DispatchJobcard) => {
    if (!job.scheduled_start_at) return { display: 'none' };
    const startM = minutesFromDayStart(job.scheduled_start_at);
    let endM = job.scheduled_end_at ? minutesFromDayStart(job.scheduled_end_at) : startM + (job.estimated_duration_minutes ?? 60);
    endM = Math.max(endM, startM + slotMinutes.value);
    const dur = endM - startM;
    const left = (startM / totalMinutes.value) * 100;
    const width = (dur / totalMinutes.value) * 100;
    return {
        left: `${Math.min(100, left)}%`,
        width: `${Math.max((slotMinutes.value / totalMinutes.value) * 100, width)}%`,
        top: '4px',
        bottom: '8px',
    };
};

const nowTick = ref(Date.now());
let nowTimer: ReturnType<typeof setInterval> | null = null;

const nowLineStyle = computed(() => {
    const now = new Date(nowTick.value);
    const [y, mo, d] = props.boardDate.split('-').map(Number);
    const dayDate = new Date(y, mo - 1, d);
    if (now.toDateString() !== dayDate.toDateString()) {
        return { display: 'none' as const };
    }
    const start = atLocal(props.boardDate, dayStartHour.value, 0);
    const m = (now.getTime() - start.getTime()) / 60000;
    if (m < 0 || m > totalMinutes.value) return { display: 'none' as const };
    return {
        display: 'block' as const,
        left: `${(m / totalMinutes.value) * 100}%`,
    };
});

const formatIsoLocal = (d: Date): string => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
};

const formatClock = (d: Date) => {
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const formatTimeLabel = (startIso: string, endIso: string | null) => {
    const a = new Date(startIso);
    const b = endIso ? new Date(endIso) : null;
    if (Number.isNaN(a.getTime())) return '';
    if (b && !Number.isNaN(b.getTime())) return `${formatClock(a)} – ${formatClock(b)}`;
    return formatClock(a);
};

const dropPreview = ref<{
    rowId: number;
    left: string;
    width: string;
    label: string;
} | null>(null);

interface DragMeta {
    id: number;
    kind: 'queue' | 'board';
    estimatedMinutes?: number;
}

const parseDragMeta = (e: DragEvent): DragMeta | null => {
    const raw = e.dataTransfer?.getData('application/x-jobcard-dispatch');
    if (!raw) return null;
    try {
        const o = JSON.parse(raw) as { id?: number; kind?: string; estimatedMinutes?: number };
        if (typeof o.id !== 'number') return null;
        const kind = o.kind === 'board' ? 'board' : 'queue';
        return { id: o.id, kind, estimatedMinutes: typeof o.estimatedMinutes === 'number' ? o.estimatedMinutes : undefined };
    } catch {
        return null;
    }
};

const timeFromClientX = (e: DragEvent, el: HTMLElement, durationMinutes: number): { start: string; end: string | null } => {
    const rect = el.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const ratio = Math.min(1, Math.max(0, x / rect.width));
    const minutesInto = ratio * totalMinutes.value;
    const snapped = Math.round(minutesInto / slotMinutes.value) * slotMinutes.value;
    const start = atLocal(props.boardDate, dayStartHour.value, 0);
    start.setMinutes(start.getMinutes() + snapped);
    const end = new Date(start.getTime() + durationMinutes * 60000);
    return {
        start: formatIsoLocal(start),
        end: formatIsoLocal(end),
    };
};

const durationForMeta = (meta: DragMeta): number => {
    if (typeof meta.estimatedMinutes === 'number' && meta.estimatedMinutes > 0) {
        return meta.estimatedMinutes;
    }
    const job = props.jobcards.find((j) => j.id === meta.id);
    return job?.estimated_duration_minutes ?? 60;
};

const onDropRow = (e: DragEvent, row: DispatchLookup) => {
    e.preventDefault();
    const meta = parseDragMeta(e);
    const el = e.currentTarget as HTMLElement;
    if (!meta || !el) return;
    const dur = durationForMeta(meta);
    const times = timeFromClientX(e, el, dur);
    const userId = row.id === -1 ? null : row.id;
    const payload = {
        jobcardId: meta.id,
        assignedToUserId: userId,
        start: times.start,
        end: times.end,
    };
    if (meta.kind === 'board') {
        emit('move-jobcard', payload);
    } else {
        emit('drop-jobcard', payload);
    }
};

const onDragOverRow = (e: DragEvent, row: DispatchLookup) => {
    e.preventDefault();
    if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
    const meta = dispatchDragSession.value;
    if (!meta) {
        dropPreview.value = null;
        return;
    }
    const el = e.currentTarget as HTMLElement;
    const dur = durationForMeta(meta);
    const times = timeFromClientX(e, el, dur);
    const startM = minutesFromDayStart(times.start);
    let endM = minutesFromDayStart(times.end!);
    endM = Math.max(endM, startM + slotMinutes.value);
    const durM = endM - startM;
    const left = (startM / totalMinutes.value) * 100;
    const width = (durM / totalMinutes.value) * 100;
    dropPreview.value = {
        rowId: row.id,
        left: `${Math.min(100, left)}%`,
        width: `${Math.max((slotMinutes.value / totalMinutes.value) * 100, width)}%`,
        label: formatTimeLabel(times.start, times.end),
    };
};

const onDocumentDragOverCapture = (e: DragEvent) => {
    if (!dispatchDragSession.value) {
        return;
    }
    const el = e.target as HTMLElement | null;
    if (!el?.closest?.('[data-timeline-row]')) {
        dropPreview.value = null;
    }
};

const onWindowDragEnd = () => {
    dispatchDragSession.value = null;
    dropPreview.value = null;
};

const onDragStartJob = (e: DragEvent, job: DispatchJobcard) => {
    const minutes = job.scheduled_end_at
        ? Math.max(slotMinutes.value, (new Date(job.scheduled_end_at).getTime() - new Date(job.scheduled_start_at!).getTime()) / 60000)
        : job.estimated_duration_minutes ?? 60;
    const rounded = Math.round(minutes);
    dispatchDragSession.value = { id: job.id, kind: 'board', estimatedMinutes: rounded };
    e.dataTransfer?.setData(
        'application/x-jobcard-dispatch',
        JSON.stringify({ id: job.id, kind: 'board', estimatedMinutes: rounded }),
    );
};

/** Resize (east edge: duration follows the horizontal time axis, not row height). */
const resizing = ref<{
    id: number;
    rowId: number;
    rowEl: HTMLElement;
    startMs: number;
    origEnd: Date;
    startX: number;
    previewEnd: Date;
} | null>(null);

const resizePreviewDisplay = computed(() => {
    const r = resizing.value;
    if (!r) return null;
    const end = r.previewEnd;
    const startIso = formatIsoLocal(new Date(r.startMs));
    const endIso = formatIsoLocal(end);
    const startM = minutesFromDayStart(startIso);
    let endM = minutesFromDayStart(endIso);
    endM = Math.max(endM, startM + slotMinutes.value);
    const durM = endM - startM;
    const left = (startM / totalMinutes.value) * 100;
    const width = (durM / totalMinutes.value) * 100;
    return {
        rowId: r.rowId,
        left: `${Math.min(100, left)}%`,
        width: `${Math.max((slotMinutes.value / totalMinutes.value) * 100, width)}%`,
        label: formatTimeLabel(startIso, endIso),
    };
});

const onResizeMouseDown = (e: MouseEvent, job: DispatchJobcard, row: DispatchLookup) => {
    e.preventDefault();
    e.stopPropagation();
    if (!job.scheduled_start_at) return;
    const rowEl = (e.target as HTMLElement).closest('[data-timeline-row]') as HTMLElement | null;
    if (!rowEl) return;
    const startMs = new Date(job.scheduled_start_at).getTime();
    const origEnd = job.scheduled_end_at ? new Date(job.scheduled_end_at) : new Date(startMs + 60 * 60000);
    resizing.value = {
        id: job.id,
        rowId: row.id,
        rowEl,
        startMs,
        origEnd,
        startX: e.clientX,
        previewEnd: origEnd,
    };
};

const onWindowMouseMove = (e: MouseEvent) => {
    const r = resizing.value;
    if (!r) return;
    const rect = r.rowEl.getBoundingClientRect();
    const deltaX = e.clientX - r.startX;
    const minutesDelta = Math.round((deltaX / rect.width) * totalMinutes.value / slotMinutes.value) * slotMinutes.value;
    const newEnd = new Date(r.origEnd.getTime() + minutesDelta * 60000);
    const minEnd = r.startMs + slotMinutes.value * 60000;
    const previewEnd = newEnd.getTime() < minEnd ? new Date(minEnd) : newEnd;
    resizing.value = { ...r, previewEnd };
};

const onWindowMouseUp = () => {
    const r = resizing.value;
    if (!r) return;
    const preview = r.previewEnd;
    resizing.value = null;
    const job = props.jobcards.find((j) => j.id === r.id);
    if (!job?.scheduled_start_at) return;
    emit('move-jobcard', {
        jobcardId: r.id,
        assignedToUserId: job.assigned_to_user_id ?? null,
        start: formatIsoLocal(new Date(job.scheduled_start_at)),
        end: formatIsoLocal(preview),
    });
};

onMounted(() => {
    nowTimer = setInterval(() => {
        nowTick.value = Date.now();
    }, 30000);
    window.addEventListener('mousemove', onWindowMouseMove);
    window.addEventListener('mouseup', onWindowMouseUp);
    window.addEventListener('dragend', onWindowDragEnd, true);
    document.addEventListener('dragover', onDocumentDragOverCapture, true);
});
onBeforeUnmount(() => {
    if (nowTimer) clearInterval(nowTimer);
    window.removeEventListener('mousemove', onWindowMouseMove);
    window.removeEventListener('mouseup', onWindowMouseUp);
    window.removeEventListener('dragend', onWindowDragEnd, true);
    document.removeEventListener('dragover', onDocumentDragOverCapture, true);
    dispatchDragSession.value = null;
    dropPreview.value = null;
    resizing.value = null;
});

watch(
    () => props.boardDate,
    () => {
        nowTick.value = Date.now();
    },
);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-3 py-2">
            <h3 class="text-sm font-semibold text-slate-900">Day board</h3>
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input
                    type="checkbox"
                    class="rounded border-slate-300"
                    :checked="showAllHours"
                    @change="emit('toggle-all-hours', ($event.target as HTMLInputElement).checked)"
                />
                All hours
            </label>
        </div>
        <div class="min-h-0 flex-1 overflow-auto">
            <div class="min-w-max">
                <div class="sticky top-0 z-20 flex border-b border-slate-200 bg-slate-50">
                    <div class="sticky left-0 z-30 w-36 shrink-0 border-r border-slate-200 bg-slate-50 px-2 py-2 text-xs font-semibold text-slate-600">Technician</div>
                    <div class="flex shrink-0" :style="{ width: `${timelineWidthPx}px` }">
                        <div
                            v-for="(s, idx) in slotHeaders"
                            :key="s.key"
                            class="shrink-0 border-l border-slate-100 px-0.5 py-1 text-[10px] text-slate-500"
                            :style="{ width: `${slotWidthPx}px` }"
                        >
                            {{ s.label }}
                        </div>
                    </div>
                </div>
                <div v-for="row in rowUsers" :key="row.id" class="flex border-b border-slate-100" style="min-height: 88px">
                    <div class="sticky left-0 z-10 w-36 shrink-0 border-r border-slate-200 bg-white px-2 py-2 text-xs font-medium text-slate-800">
                        {{ row.name }}
                    </div>
                    <div
                        class="relative shrink-0 bg-slate-50/40"
                        data-timeline-row
                        :data-row-id="String(row.id)"
                        :style="{ width: `${timelineWidthPx}px` }"
                        @dragover="onDragOverRow($event, row)"
                        @drop="onDropRow($event, row)"
                    >
                        <div
                            v-for="idx in slotCount"
                            :key="`g-${row.id}-${idx}`"
                            class="pointer-events-none absolute top-0 bottom-0 border-l border-slate-100/80"
                            :style="{ left: `${((idx - 1) / slotCount) * 100}%`, width: `${100 / slotCount}%` }"
                        />
                        <div
                            v-if="nowLineStyle.display === 'block'"
                            class="pointer-events-none absolute top-0 bottom-0 z-10 w-px bg-rose-500"
                            :style="{ left: nowLineStyle.left }"
                        />
                        <div
                            v-if="dropPreview && dropPreview.rowId === row.id"
                            class="pointer-events-none absolute z-[8] rounded-md border-2 border-dashed border-indigo-500 bg-indigo-400/20"
                            :style="{ left: dropPreview.left, width: dropPreview.width, top: '4px', bottom: '8px' }"
                        >
                            <div
                                class="absolute left-1 top-1 max-w-[calc(100%-0.5rem)] truncate rounded bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-indigo-900 shadow-sm ring-1 ring-indigo-200/80"
                            >
                                {{ dropPreview.label }}
                            </div>
                        </div>
                        <div
                            v-if="resizePreviewDisplay && resizePreviewDisplay.rowId === row.id"
                            class="pointer-events-none absolute z-[9] rounded-md border-2 border-amber-500 bg-amber-300/25 ring-1 ring-amber-400/40"
                            :style="{
                                left: resizePreviewDisplay.left,
                                width: resizePreviewDisplay.width,
                                top: '4px',
                                bottom: '8px',
                            }"
                        >
                            <div
                                class="absolute left-1 top-1 max-w-[calc(100%-0.5rem)] truncate rounded bg-white/95 px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-amber-900 shadow-sm ring-1 ring-amber-200/80"
                            >
                                {{ resizePreviewDisplay.label }}
                            </div>
                        </div>
                        <div
                            v-for="job in jobsForRow(row)"
                            :key="job.id"
                            draggable="true"
                            class="absolute z-[5] cursor-grab overflow-hidden active:cursor-grabbing transition-opacity"
                            :class="resizing?.id === job.id ? 'opacity-35' : ''"
                            :style="jobStyle(job)"
                            @dragstart="onDragStartJob($event, job)"
                            @click.stop="emit('select', job)"
                        >
                            <DispatchJobcardBlock :job="job" compact :conflict-ids="conflictIds" />
                            <div
                                class="absolute top-0 right-0 bottom-0 z-[6] w-1.5 cursor-ew-resize rounded-r bg-slate-300/80 hover:bg-indigo-400"
                                title="Resize duration (drag sideways)"
                                @mousedown.stop="onResizeMouseDown($event, job, row)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
