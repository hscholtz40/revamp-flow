<script setup lang="ts">
import { useNumberFormat } from '@/composables/useNumberFormat';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Edit, Trash2, Eye, Building2 } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface BankAccount {
    id: number;
    account_name: string;
    account_number: string;
    bank_name: string;
    branch_code: string | null;
    account_type: string;
    currency: string;
    opening_balance: number;
    notes: string | null;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    bankAccounts: BankAccount[];
}

const props = defineProps<Props>();
const { formatCurrency } = useNumberFormat();

function deleteBankAccount(bankAccount: BankAccount) {
    if (confirm(`Are you sure you want to delete "${bankAccount.account_name}"?`)) {
        router.delete(administration.bankAccounts.destroy(bankAccount.id).url);
    }
}

</script>

<template>
    <Head title="Bank Account Management" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Bank Accounts', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Bank Account Management</h1>
                    <p class="text-gray-600">Manage your company bank accounts</p>
                </div>
                <Link
                    :href="administration.bankAccounts.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Bank Account
                </Link>
            </div>

            <!-- Bank Accounts List -->
            <div v-if="props.bankAccounts.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Building2 class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No bank accounts found</h3>
                <p class="mt-1 text-gray-500">Get started by creating your first bank account.</p>
                <div class="mt-6">
                    <Link
                        :href="administration.bankAccounts.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Bank Account
                    </Link>
                </div>
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Account Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Bank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Account Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Opening Balance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Default</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <tr
                            v-for="bankAccount in props.bankAccounts"
                            :key="bankAccount.id"
                            class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                            tabindex="0"
                            role="link"
                            @click="router.visit(administration.bankAccounts.show(bankAccount.id).url)"
                            @keydown.enter.prevent="router.visit(administration.bankAccounts.show(bankAccount.id).url)"
                            @keydown.space.prevent="router.visit(administration.bankAccounts.show(bankAccount.id).url)"
                        >
                            <td class="whitespace-nowrap px-6 py-4">
                                <div class="flex items-center">
                                    <Building2 class="mr-2 h-5 w-5 text-gray-400" />
                                    <div class="font-medium text-gray-900">{{ bankAccount.account_name }}</div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ bankAccount.bank_name }}
                                <div v-if="bankAccount.branch_code" class="text-xs text-gray-400">
                                    Branch: {{ bankAccount.branch_code }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-900">
                                {{ bankAccount.account_number }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                {{ bankAccount.account_type }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
                                {{ formatCurrency(bankAccount.opening_balance, bankAccount.currency) }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                        bankAccount.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ bankAccount.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4">
                                <span
                                    v-if="bankAccount.is_default"
                                    class="inline-flex rounded-full px-2 py-1 text-xs font-semibold bg-blue-100 text-blue-800"
                                >
                                    Default
                                </span>
                                <span v-else class="text-gray-400 text-xs">—</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                <div class="flex items-center justify-end gap-2">
                                    <Link
                                        :href="administration.bankAccounts.edit(bankAccount.id).url"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        <Edit class="h-4 w-4" />
                                    </Link>
                                    <button
                                        @click="deleteBankAccount(bankAccount)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        <Trash2 class="h-4 w-4" />
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
