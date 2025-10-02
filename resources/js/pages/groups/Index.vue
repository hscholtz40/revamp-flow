<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import groups from '@/routes/groups';

const props = defineProps<{ groups: any[] }>();
</script>

<template>
    <Head title="Groups" />
    <AppLayout :breadcrumbs="[{ title: 'Groups', href: groups.index().url }]">
        <div class="flex items-center justify-between gap-3 p-4">
            <div />
            <Link :href="groups.create().url" class="rounded bg-blue-600 px-3 py-2 text-white">New Group</Link>
        </div>
        <div class="p-4">
            <table class="min-w-full border">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="p-2 text-left">Name</th>
                        <th class="p-2 text-left">Description</th>
                        <th class="p-2 text-left">Type</th>
                        <th class="p-2 text-left">Users</th>
                        <th class="p-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="g in props.groups" :key="g.id" class="border-t">
                        <td class="p-2 font-medium">{{ g.name }}</td>
                        <td class="p-2 text-sm text-gray-600">{{ g.description || '-' }}</td>
                        <td class="p-2">
                            <span v-if="g.is_administrator" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Administrator
                            </span>
                            <span v-else class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                Standard
                            </span>
                        </td>
                        <td class="p-2">{{ g.users_count }}</td>
                        <td class="p-2 text-right">
                            <Link :href="groups.edit(g.id).url" class="rounded border px-2 py-1">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>


