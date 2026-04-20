<script setup lang="ts">
import type { DispatchJobcard } from '@/types/dispatch-board';
import { formatDispatchScheduleInstant } from '@/lib/dispatchScheduleFormat';
import { computed } from 'vue';

const props = defineProps<{
    job: DispatchJobcard | null;
    users: { id: number; name: string }[];
    teams: { id: number; name: string }[];
    googleMapsApiKey?: string;
}>();

const emit = defineEmits<{
    'open-full': [];
    'view-map': [];
    'set-status': [status: string];
    assign: [payload: { assigned_to_user_id: number | null; assigned_to_team_id: number | null }];
    unschedule: [];
    'set-datetime': [payload: { scheduled_start_at: string; scheduled_end_at: string | null }];
    'set-estimated-duration': [minutes: number];
}>();

const estimatedMinutesDisplay = computed(() => {
    const j = props.job;
    if (!j) return 60;
    if (j.scheduled_start_at && j.scheduled_end_at) {
        const a = new Date(j.scheduled_start_at).getTime();
        const b = new Date(j.scheduled_end_at).getTime();
        if (!Number.isNaN(a) && !Number.isNaN(b)) {
            return Math.max(5, Math.round((b - a) / 60000));
        }
    }
    return j.estimated_duration_minutes ?? 60;
});

const address = computed(() => {
    if (!props.job) return '';
    if (props.job.service_address) return props.job.service_address;
    const c = props.job.customer;
    if (!c) return '';
    return [c.address, c.city, c.country].map((x) => (x ?? '').trim()).filter(Boolean).join(', ');
});

const phone = computed(() => props.job?.phone || props.job?.contact?.phone || '');
const email = computed(() => props.job?.email || props.job?.contact?.email || '');

const assignUser = computed({
    get: () => (props.job?.assigned_to_user_id != null ? String(props.job.assigned_to_user_id) : ''),
    set: (v: string) => {
        if (!props.job) return;
        emit('assign', {
            assigned_to_user_id: v ? Number(v) : null,
            assigned_to_team_id: props.job.assigned_to_team_id ?? null,
        });
    },
});

const assignTeam = computed({
    get: () => (props.job?.assigned_to_team_id != null ? String(props.job.assigned_to_team_id) : ''),
    set: (v: string) => {
        if (!props.job) return;
        emit('assign', {
            assigned_to_user_id: props.job.assigned_to_user_id ?? null,
            assigned_to_team_id: v ? Number(v) : null,
        });
    },
});

const statusButtons = [
    { status: 'dispatched', label: 'Dispatched' },
    { status: 'accepted', label: 'Accepted' },
    { status: 'en_route', label: 'En route' },
    { status: 'on_site', label: 'On site' },
    { status: 'completed', label: 'Completed' },
    { status: 'waiting_for_parts', label: 'Waiting parts' },
    { status: 'needs_follow_up', label: 'Follow-up' },
    { status: 'emergency', label: 'Emergency' },
    { status: 'needs_scheduling', label: 'Needs scheduling' },
];
</script>

<template>
    <div v-if="job" class="flex min-h-0 flex-col gap-3 overflow-y-auto rounded-xl border border-slate-200 bg-white p-3">
        <div class="flex items-start justify-between gap-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jobcard</p>
                <p class="text-lg font-bold text-slate-900">{{ job.job_number || `Jobcard #${job.id}` }}</p>
                <p v-if="job.title" class="text-sm text-slate-700">{{ job.title }}</p>
            </div>
            <button type="button" class="shrink-0 text-sm font-medium text-indigo-600 hover:underline" @click="emit('open-full')">Open full</button>
        </div>
        <div class="space-y-1 text-sm text-slate-700">
            <p>
                <span class="font-medium text-slate-500">Customer:</span>
                {{ job.customer?.name || '—' }}
            </p>
            <p>
                <span class="font-medium text-slate-500">Site:</span>
                {{ address || '—' }}
            </p>
            <p v-if="phone"><span class="font-medium text-slate-500">Phone:</span> {{ phone }}</p>
            <p v-if="email"><span class="font-medium text-slate-500">Email:</span> {{ email }}</p>
            <p><span class="font-medium text-slate-500">Status:</span> {{ (job.status ?? '').replace(/_/g, ' ') }}</p>
            <p><span class="font-medium text-slate-500">Priority:</span> {{ job.priority ?? 'normal' }}</p>
            <p v-if="job.scheduled_start_at">
                <span class="font-medium text-slate-500">Scheduled:</span>
                {{ formatDispatchScheduleInstant(job.scheduled_start_at) }} – {{ job.scheduled_end_at ? formatDispatchScheduleInstant(job.scheduled_end_at) : '—' }}
            </p>
            <p v-else><span class="font-medium text-slate-500">Scheduled:</span> Unscheduled</p>
            <p>
                <span class="font-medium text-slate-500">Assigned:</span>
                {{ job.assigned_user?.name || job.assigned_team?.name || 'Unassigned' }}
            </p>
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <label class="text-sm text-slate-700">
                    <span class="font-medium text-slate-500">Est. duration</span>
                    <input
                        type="number"
                        min="5"
                        max="1440"
                        step="5"
                        class="ml-2 w-24 rounded-md border border-slate-200 px-2 py-1 text-sm tabular-nums"
                        :value="estimatedMinutesDisplay"
                        @change="
                            emit(
                                'set-estimated-duration',
                                Math.max(5, Math.min(1440, Math.round(Number(($event.target as HTMLInputElement).value) || 60))),
                            )
                        "
                    />
                    <span class="ml-1 text-slate-500">min</span>
                </label>
            </div>
        </div>
        <div v-if="job.description" class="rounded-md bg-slate-50 p-2 text-xs text-slate-700">
            <span class="font-semibold text-slate-500">Description</span>
            <p class="mt-1 whitespace-pre-wrap">{{ job.description }}</p>
        </div>
        <div v-if="job.notes" class="rounded-md bg-amber-50/60 p-2 text-xs text-slate-800">
            <span class="font-semibold text-amber-800">Internal notes</span>
            <p class="mt-1 whitespace-pre-wrap">{{ job.notes }}</p>
        </div>

        <div class="border-t border-slate-100 pt-2">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Assign</p>
            <div class="grid gap-2">
                <select v-model="assignUser" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">Technician…</option>
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
                <select v-model="assignTeam" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">Team…</option>
                    <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-2">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Quick status</p>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="b in statusButtons"
                    :key="b.status"
                    type="button"
                    class="rounded-md border border-slate-200 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:border-indigo-300 hover:bg-indigo-50"
                    @click="emit('set-status', b.status)"
                >
                    {{ b.label }}
                </button>
            </div>
            <button
                type="button"
                class="mt-2 w-full rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5 text-xs font-medium text-amber-900 hover:bg-amber-100"
                @click="emit('unschedule')"
            >
                Remove from schedule
            </button>
        </div>

        <div class="border-t border-slate-100 pt-2">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Map</p>
            <p v-if="!googleMapsApiKey" class="rounded border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs text-slate-600">
                Map is disabled (no Google Maps API key). Configure it under Administration → Google Integration.
            </p>
            <button
                v-else
                type="button"
                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-800 shadow-sm hover:border-indigo-300 hover:bg-indigo-50"
                @click="emit('view-map')"
            >
                View map
            </button>
        </div>
    </div>
    <div v-else class="flex flex-1 items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">
        Select a jobcard to view details and actions.
    </div>
</template>
