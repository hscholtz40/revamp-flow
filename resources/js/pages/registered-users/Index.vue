<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Building2, ClipboardList, UserPlus } from 'lucide-vue-next';

defineProps<{
    showRegisteredUsers: boolean;
    showUpdateRequests: boolean;
    counts: {
        registered: number;
        pending: number;
        deactivated: number;
        update_requests: number;
        pending_update_requests: number;
    };
}>();
</script>

<template>
    <Head title="Client zone — registered users" />
    <AppLayout :breadcrumbs="[{ title: 'Registered Users', href: '/registered-users' }]">
        <div class="space-y-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Client zone administration</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Review approved client logins, pending registrations, and customer information update requests (based on your group permissions).
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-if="showRegisteredUsers"
                    href="/registered-users/registered"
                    class="group flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-blue-300 hover:shadow"
                >
                    <div class="flex items-center gap-3">
                        <span class="rounded-lg bg-blue-50 p-2 text-blue-700">
                            <Building2 class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="font-medium text-gray-900 group-hover:text-blue-700">Registered users</h2>
                            <p class="text-sm text-gray-500">Active and deactivated client accounts</p>
                        </div>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums text-gray-900">{{ counts.registered }}</p>
                    <p v-if="counts.deactivated > 0" class="mt-1 text-sm text-gray-600">
                        {{ counts.deactivated }} deactivated
                    </p>
                </Link>

                <Link
                    v-if="showRegisteredUsers"
                    href="/registered-users/pending"
                    class="group flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-amber-300 hover:shadow"
                >
                    <div class="flex items-center gap-3">
                        <span class="rounded-lg bg-amber-50 p-2 text-amber-700">
                            <UserPlus class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="font-medium text-gray-900 group-hover:text-amber-800">Awaiting approval</h2>
                            <p class="text-sm text-gray-500">New registrations to review</p>
                        </div>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums text-gray-900">{{ counts.pending }}</p>
                </Link>

                <Link
                    v-if="showUpdateRequests"
                    href="/registered-users/update-requests"
                    class="group flex flex-col rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 hover:shadow"
                >
                    <div class="flex items-center gap-3">
                        <span class="rounded-lg bg-violet-50 p-2 text-violet-700">
                            <ClipboardList class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="font-medium text-gray-900 group-hover:text-violet-800">Update requests</h2>
                            <p class="text-sm text-gray-500">
                                {{ counts.pending_update_requests }} pending of {{ counts.update_requests }} total
                            </p>
                        </div>
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums text-gray-900">{{ counts.update_requests }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
