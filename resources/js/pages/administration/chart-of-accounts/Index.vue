<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, BookOpen } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface ChartOfAccount {
    id: number;
    account_code: string;
    account_name: string;
    account_type: string;
    parent_account_id: number | null;
    parent_account?: ChartOfAccount | null;
    description: string | null;
    is_active: boolean;
    is_default_sales: boolean;
    is_default_purchasing: boolean;
    is_default_rounding: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    accounts: ChartOfAccount[];
}

const props = defineProps<Props>();

function deleteAccount(account: ChartOfAccount) {
    if (confirm(`Are you sure you want to delete "${account.account_name}"?`)) {
        router.delete(administration.chartOfAccounts.destroy(account.id).url);
    }
}

function getAccountTypeColor(type: string): string {
    const colors: Record<string, string> = {
        'Asset': 'bg-green-100 text-green-800',
        'Liability': 'bg-red-100 text-red-800',
        'Equity': 'bg-blue-100 text-blue-800',
        'Revenue': 'bg-purple-100 text-purple-800',
        'Expense': 'bg-orange-100 text-orange-800',
    };
    return colors[type] || 'bg-gray-100 text-gray-800';
}
</script>

<template>
    <Head title="Chart of Accounts" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Chart of Accounts', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Chart of Accounts</h1>
                    <p class="text-gray-600">Manage your accounting chart of accounts</p>
                </div>
                <Link
                    :href="administration.chartOfAccounts.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Account
                </Link>
            </div>

            <!-- Accounts List -->
            <div v-if="props.accounts.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <BookOpen class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No accounts found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first account.</p>
                <div class="mt-6">
                    <Link
                        :href="administration.chartOfAccounts.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Account
                    </Link>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Account Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Parent Account</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="account in props.accounts"
                            :key="account.id"
                            class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            tabindex="0"
                            role="link"
                            @click="router.visit(administration.chartOfAccounts.show(account.id).url)"
                            @keydown.enter.prevent="router.visit(administration.chartOfAccounts.show(account.id).url)"
                            @keydown.space.prevent="router.visit(administration.chartOfAccounts.show(account.id).url)"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="font-mono text-sm font-medium text-gray-900">{{ account.account_code }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <BookOpen class="mr-2 h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium text-gray-900">{{ account.account_name }}</span>
                                            <span v-if="account.is_default_sales" class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Default Sales</span>
                                            <span v-if="account.is_default_purchasing" class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Default Purchasing</span>
                                            <span v-if="account.is_default_rounding" class="inline-flex rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700">Default Rounding</span>
                                        </div>
                                        <div v-if="account.description" class="text-sm text-gray-500 line-clamp-1">
                                            {{ account.description }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                        getAccountTypeColor(account.account_type)
                                    ]"
                                >
                                    {{ account.account_type }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ account.parent_account?.account_name || '—' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                        account.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ account.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="administration.chartOfAccounts.edit(account.id).url"
                                        class="inline-flex items-center justify-center rounded-md p-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 md:p-0 md:hover:bg-transparent hover:text-indigo-900"
                                    >
                                        <ListTableActionLabel label="Edit">
                                            <Edit class="h-4 w-4" />
                                        </ListTableActionLabel>
                                    </Link>
                                    <button
                                        type="button"
                                        @click="deleteAccount(account)"
                                        class="inline-flex items-center justify-center rounded-md p-2 text-sm font-medium text-red-600 hover:bg-red-50 md:p-0 md:hover:bg-transparent hover:text-red-900"
                                    >
                                        <ListTableActionLabel label="Delete">
                                            <Trash2 class="h-4 w-4" />
                                        </ListTableActionLabel>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
