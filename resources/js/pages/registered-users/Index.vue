<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    pendingClients: Array<any>;
    approvedClients: Array<any>;
    pendingUpdateRequests: Array<any>;
}>();

const approveUser = (id: number) => router.post(`/registered-users/${id}/approve`);
const rejectUser = (id: number) => router.post(`/registered-users/${id}/reject`);
const approveUpdate = (id: number) => router.post(`/registered-users/update-requests/${id}/approve`);
const rejectUpdate = (id: number) => router.post(`/registered-users/update-requests/${id}/reject`);
</script>

<template>
    <Head title="Registered Users" />
    <AppLayout :breadcrumbs="[{ title: 'Registered Users', href: '/registered-users' }]">
        <div class="space-y-6 p-4">
            <h1 class="text-2xl font-semibold">Registered Users</h1>

            <section class="rounded border p-4">
                <h2 class="mb-3 text-lg font-medium">Pending Client Registrations</h2>
                <div class="space-y-2">
                    <div v-for="user in props.pendingClients" :key="user.id" class="flex items-center justify-between rounded border p-3">
                        <div>
                            <div class="font-medium">{{ user.name }}</div>
                            <div class="text-sm text-gray-600">{{ user.email }}</div>
                            <div class="text-sm text-gray-600">Customer: {{ user.customer?.name || 'Unlinked' }}</div>
                        </div>
                        <div class="flex gap-2">
                            <button class="rounded bg-green-600 px-3 py-1.5 text-white" @click="approveUser(user.id)">Approve</button>
                            <button class="rounded bg-red-600 px-3 py-1.5 text-white" @click="rejectUser(user.id)">Reject</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded border p-4">
                <h2 class="mb-3 text-lg font-medium">Pending Customer Update Requests</h2>
                <div class="space-y-2">
                    <div v-for="request in props.pendingUpdateRequests" :key="request.id" class="rounded border p-3">
                        <div class="mb-2 text-sm">
                            <strong>{{ request.customer?.name }}</strong> requested by {{ request.user?.name }} ({{ request.user?.email }})
                        </div>
                        <pre class="mb-2 overflow-x-auto rounded bg-gray-50 p-2 text-xs">{{ request.requested_changes }}</pre>
                        <div class="flex gap-2">
                            <button class="rounded bg-green-600 px-3 py-1.5 text-white" @click="approveUpdate(request.id)">Approve</button>
                            <button class="rounded bg-red-600 px-3 py-1.5 text-white" @click="rejectUpdate(request.id)">Reject</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded border p-4">
                <h2 class="mb-3 text-lg font-medium">Approved Client Accounts</h2>
                <div class="space-y-2">
                    <div v-for="user in props.approvedClients" :key="user.id" class="rounded border p-3">
                        <div class="font-medium">{{ user.name }}</div>
                        <div class="text-sm text-gray-600">{{ user.email }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
