<script setup lang="ts">
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import users from '@/routes/users';
import { ref, watch } from 'vue';

interface User { id: number; name: string; email: string; user_type: 'standard' | 'limited' | 'info' }

const canUsersCreate = useAuthAbility('users', 'create');
const canUsersEdit = useAuthAbility('users', 'edit');

const props = defineProps<{
    users: { data: User[] };
    filters: { search?: string };
    licenseComplianceWarning?: string | null;
}>();

const search = ref(props.filters?.search ?? '');
watch(search, (value) => {
    const params: Record<string, string> = {};
    if (value && value.trim()) {
        params.search = value.trim();
    }
    
    router.get(users.index().url, params, { preserveState: true, replace: true });
});
</script>

<template>
    <Head title="Users" />

    <AppLayout :breadcrumbs="[{ title: 'Users', href: users.index().url }]">
        <div v-if="props.licenseComplianceWarning" class="mx-4 mt-4 rounded border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
            {{ props.licenseComplianceWarning }}
        </div>

        <div class="flex items-center justify-between gap-3 p-4">
            <input v-model="search" type="search" placeholder="Search users..." class="w-full max-w-sm rounded border px-3 py-2" />
            <Link v-if="canUsersCreate" :href="users.create().url" class="rounded bg-blue-600 px-3 py-2 text-white">New User</Link>
        </div>

        <div class="p-4">
            <div class="overflow-x-auto">
                <table class="min-w-full border">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="p-2 text-left">Name</th>
                            <th class="p-2 text-left">Email</th>
                            <th class="p-2 text-left">Type</th>
                            <th class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="u in props.users.data"
                            :key="u.id"
                            class="cursor-pointer border-t hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            tabindex="0"
                            role="link"
                            @click="router.visit(users.show(u.id).url)"
                            @keydown.enter.prevent="router.visit(users.show(u.id).url)"
                            @keydown.space.prevent="router.visit(users.show(u.id).url)"
                        >
                            <td class="p-2">{{ u.name }}</td>
                            <td class="p-2">{{ u.email }}</td>
                            <td class="p-2">
                                <span
                                    :class="{
                                        'bg-green-100 text-green-800': u.user_type === 'standard' || !u.user_type,
                                        'bg-yellow-100 text-yellow-800': u.user_type === 'limited',
                                        'bg-gray-100 text-gray-600': u.user_type === 'info',
                                    }"
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold capitalize"
                                >
                                    {{ u.user_type || 'standard' }}
                                </span>
                            </td>
                            <td class="p-2 text-right" @click.stop>
                                <Link v-if="canUsersEdit" :href="users.edit(u.id).url" class="rounded bg-gray-200 px-2 py-1">Edit</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>


