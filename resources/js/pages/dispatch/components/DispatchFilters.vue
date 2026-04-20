<script setup lang="ts">
import type { DispatchLookup, DispatchQueueTab } from '@/types/dispatch-board';

const boardDate = defineModel<string>('boardDate', { required: true });
const search = defineModel<string>('search', { required: true });
const assignedUserId = defineModel<string>('assignedUserId', { required: true });
const assignedTeamId = defineModel<string>('assignedTeamId', { required: true });
const priority = defineModel<string>('priority', { required: true });
const tab = defineModel<DispatchQueueTab>('tab', { required: true });

defineProps<{
    users: DispatchLookup[];
    teams: DispatchLookup[];
    loading?: boolean;
}>();

defineEmits<{
    refresh: [];
}>();

const tabs: { value: DispatchQueueTab; label: string }[] = [
    { value: 'unscheduled', label: 'Unscheduled' },
    { value: 'needs_follow_up', label: 'Follow-up' },
    { value: 'waiting_for_parts', label: 'Parts' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'all', label: 'All open' },
];
</script>

<template>
    <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-3">
        <div>
            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Board day</label>
            <input v-model="boardDate" type="date" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm" />
        </div>
        <div>
            <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Search</label>
            <input
                v-model="search"
                type="search"
                placeholder="Job #, customer, phone, address…"
                class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm"
            />
        </div>
        <div class="grid grid-cols-1 gap-2">
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Technician</label>
                <select v-model="assignedUserId" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Team</label>
                <select v-model="assignedTeamId" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">All</option>
                    <option v-for="t in teams" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">Priority</label>
                <select v-model="priority" class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm">
                    <option value="">Any</option>
                    <option value="low">Low</option>
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
        </div>
        <div>
            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Queue tab</p>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="item in tabs"
                    :key="item.value"
                    type="button"
                    class="rounded-full border px-2 py-0.5 text-xs font-medium transition"
                    :class="tab === item.value ? 'border-indigo-500 bg-indigo-600 text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'"
                    @click="tab = item.value"
                >
                    {{ item.label }}
                </button>
            </div>
        </div>
        <button
            type="button"
            class="w-full rounded-md border border-slate-200 px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
            :disabled="loading"
            @click="$emit('refresh')"
        >
            {{ loading ? 'Loading…' : 'Refresh' }}
        </button>
    </div>
</template>
