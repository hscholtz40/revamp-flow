<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Customer {
    id: number;
    name: string;
    email?: string | null;
}

interface RequestUser {
    id: number;
    name: string;
    email: string;
}

interface Reviewer {
    id: number;
    name: string;
}

const props = defineProps<{
    updateRequest: {
        id: number;
        status: string;
        requested_changes: Record<string, string> | null;
        rejection_reason?: string | null;
        created_at: string;
        reviewed_at?: string | null;
        customer?: Customer | null;
        user?: RequestUser | null;
        reviewer?: Reviewer | null;
    };
}>();

const { formatDateTime } = useDateTimeFormat();
const canApprove = useAuthAbility('customer-update-requests', 'approve');

const rejectForm = useForm({ reason: '' });

const changeRows = computed(() => {
    const raw = props.updateRequest.requested_changes;
    if (!raw || typeof raw !== 'object') return [];
    return Object.entries(raw).map(([key, value]) => ({
        key: key.replace(/_/g, ' '),
        value: value === null || value === undefined || value === '' ? '—' : String(value),
    }));
});

const isPending = computed(() => props.updateRequest.status === 'pending');

const statusLabel = computed(() => {
    const s = props.updateRequest.status;
    if (s === 'pending') return 'Pending';
    if (s === 'approved') return 'Approved';
    if (s === 'rejected') return 'Rejected';
    return s;
});

const statusClass = computed(() => {
    const s = props.updateRequest.status;
    if (s === 'pending') return 'bg-amber-100 text-amber-900';
    if (s === 'approved') return 'bg-green-100 text-green-800';
    if (s === 'rejected') return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
});

const approve = () => {
    router.post(`/registered-users/update-requests/${props.updateRequest.id}/approve`);
};

const reject = () => {
    rejectForm.post(`/registered-users/update-requests/${props.updateRequest.id}/reject`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Update request" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Registered Users', href: '/registered-users' },
            { title: 'Update requests', href: '/registered-users/update-requests' },
            { title: `#${updateRequest.id}`, href: `/registered-users/update-requests/${updateRequest.id}` },
        ]"
    >
        <div class="space-y-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Update request #{{ updateRequest.id }}</h1>
                    <p class="text-sm text-gray-600">
                        {{ updateRequest.customer?.name ?? 'Customer' }} · submitted {{ formatDateTime(updateRequest.created_at) }}
                    </p>
                    <span
                        class="mt-2 inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="statusClass"
                    >
                        {{ statusLabel }}
                    </span>
                </div>
                <Link
                    href="/registered-users/update-requests"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Back to list
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-2 text-sm font-medium uppercase tracking-wide text-gray-500">Customer</h2>
                    <template v-if="updateRequest.customer">
                        <p class="font-medium text-gray-900">{{ updateRequest.customer.name }}</p>
                        <p class="text-sm text-gray-600">{{ updateRequest.customer.email ?? '—' }}</p>
                    </template>
                    <p v-else class="text-sm text-gray-500">—</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-2 text-sm font-medium uppercase tracking-wide text-gray-500">Requested by</h2>
                    <template v-if="updateRequest.user">
                        <p class="font-medium text-gray-900">{{ updateRequest.user.name }}</p>
                        <p class="text-sm text-gray-600">{{ updateRequest.user.email }}</p>
                    </template>
                    <p v-else class="text-sm text-gray-500">—</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-gray-500">Requested changes</h2>
                <div v-if="changeRows.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase text-gray-500">
                            <tr>
                                <th class="px-3 py-2">Field</th>
                                <th class="px-3 py-2">New value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in changeRows" :key="row.key">
                                <td class="px-3 py-2 font-medium capitalize text-gray-700">{{ row.key }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ row.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-500">No change payload recorded.</p>
            </div>

            <p
                v-if="isPending && !canApprove"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                Your group can view this request but does not have permission to approve or reject changes.
            </p>

            <div v-if="!isPending && updateRequest.reviewed_at" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-medium uppercase tracking-wide text-gray-500">Review</h2>
                <p class="text-sm text-gray-700">
                    {{ updateRequest.status === 'approved' ? 'Approved' : 'Rejected' }}
                    {{ formatDateTime(updateRequest.reviewed_at) }}
                    <span v-if="updateRequest.reviewer"> by {{ updateRequest.reviewer.name }}</span>
                </p>
                <p v-if="updateRequest.rejection_reason" class="mt-2 text-sm text-red-700">
                    {{ updateRequest.rejection_reason }}
                </p>
            </div>

            <div
                v-if="isPending && canApprove"
                class="space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
            >
                <h2 class="text-sm font-medium text-gray-900">Decision</h2>
                <div>
                    <label for="reject-reason" class="mb-1 block text-xs font-medium text-gray-600">
                        Optional reason when rejecting
                    </label>
                    <textarea
                        id="reject-reason"
                        v-model="rejectForm.reason"
                        rows="2"
                        class="w-full rounded-md border border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="Reason (optional)"
                    />
                    <p v-if="rejectForm.errors.reason" class="mt-1 text-sm text-red-600">{{ rejectForm.errors.reason }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="inline-flex min-h-[2.5rem] items-center justify-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                        @click="approve"
                    >
                        Approve and apply
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-[2.5rem] items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="rejectForm.processing"
                        @click="reject"
                    >
                        {{ rejectForm.processing ? 'Rejecting…' : 'Reject' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
