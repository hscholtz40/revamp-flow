<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { 
    Database, 
    Download, 
    Trash2, 
    RotateCcw, 
    Plus, 
    Clock, 
    Calendar,
    HardDrive,
    AlertCircle,
    CheckCircle2,
    Loader2
} from 'lucide-vue-next';

interface Backup {
    id: number;
    name: string;
    type: string;
    storage_type: string;
    file_name: string;
    file_size: number;
    status: string;
    error_message: string | null;
    created_at: string;
    completed_at: string | null;
    user: {
        id: number;
        name: string;
    } | null;
}

interface BackupSchedule {
    id: number;
    name: string;
    frequency: string;
    time: string;
    timezone: string;
    day_of_week: string | null;
    day_of_month: number | null;
    storage_type: string;
    is_active: boolean;
    retention_days: number;
    last_run_at: string | null;
    next_run_at: string | null;
}

const props = defineProps<{
    backups: {
        data: Backup[];
        links: any[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    schedules: BackupSchedule[];
    stats: {
        total_backups: number;
        total_size: number;
        last_backup: string | null;
    };
}>();

const showCreateBackupDialog = ref(false);
const showScheduleDialog = ref(false);
const isRestoring = ref<number | null>(null);
const isDeleting = ref<number | null>(null);

const backupForm = ref({
    name: '',
});

const scheduleForm = ref({
    name: '',
    frequency: 'daily',
    time: '02:00',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    day_of_week: '',
    day_of_month: null,
    retention_days: 30,
});

function createBackup() {
    router.post('/backups', backupForm.value, {
        onSuccess: () => {
            showCreateBackupDialog.value = false;
            backupForm.value = { name: '' };
        },
    });
}

function createSchedule() {
    router.post('/backups/schedules', scheduleForm.value, {
        onSuccess: () => {
            showScheduleDialog.value = false;
            scheduleForm.value = {
                name: '',
                frequency: 'daily',
                time: '02:00',
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                day_of_week: '',
                day_of_month: null,
                retention_days: 30,
            };
        },
    });
}

function restoreBackup(backupId: number) {
    if (!confirm('Are you sure you want to restore this backup? This will overwrite your current database and files.')) {
        return;
    }
    
    isRestoring.value = backupId;
    router.post(`/backups/${backupId}/restore`, {}, {
        onFinish: () => {
            isRestoring.value = null;
        },
    });
}

function deleteBackup(backupId: number) {
    if (!confirm('Are you sure you want to delete this backup?')) {
        return;
    }
    
    isDeleting.value = backupId;
    router.delete(`/backups/${backupId}`, {
        onFinish: () => {
            isDeleting.value = null;
        },
    });
}

function downloadBackup(backupId: number) {
    // Use window.location to bypass Inertia and trigger direct download
    window.location.href = `/backups/${backupId}/download`;
}

function deleteSchedule(scheduleId: number) {
    if (!confirm('Are you sure you want to delete this schedule?')) {
        return;
    }
    
    router.delete(`/backups/schedules/${scheduleId}`);
}

function getStatusBadgeVariant(status: string): string {
    const variants: Record<string, string> = {
        completed: 'default',
        in_progress: 'secondary',
        failed: 'destructive',
        pending: 'outline',
    };
    return variants[status] || 'outline';
}

function formatSize(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
        size /= 1024;
        unitIndex++;
    }
    
    return `${size.toFixed(2)} ${units[unitIndex]}`;
}

function formatStorageType(type: string): string {
    return 'Local Storage';
}

function formatFrequency(frequency: string, dayOfWeek?: string | null, dayOfMonth?: number | null): string {
    switch (frequency) {
        case 'daily':
            return 'Daily';
        case 'weekly':
            return `Weekly (${dayOfWeek || 'Monday'})`;
        case 'monthly':
            return `Monthly (Day ${dayOfMonth || 1})`;
        default:
            return frequency;
    }
}

// Get common timezones
const timezones = [
    { value: 'UTC', label: 'UTC (Coordinated Universal Time)' },
    { value: 'America/New_York', label: 'America/New_York (EST/EDT)' },
    { value: 'America/Chicago', label: 'America/Chicago (CST/CDT)' },
    { value: 'America/Denver', label: 'America/Denver (MST/MDT)' },
    { value: 'America/Los_Angeles', label: 'America/Los_Angeles (PST/PDT)' },
    { value: 'Europe/London', label: 'Europe/London (GMT/BST)' },
    { value: 'Europe/Paris', label: 'Europe/Paris (CET/CEST)' },
    { value: 'Europe/Berlin', label: 'Europe/Berlin (CET/CEST)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (JST)' },
    { value: 'Asia/Shanghai', label: 'Asia/Shanghai (CST)' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai (GST)' },
    { value: 'Asia/Kolkata', label: 'Asia/Kolkata (IST)' },
    { value: 'Australia/Sydney', label: 'Australia/Sydney (AEDT/AEST)' },
    { value: 'Africa/Johannesburg', label: 'Africa/Johannesburg (SAST)' },
    { value: 'Africa/Cairo', label: 'Africa/Cairo (EET/EEST)' },
    { value: 'America/Sao_Paulo', label: 'America/Sao_Paulo (BRT/BRST)' },
    { value: 'America/Mexico_City', label: 'America/Mexico_City (CST/CDT)' },
    { value: 'America/Toronto', label: 'America/Toronto (EST/EDT)' },
    { value: 'Europe/Moscow', label: 'Europe/Moscow (MSK)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (SGT)' },
];

// Add user's timezone if not in list
const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
if (!timezones.find(tz => tz.value === userTimezone)) {
    timezones.unshift({ value: userTimezone, label: `${userTimezone} (Your Timezone)` });
}

function formatDateTimeInTimezone(dateString: string, timezone: string): string {
    try {
        const date = new Date(dateString);
        return new Intl.DateTimeFormat('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: timezone,
        }).format(date);
    } catch (e) {
        return new Date(dateString).toLocaleString();
    }
}
</script>

<template>
    <Head title="Backups" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Backups & Restore</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Manage system-wide automated backups and restore your entire system
                    </p>
                </div>
                <div class="flex gap-2">
                    <Dialog v-model:open="showScheduleDialog">
                        <DialogTrigger as-child>
                            <Button variant="outline">
                                <Clock class="mr-2 h-4 w-4" />
                                Schedule Backup
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create Backup Schedule</DialogTitle>
                            </DialogHeader>
                            <form @submit.prevent="createSchedule" class="space-y-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Schedule Name</label>
                                    <Input v-model="scheduleForm.name" required />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Frequency</label>
                                    <select
                                        v-model="scheduleForm.frequency"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                <div v-if="scheduleForm.frequency === 'weekly'">
                                    <label class="mb-2 block text-sm font-medium">Day of Week</label>
                                    <select
                                        v-model="scheduleForm.day_of_week"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                        required
                                    >
                                        <option value="monday">Monday</option>
                                        <option value="tuesday">Tuesday</option>
                                        <option value="wednesday">Wednesday</option>
                                        <option value="thursday">Thursday</option>
                                        <option value="friday">Friday</option>
                                        <option value="saturday">Saturday</option>
                                        <option value="sunday">Sunday</option>
                                    </select>
                                </div>
                                <div v-if="scheduleForm.frequency === 'monthly'">
                                    <label class="mb-2 block text-sm font-medium">Day of Month</label>
                                    <Input
                                        v-model.number="scheduleForm.day_of_month"
                                        type="number"
                                        min="1"
                                        max="31"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Time</label>
                                    <Input v-model="scheduleForm.time" type="time" required />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Timezone</label>
                                    <select
                                        v-model="scheduleForm.timezone"
                                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm"
                                        required
                                    >
                                        <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                                            {{ tz.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Retention Days</label>
                                    <Input
                                        v-model.number="scheduleForm.retention_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        required
                                    />
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" @click="showScheduleDialog = false">
                                        Cancel
                                    </Button>
                                    <Button type="submit">Create Schedule</Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>

                    <Dialog v-model:open="showCreateBackupDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="mr-2 h-4 w-4" />
                                Create Backup
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Create Manual Backup</DialogTitle>
                            </DialogHeader>
                            <form @submit.prevent="createBackup" class="space-y-4">
                                <div>
                                    <label class="mb-2 block text-sm font-medium">Backup Name (Optional)</label>
                                    <Input v-model="backupForm.name" placeholder="Auto-generated if empty" />
                                </div>
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" @click="showCreateBackupDialog = false">
                                        Cancel
                                    </Button>
                                    <Button type="submit">Create Backup</Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Backups</p>
                            <p class="text-2xl font-bold">{{ props.stats.total_backups }}</p>
                        </div>
                        <Database class="h-8 w-8 text-blue-600" />
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Total Size</p>
                            <p class="text-2xl font-bold">{{ formatSize(props.stats.total_size) }}</p>
                        </div>
                        <HardDrive class="h-8 w-8 text-green-600" />
                    </div>
                </div>
                <div class="rounded-lg border bg-white p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Last Backup</p>
                            <p class="text-sm font-medium">
                                {{ props.stats.last_backup ? new Date(props.stats.last_backup).toLocaleString() : 'Never' }}
                            </p>
                        </div>
                        <Calendar class="h-8 w-8 text-purple-600" />
                    </div>
                </div>
            </div>

            <!-- Backup Schedules -->
            <div v-if="props.schedules.length > 0" class="rounded-lg border bg-white p-4">
                <h2 class="mb-4 text-lg font-semibold">Backup Schedules</h2>
                <div class="space-y-2">
                    <div
                        v-for="schedule in props.schedules"
                        :key="schedule.id"
                        class="flex items-center justify-between rounded border p-3"
                    >
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-medium">{{ schedule.name }}</h3>
                                <Badge :variant="schedule.is_active ? 'default' : 'outline'">
                                    {{ schedule.is_active ? 'Active' : 'Inactive' }}
                                </Badge>
                            </div>
                            <p class="text-sm text-gray-600">
                                {{ formatFrequency(schedule.frequency, schedule.day_of_week, schedule.day_of_month) }}
                                at {{ schedule.time }} ({{ schedule.timezone }})
                            </p>
                            <p class="text-xs text-gray-500">
                                Retention: {{ schedule.retention_days }} days |
                                <span v-if="schedule.next_run_at">
                                    Next run: {{ formatDateTimeInTimezone(schedule.next_run_at, schedule.timezone) }}
                                </span>
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="inline-flex items-center justify-center"
                            @click="deleteSchedule(schedule.id)"
                        >
                            <ListTableActionLabel label="Delete">
                                <Trash2 class="h-4 w-4" />
                            </ListTableActionLabel>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Backups Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Storage
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Size
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Created
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-if="backups.data.length === 0">
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                No backups found. Create your first backup to get started.
                            </td>
                        </tr>
                        <tr
                            v-for="backup in backups.data"
                            :key="backup.id"
                            class="hover:bg-gray-50"
                        >
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ backup.name }}</div>
                                <div class="text-sm text-gray-500">{{ backup.file_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <Badge variant="outline">{{ backup.type }}</Badge>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Badge :variant="getStatusBadgeVariant(backup.status)">
                                        {{ backup.status }}
                                    </Badge>
                                    <AlertCircle
                                        v-if="backup.status === 'failed'"
                                        class="h-4 w-4 text-red-500"
                                        :title="backup.error_message || 'Backup failed'"
                                    />
                                    <Loader2
                                        v-if="backup.status === 'in_progress'"
                                        class="h-4 w-4 animate-spin text-blue-500"
                                    />
                                    <CheckCircle2
                                        v-if="backup.status === 'completed'"
                                        class="h-4 w-4 text-green-500"
                                    />
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-1 text-sm">
                                    <HardDrive class="h-4 w-4" />
                                    Local Storage
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ formatSize(backup.file_size) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ new Date(backup.created_at).toLocaleString() }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="backup.status === 'completed'"
                                        variant="ghost"
                                        size="sm"
                                        class="inline-flex items-center justify-center"
                                        @click="restoreBackup(backup.id)"
                                        :disabled="isRestoring === backup.id"
                                    >
                                        <template v-if="isRestoring !== backup.id">
                                            <ListTableActionLabel label="Restore">
                                                <RotateCcw class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </template>
                                        <Loader2
                                            v-else
                                            class="h-4 w-4 animate-spin"
                                        />
                                    </Button>
                                    <button
                                        v-if="backup.status === 'completed'"
                                        type="button"
                                        @click="downloadBackup(backup.id)"
                                        class="inline-flex h-8 items-center justify-center whitespace-nowrap rounded-md px-2 text-sm font-medium transition-all hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                                    >
                                        <ListTableActionLabel label="Download">
                                            <Download class="h-4 w-4" />
                                        </ListTableActionLabel>
                                    </button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="inline-flex items-center justify-center"
                                        @click="deleteBackup(backup.id)"
                                        :disabled="isDeleting === backup.id"
                                    >
                                        <template v-if="isDeleting !== backup.id">
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4 text-red-500" />
                                            </ListTableActionLabel>
                                        </template>
                                        <Loader2
                                            v-else
                                            class="h-4 w-4 animate-spin"
                                        />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="backups.last_page > 1" class="flex items-center justify-between">
                <div class="text-sm text-gray-600">
                    Showing {{ (backups.current_page - 1) * backups.per_page + 1 }} to
                    {{ Math.min(backups.current_page * backups.per_page, backups.total) }}
                    of {{ backups.total }} results
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="backups.current_page > 1"
                        variant="outline"
                        size="sm"
                        @click="router.get(backups.links[0].url)"
                    >
                        Previous
                    </Button>
                    <Button
                        v-if="backups.current_page < backups.last_page"
                        variant="outline"
                        size="sm"
                        @click="router.get(backups.links[backups.links.length - 1].url)"
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

