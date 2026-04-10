<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import groups from '@/routes/groups';
import { allModulesForGroupPermissions, clientZoneAdminModules, modules } from '@/lib/modules';

const props = defineProps<{
    group: {
        id: number;
        name: string;
        description?: string;
        is_administrator?: boolean;
        payment_method_card?: boolean;
        payment_method_cash?: boolean;
        payment_method_eft?: boolean;
        permissions: any[];
    };
}>();

const form = useForm({
    name: props.group.name,
    description: props.group.description || '',
    is_administrator: props.group.is_administrator || false,
    payment_method_card: props.group.payment_method_card !== false,
    payment_method_cash: props.group.payment_method_cash !== false,
    payment_method_eft: props.group.payment_method_eft !== false,
    permissions: allModulesForGroupPermissions.map((m) => {
        const existing = props.group.permissions.find((p: any) => p.module === m.key) || {};
        return {
            module: m.key,
            can_view: !!existing.can_view,
            can_list: !!existing.can_list,
            can_create: !!existing.can_create,
            can_edit: !!existing.can_edit,
            can_delete: !!existing.can_delete,
            can_edit_completed: !!existing.can_edit_completed,
            can_edit_salesperson: !!existing.can_edit_salesperson,
            can_approve: !!existing.can_approve,
        };
    }),
});

function saveDetails() { form.put(groups.update(props.group.id).url, { preserveScroll: true }); }
function savePermissions() {
    form
        .transform((d) => ({
            permissions: d.permissions,
            payment_method_card: d.payment_method_card,
            payment_method_cash: d.payment_method_cash,
            payment_method_eft: d.payment_method_eft,
        }))
        .put(groups.permissions(props.group.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Edit ${props.group.name}`" />
    <AppLayout :breadcrumbs="[{ title: 'Groups', href: groups.index().url }, { title: 'Edit', href: '#' }]">
        <div class="space-y-6 p-4">
            <form @submit.prevent="saveDetails" class="space-y-4">
                <label class="block">
                    <span class="mb-1 block">Name</span>
                    <input v-model="form.name" class="w-full rounded border px-3 py-2" required />
                </label>
                
                <label class="block">
                    <span class="mb-1 block">Description</span>
                    <textarea v-model="form.description" class="w-full rounded border px-3 py-2" rows="3"></textarea>
                </label>
                
                <label class="flex items-center space-x-2">
                    <input 
                        type="checkbox" 
                        v-model="form.is_administrator" 
                        class="rounded border-gray-300"
                    />
                    <span class="text-sm font-medium">Administrator Group</span>
                </label>
                <p class="text-xs text-gray-600">
                    Administrator groups have access to all administration functions and system settings.
                </p>
                
                <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save Details</button>
            </form>

            <div class="rounded border">
                <div class="border-b p-3 font-medium">Permissions</div>
                <form @submit.prevent="savePermissions" class="space-y-3 p-3">
                    <table class="min-w-full">
                        <thead>
                            <tr class="text-left">
                                <th class="p-2">Module</th>
                                <th class="p-2">List</th>
                                <th class="p-2">View</th>
                                <th class="p-2">Create</th>
                                <th class="p-2">Edit</th>
                                <th class="p-2">Delete</th>
                                <th class="p-2">Edit Completed</th>
                                <th class="p-2">Edit Salesperson</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(perm, i) in form.permissions"
                                v-show="!clientZoneAdminModules.some((c) => c.key === perm.module)"
                                :key="perm.module"
                                class="border-t"
                            >
                                <td class="p-2">{{ modules.find(m => m.key === perm.module)?.label }}</td>
                                <td class="p-2"><input type="checkbox" v-model="form.permissions[i].can_list" /></td>
                                <td class="p-2"><input type="checkbox" v-model="form.permissions[i].can_view" /></td>
                                <td class="p-2"><input type="checkbox" v-model="form.permissions[i].can_create" /></td>
                                <td class="p-2"><input type="checkbox" v-model="form.permissions[i].can_edit" /></td>
                                <td class="p-2"><input type="checkbox" v-model="form.permissions[i].can_delete" /></td>
                        <td class="p-2">
                            <input
                                type="checkbox"
                                v-model="form.permissions[i].can_edit_completed"
                                :disabled="!['jobcards', 'invoices', 'quotes'].includes(perm.module)"
                                :class="{ 'opacity-50 cursor-not-allowed': !['jobcards', 'invoices', 'quotes'].includes(perm.module) }"
                            />
                        </td>
                                <td class="p-2">
                                    <input 
                                        type="checkbox" 
                                        v-model="form.permissions[i].can_edit_salesperson"
                                        :disabled="perm.module !== 'invoices'"
                                        :class="{ 'opacity-50 cursor-not-allowed': perm.module !== 'invoices' }"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mt-6 border-t pt-4">
                        <div class="mb-2 font-medium text-gray-900">Client Zone user management</div>
                        <p class="mb-3 text-xs text-gray-600">
                            Control staff access to client self-registration approvals and customer information update requests submitted from Client Zone.
                        </p>
                        <table class="min-w-full">
                            <thead>
                                <tr class="text-left">
                                    <th class="p-2">Module</th>
                                    <th class="p-2">List</th>
                                    <th class="p-2">View</th>
                                    <th class="p-2">Approve / reject</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(perm, i) in form.permissions"
                                    v-show="clientZoneAdminModules.some((c) => c.key === perm.module)"
                                    :key="perm.module"
                                    class="border-t"
                                >
                                    <td class="p-2">{{ clientZoneAdminModules.find((c) => c.key === perm.module)?.label }}</td>
                                    <td class="p-2">
                                        <input type="checkbox" v-model="form.permissions[i].can_list" />
                                    </td>
                                    <td class="p-2">
                                        <input type="checkbox" v-model="form.permissions[i].can_view" />
                                    </td>
                                    <td class="p-2">
                                        <input type="checkbox" v-model="form.permissions[i].can_approve" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 rounded border border-gray-200 bg-gray-50 p-3">
                        <div class="mb-2 text-sm font-medium text-gray-800">Payment methods (invoice payments, POS, refunds)</div>
                        <p class="mb-3 text-xs text-gray-600">
                            Users in this group may only record <strong>Cash</strong>, <strong>Card</strong>, or <strong>EFT</strong> for the options you enable below. At least one must stay on.
                        </p>
                        <div class="flex flex-wrap gap-4">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="form.payment_method_card" class="rounded border-gray-300" />
                                Card
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="form.payment_method_cash" class="rounded border-gray-300" />
                                Cash
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="form.payment_method_eft" class="rounded border-gray-300" />
                                EFT
                            </label>
                        </div>
                        <p v-if="form.errors.payment_methods" class="mt-2 text-sm text-red-600">{{ form.errors.payment_methods }}</p>
                    </div>
                    <div>
                        <button :disabled="form.processing" class="rounded bg-blue-600 px-4 py-2 text-white">Save Permissions</button>
                        <Link :href="groups.index().url" class="ml-3 rounded border px-4 py-2">Back</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>


