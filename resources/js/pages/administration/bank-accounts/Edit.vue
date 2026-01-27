<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
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

const form = useForm({
    account_name: props.bankAccount.account_name,
    account_number: props.bankAccount.account_number,
    bank_name: props.bankAccount.bank_name,
    branch_code: props.bankAccount.branch_code || '',
    account_type: props.bankAccount.account_type,
    currency: props.bankAccount.currency,
    opening_balance: props.bankAccount.opening_balance,
    notes: props.bankAccount.notes || '',
    is_active: props.bankAccount.is_active,
    is_default: props.bankAccount.is_default,
});

const accountTypes = ['Current', 'Savings', 'Credit Card', 'Loan', 'Other'];
const currencies = ['ZAR', 'USD', 'EUR', 'GBP'];

function submit() {
    form.put(administration.bankAccounts.update(props.bankAccount.id).url);
}
</script>

<template>
    <Head :title="`Edit ${props.bankAccount.account_name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Bank Accounts', href: administration.bankAccounts.index().url },
        { title: props.bankAccount.account_name, href: administration.bankAccounts.show(props.bankAccount.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="administration.bankAccounts.show(props.bankAccount.id).url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Bank Account
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Edit Bank Account</h1>
                    <p class="text-gray-600">Update the details for {{ props.bankAccount.account_name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        
                        <div class="space-y-6">
                            <!-- Account Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Name *
                                </label>
                                <input
                                    v-model="form.account_name"
                                    type="text"
                                    required
                                    placeholder="e.g., Main Business Account"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.account_name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.account_name }}
                                </div>
                            </div>

                            <!-- Bank Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Bank Name *
                                </label>
                                <input
                                    v-model="form.bank_name"
                                    type="text"
                                    required
                                    placeholder="e.g., First National Bank"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.bank_name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.bank_name }}
                                </div>
                            </div>

                            <!-- Account Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Number *
                                </label>
                                <input
                                    v-model="form.account_number"
                                    type="text"
                                    required
                                    placeholder="e.g., 1234567890"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.account_number" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.account_number }}
                                </div>
                            </div>

                            <!-- Branch Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Branch Code
                                </label>
                                <input
                                    v-model="form.branch_code"
                                    type="text"
                                    placeholder="e.g., 250655"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.branch_code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.branch_code }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Account Details</h2>
                        
                        <div class="space-y-6">
                            <!-- Account Type -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Type *
                                </label>
                                <select
                                    v-model="form.account_type"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option v-for="type in accountTypes" :key="type" :value="type">
                                        {{ type }}
                                    </option>
                                </select>
                                <div v-if="form.errors.account_type" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.account_type }}
                                </div>
                            </div>

                            <!-- Currency -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Currency *
                                </label>
                                <select
                                    v-model="form.currency"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option v-for="currency in currencies" :key="currency" :value="currency">
                                        {{ currency }}
                                    </option>
                                </select>
                                <div v-if="form.errors.currency" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.currency }}
                                </div>
                            </div>

                            <!-- Opening Balance -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Opening Balance *
                                </label>
                                <input
                                    v-model="form.opening_balance"
                                    type="number"
                                    step="0.01"
                                    required
                                    placeholder="0.00"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.opening_balance" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.opening_balance }}
                                </div>
                            </div>

                            <!-- Notes -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Notes
                                </label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Optional notes about this account"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.notes" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.notes }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        
                        <div class="space-y-6">
                            <!-- Active Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for selection)</span>
                                </label>
                            </div>

                            <!-- Default Status -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Default (used for Xero payments)</span>
                                </label>
                                <p class="ml-6 mt-1 text-xs text-gray-500">Only one bank account can be set as default. Setting this as default will unset any other default account.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="administration.bankAccounts.show(props.bankAccount.id).url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Updating...' : 'Update Bank Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
