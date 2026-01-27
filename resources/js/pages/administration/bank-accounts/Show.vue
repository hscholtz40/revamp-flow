<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Building2, Calendar, CreditCard } from 'lucide-vue-next';
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
    bankAccount: BankAccount;
}

const props = defineProps<Props>();

function formatCurrency(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

function deleteBankAccount() {
    if (confirm(`Are you sure you want to delete "${props.bankAccount.account_name}"?`)) {
        router.delete(administration.bankAccounts.destroy(props.bankAccount.id).url);
    }
}
</script>

<template>
    <Head :title="props.bankAccount.account_name" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Bank Accounts', href: administration.bankAccounts.index().url },
        { title: props.bankAccount.account_name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="administration.bankAccounts.index().url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Bank Accounts
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="administration.bankAccounts.edit(props.bankAccount.id).url"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        @click="deleteBankAccount"
                        class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <!-- Bank Account Header -->
                <div class="mb-8 rounded-lg border bg-white p-6">
                    <div class="flex items-start gap-6">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-blue-100">
                                <Building2 class="h-8 w-8 text-blue-600" />
                            </div>
                        </div>

                        <!-- Bank Account Info -->
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-gray-900">{{ props.bankAccount.account_name }}</h1>
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        props.bankAccount.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.bankAccount.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            
                            <p class="text-gray-600">
                                {{ props.bankAccount.bank_name }} • {{ props.bankAccount.account_type }}
                            </p>
                            <p v-if="props.bankAccount.is_default" class="mt-2">
                                <span class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-800">
                                    Default Account (Used for Xero Payments)
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Bank Account Details Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center gap-3">
                                    <Building2 class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Name</div>
                                        <div class="text-gray-900">{{ props.bankAccount.account_name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Building2 class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Bank Name</div>
                                        <div class="text-gray-900">{{ props.bankAccount.bank_name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <CreditCard class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Number</div>
                                        <div class="font-mono text-gray-900">{{ props.bankAccount.account_number }}</div>
                                    </div>
                                </div>

                                <div v-if="props.bankAccount.branch_code" class="flex items-center gap-3">
                                    <Building2 class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Branch Code</div>
                                        <div class="text-gray-900">{{ props.bankAccount.branch_code }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <CreditCard class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Type</div>
                                        <div class="text-gray-900">{{ props.bankAccount.account_type }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <CreditCard class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Currency</div>
                                        <div class="text-gray-900">{{ props.bankAccount.currency }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Financial Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Financial Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <div class="text-sm font-medium text-gray-500">Opening Balance</div>
                                    <div class="text-xl font-semibold text-gray-900">
                                        {{ formatCurrency(props.bankAccount.opening_balance, props.bankAccount.currency) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="props.bankAccount.notes" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Notes</h2>
                            <div class="text-gray-700 whitespace-pre-wrap">{{ props.bankAccount.notes }}</div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Quick Stats</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.bankAccount.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.bankAccount.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Default</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.bankAccount.is_default ? 'text-blue-600' : 'text-gray-500'
                                        ]"
                                    >
                                        {{ props.bankAccount.is_default ? 'Yes' : 'No' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Account Type</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.bankAccount.account_type }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Currency</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.bankAccount.currency }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Opening Balance</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ formatCurrency(props.bankAccount.opening_balance, props.bankAccount.currency) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Timestamps -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Timestamps</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Created</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.bankAccount.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.bankAccount.updated_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
