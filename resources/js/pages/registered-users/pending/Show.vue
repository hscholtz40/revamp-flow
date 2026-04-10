<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

interface Customer {
    id: number;
    name: string;
    email?: string | null;
}

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        customer?: Customer | null;
    };
}>();

const { formatDateTime } = useDateTimeFormat();
const canApprove = useAuthAbility('registered-users', 'approve');

const rejectForm = useForm({ reason: '' });

const approve = () => {
    router.post(`/registered-users/${props.user.id}/approve`);
};

const reject = () => {
    rejectForm.post(`/registered-users/${props.user.id}/reject`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Awaiting approval — ${user.name}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Registered Users', href: '/registered-users' },
            { title: 'Awaiting approval', href: '/registered-users/pending' },
            { title: user.name, href: `/registered-users/pending/${user.id}` },
        ]"
    >
        <div class="space-y-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ user.name }}</h1>
                    <p class="text-sm text-gray-600">{{ user.email }}</p>
                    <span class="mt-2 inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">Pending approval</span>
                </div>
                <Link
                    href="/registered-users/pending"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Back to list
                </Link>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-gray-500">Registration</h2>
                <p class="text-sm text-gray-700">Submitted {{ formatDateTime(user.created_at) }}</p>
                <div class="mt-4">
                    <h3 class="text-sm font-medium text-gray-900">Linked customer</h3>
                    <template v-if="user.customer">
                        <p class="mt-1 text-sm text-gray-700">{{ user.customer.name }}</p>
                        <p class="text-sm text-gray-600">{{ user.customer.email ?? '—' }}</p>
                    </template>
                    <p v-else class="mt-1 text-sm text-gray-500">No customer linked</p>
                </div>
            </div>

            <p v-if="!canApprove" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                Your group can view this registration but does not have permission to approve or reject.
            </p>

            <div
                v-if="canApprove"
                class="flex flex-wrap gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4"
            >
                <button
                    type="button"
                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="approve"
                >
                    Approve
                </button>
                <form class="flex flex-1 flex-col gap-2 md:min-w-[280px]" @submit.prevent="reject">
                    <label class="text-xs font-medium text-gray-600">Optional note when rejecting</label>
                    <textarea
                        v-model="rejectForm.reason"
                        rows="2"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"
                        placeholder="Reason (optional)"
                    />
                    <p v-if="rejectForm.errors.reason" class="text-sm text-red-600">{{ rejectForm.errors.reason }}</p>
                    <button
                        type="submit"
                        class="w-fit rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        :disabled="rejectForm.processing"
                    >
                        {{ rejectForm.processing ? 'Rejecting…' : 'Reject' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
