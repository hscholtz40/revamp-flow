<template>
    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Time Tracking</h2>
            <p class="text-sm text-gray-600">Track time spent on this jobcard</p>
        </div>
        
        <div class="p-6 space-y-4">
            <!-- Timer Section -->
            <div v-if="runningTimer" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-900">Timer Running</p>
                        <p class="text-2xl font-bold text-blue-600">{{ formatElapsedTime(runningTimer) }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="pauseTimer"
                            class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 text-sm font-medium"
                        >
                            Pause
                        </button>
                        <button
                            @click="stopTimer"
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium"
                        >
                            Stop
                        </button>
                    </div>
                </div>
            </div>
            
            <div v-else class="flex gap-2">
                <button
                    @click="showStartTimerDialog = true"
                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm font-medium"
                >
                    Start Timer
                </button>
                <button
                    @click="showManualEntryDialog = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm font-medium"
                >
                    Add Manual Entry
                </button>
            </div>
            
            <!-- Time Summary -->
            <div v-if="timeSummary" class="grid gap-4 pt-4 border-t border-gray-200" :class="isLimitedUser ? 'grid-cols-1' : 'grid-cols-3'">
                <div>
                    <p class="text-sm text-gray-600">Total Hours</p>
                    <p class="text-lg font-semibold">{{ timeSummary.total_hours.toFixed(2) }}h</p>
                </div>
                <div v-if="!isLimitedUser">
                    <p class="text-sm text-gray-600">Billable Hours</p>
                    <p class="text-lg font-semibold">{{ timeSummary.billable_hours.toFixed(2) }}h</p>
                </div>
                <div v-if="!isLimitedUser">
                    <p class="text-sm text-gray-600">Total Amount</p>
                    <p class="text-lg font-semibold">R{{ timeSummary.total_amount.toFixed(2) }}</p>
                </div>
            </div>
            
            <!-- Time Entries List -->
            <div v-if="timeEntries && timeEntries.length > 0" class="mt-4">
                <h3 class="text-sm font-medium text-gray-900 mb-2">Recent Time Entries</h3>
                <div class="space-y-2">
                    <div
                        v-for="entry in timeEntries.slice(0, 5)"
                        :key="entry.id"
                        class="flex items-center justify-between p-3 bg-gray-50 rounded-md"
                    >
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ entry.user?.name }}</p>
                            <p class="text-xs text-gray-600">
                                {{ formatDate(entry.date) }}
                                <span v-if="entry.formatted_time_range && entry.formatted_time_range !== '—'">
                                    • {{ entry.formatted_time_range }}
                                </span>
                                • {{ entry.formatted_duration }}
                            </p>
                            <p v-if="entry.has_location" class="text-xs text-gray-500 mt-1">
                                <ResolvedLocationDisplay
                                    prefix="Location: "
                                    :latitude="entry.latitude"
                                    :longitude="entry.longitude"
                                    :accuracy="entry.location_accuracy"
                                    text-class="text-xs text-gray-500"
                                />
                            </p>
                            <p v-if="entry.description" class="text-xs text-gray-500 mt-1">{{ entry.description }}</p>
                        </div>
                        <div v-if="!isLimitedUser" class="text-right">
                            <p v-if="entry.is_billable" class="text-sm font-medium text-green-600">{{ entry.formatted_total_amount }}</p>
                            <p v-else class="text-sm text-gray-500">Non-billable</p>
                        </div>
                    </div>
                </div>
                <Link
                    :href="`/time-entries?jobcard_id=${jobcardId}`"
                    class="mt-2 text-sm text-blue-600 hover:text-blue-800"
                >
                    View all time entries →
                </Link>
            </div>
        </div>
        
        <!-- Start Timer Dialog -->
        <div v-if="showStartTimerDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Start Timer</h3>
                <form @submit.prevent="startTimer">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                v-model="timerForm.description"
                                class="w-full rounded-md border-gray-300 shadow-sm"
                                rows="3"
                                placeholder="What are you working on?"
                            ></textarea>
                        </div>
                        <div v-if="!isLimitedUser">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate (R)</label>
                            <input
                                v-model.number="timerForm.hourly_rate"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border-gray-300 shadow-sm"
                                placeholder="0.00"
                            />
                        </div>
                        <div class="flex items-center">
                            <input
                                v-model="timerForm.is_billable"
                                type="checkbox"
                                id="is_billable"
                                class="rounded border-gray-300"
                            />
                            <label for="is_billable" class="ml-2 text-sm text-gray-700">Billable</label>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button
                            type="button"
                            @click="showStartTimerDialog = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            Start Timer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Manual Entry Dialog -->
        <div v-if="showManualEntryDialog" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Time Entry</h3>
                <form @submit.prevent="addManualEntry">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input
                                v-model="entryForm.date"
                                type="date"
                                required
                                class="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                                <input
                                    v-model="entryForm.start_time"
                                    type="time"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                                <input
                                    v-model="entryForm.end_time"
                                    type="time"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration (hours)</label>
                            <input
                                v-model.number="entryForm.duration_hours"
                                type="number"
                                step="0.25"
                                min="0"
                                max="24"
                                class="w-full rounded-md border-gray-300 shadow-sm"
                                :class="{ 'bg-gray-100': entryForm.start_time && entryForm.end_time }"
                                :readonly="!!(entryForm.start_time && entryForm.end_time)"
                                placeholder="2.5"
                            />
                            <p v-if="entryForm.start_time && entryForm.end_time" class="text-xs text-gray-500 mt-1">
                                Auto-calculated from start and end times
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea
                                v-model="entryForm.description"
                                class="w-full rounded-md border-gray-300 shadow-sm"
                                rows="3"
                            ></textarea>
                        </div>
                        <div v-if="!isLimitedUser">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hourly Rate (R)</label>
                            <input
                                v-model.number="entryForm.hourly_rate"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div class="flex items-center">
                            <input
                                v-model="entryForm.is_billable"
                                type="checkbox"
                                id="entry_is_billable"
                                class="rounded border-gray-300"
                            />
                            <label for="entry_is_billable" class="ml-2 text-sm text-gray-700">Billable</label>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 mt-6">
                        <button
                            type="button"
                            @click="showManualEntryDialog = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Add Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import ResolvedLocationDisplay from '@/components/ResolvedLocationDisplay.vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';

interface Props {
    jobcardId: number;
    timeEntries?: any[];
    runningTimer?: any;
    timeSummary?: {
        total_hours: number;
        billable_hours: number;
        total_amount: number;
    };
}

const props = defineProps<Props>();

const page = usePage();
const isLimitedUser = computed(() => (page.props.auth as any)?.user?.user_type === 'limited');
const userHourlyRate = computed(() => (page.props.auth as any)?.user?.hourly_rate ?? null);

const showStartTimerDialog = ref(false);
const showManualEntryDialog = ref(false);
const elapsedTime = ref(0);
let intervalId: number | null = null;

const timerForm = useForm({
    jobcard_id: props.jobcardId,
    description: '',
    hourly_rate: userHourlyRate.value as number | null,
    is_billable: true,
});

const entryForm = useForm({
    jobcard_id: props.jobcardId,
    date: new Date().toISOString().split('T')[0],
    start_time: '',
    end_time: '',
    duration_hours: null as number | null,
    description: '',
    hourly_rate: userHourlyRate.value as number | null,
    is_billable: true,
});

// Auto-calculate duration when start and end times change
watch(
    () => [entryForm.start_time, entryForm.end_time],
    ([startTime, endTime]) => {
        if (startTime && endTime) {
            const [startHours, startMinutes] = startTime.split(':').map(Number);
            const [endHours, endMinutes] = endTime.split(':').map(Number);

            let durationMinutes = (endHours * 60 + endMinutes) - (startHours * 60 + startMinutes);

            // Handle overnight (e.g., 22:00 to 06:00)
            if (durationMinutes < 0) {
                durationMinutes += 24 * 60;
            }

            entryForm.duration_hours = Math.round((durationMinutes / 60) * 100) / 100;
        }
    }
);

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const formatElapsedTime = (timer: any) => {
    if (!timer?.started_at) return '0:00:00';
    
    const start = new Date(timer.started_at).getTime();
    const now = Date.now();
    const diff = Math.floor((now - start) / 1000);
    
    const hours = Math.floor(diff / 3600);
    const minutes = Math.floor((diff % 3600) / 60);
    const seconds = diff % 60;
    
    return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
};

const startTimer = () => {
    timerForm.post('/time-entries/start-timer', {
        preserveScroll: true,
        onSuccess: () => {
            showStartTimerDialog.value = false;
            timerForm.reset();
        },
    });
};

const stopTimer = () => {
    router.post('/time-entries/stop-timer', {
        jobcard_id: props.jobcardId,
    }, {
        preserveScroll: true,
    });
};

const pauseTimer = () => {
    router.post('/time-entries/pause-timer', {
        jobcard_id: props.jobcardId,
    }, {
        preserveScroll: true,
    });
};

const addManualEntry = () => {
    entryForm.post('/time-entries', {
        preserveScroll: true,
        onSuccess: () => {
            showManualEntryDialog.value = false;
            entryForm.reset();
            entryForm.date = new Date().toISOString().split('T')[0];
        },
    });
};

onMounted(() => {
    if (props.runningTimer) {
        intervalId = window.setInterval(() => {
            elapsedTime.value++;
        }, 1000);
    }
});

onUnmounted(() => {
    if (intervalId) {
        clearInterval(intervalId);
    }
});
</script>

