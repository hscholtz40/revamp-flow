<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Customer {
    id: number;
    name: string;
    email?: string | null;
}

interface Approver {
    id: number;
    name: string;
    email: string;
}

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        email_verified_at?: string | null;
        created_at: string;
        approval_status: string;
        approved_at: string | null;
        customer?: Customer | null;
        approved_by?: Approver | null;
    };
}>();

const { formatDateTime } = useDateTimeFormat();
const canApprove = useAuthAbility('registered-users', 'approve');

const isApproved = computed(() => props.user.approval_status === 'approved');
const isDeactivated = computed(() => props.user.approval_status === 'deactivated');

const listHref = computed(() =>
    `/registered-users/registered${isDeactivated.value ? '?account_filter=deactivated' : ''}`,
);

const breadcrumbs = computed(() => [
    { title: 'Registered Users', href: '/registered-users' },
    { title: 'Registered users', href: listHref.value },
    { title: props.user.name, href: `/registered-users/registered/${props.user.id}` },
]);

const deactivate = () => {
    if (!confirm(`Deactivate ${props.user.name}? They will lose Client Zone access until reactivated.`)) {
        return;
    }
    router.post(`/registered-users/${props.user.id}/deactivate`);
};

const reactivate = () => {
    router.post(`/registered-users/${props.user.id}/reactivate`);
};
</script>

<template>
    <Head :title="`Registered user — ${user.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ user.name }}</h1>
                    <p class="text-sm text-gray-600">{{ user.email }}</p>
                    <span
                        v-if="isApproved"
                        class="mt-2 inline-flex rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                    >
                        Active
                    </span>
                    <span
                        v-else-if="isDeactivated"
                        class="mt-2 inline-flex rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-800"
                    >
                        Deactivated
                    </span>
                </div>
                <Link
                    :href="listHref"
                    class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Back to list
                </Link>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-gray-500">Account</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Registered</dt>
                            <dd class="text-right text-gray-900">{{ formatDateTime(user.created_at) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Approved</dt>
                            <dd class="text-right text-gray-900">{{ user.approved_at ? formatDateTime(user.approved_at) : '—' }}</dd>
                        </div>
                        <div v-if="user.approved_by" class="flex justify-between gap-4">
                            <dt class="text-gray-500">Approved by</dt>
                            <dd class="text-right text-gray-900">{{ user.approved_by.name }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <h2 class="mb-3 text-sm font-medium uppercase tracking-wide text-gray-500">Linked customer</h2>
                    <template v-if="user.customer">
                        <p class="font-medium text-gray-900">{{ user.customer.name }}</p>
                        <p class="text-sm text-gray-600">{{ user.customer.email ?? '—' }}</p>
                    </template>
                    <p v-else class="text-sm text-gray-500">No customer linked</p>
                </div>
            </div>

            <div
                v-if="canApprove && (isApproved || isDeactivated)"
                class="flex flex-wrap gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4"
            >
                <button
                    v-if="isApproved"
                    type="button"
                    class="rounded-md bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900"
                    @click="deactivate"
                >
                    Deactivate client user
                </button>
                <button
                    v-if="isDeactivated"
                    type="button"
                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    @click="reactivate"
                >
                    Reactivate client user
                </button>
            </div>
            <p
                v-else-if="!canApprove && (isApproved || isDeactivated)"
                class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                Your group cannot deactivate or reactivate client users (requires Approve / reject permission).
            </p>
        </div>
    </AppLayout>
</template>
