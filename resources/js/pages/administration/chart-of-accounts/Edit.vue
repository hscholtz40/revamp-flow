<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface ChartOfAccount {
    id: number;
    account_code: string;
    account_name: string;
    account_type: string;
    parent_account_id: number | null;
    description: string | null;
    is_active: boolean;
    is_default_sales: boolean;
    is_default_purchasing: boolean;
    is_default_rounding: boolean;
    sort_order: number;
}

interface ParentAccount {
    id: number;
    account_code: string;
    account_name: string;
}

interface Props {
    account: ChartOfAccount;
    parentAccounts: ParentAccount[];
}

const props = defineProps<Props>();

const form = useForm({
    account_code: props.account.account_code,
    account_name: props.account.account_name,
    account_type: props.account.account_type,
    parent_account_id: props.account.parent_account_id,
    description: props.account.description || '',
    is_active: props.account.is_active,
    is_default_sales: props.account.is_default_sales,
    is_default_purchasing: props.account.is_default_purchasing,
    is_default_rounding: props.account.is_default_rounding ?? false,
    sort_order: props.account.sort_order,
});

const accountTypes = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];

function submit() {
    form.put(administration.chartOfAccounts.update(props.account.id).url);
}
</script>

<template>
    <Head :title="`Edit ${props.account.account_name}`" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Chart of Accounts', href: administration.chartOfAccounts.index().url },
        { title: props.account.account_name, href: administration.chartOfAccounts.show(props.account.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="administration.chartOfAccounts.show(props.account.id).url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Account
                </Link>
            </div>

            <div class="mx-auto max-w-2xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Edit Account</h1>
                    <p class="text-gray-600">Update the details for {{ props.account.account_name }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        
                        <div class="space-y-6">
                            <!-- Account Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Code *
                                </label>
                                <input
                                    v-model="form.account_code"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.account_code" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.account_code }}
                                </div>
                            </div>

                            <!-- Account Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Account Name *
                                </label>
                                <input
                                    v-model="form.account_name"
                                    type="text"
                                    required
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.account_name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.account_name }}
                                </div>
                            </div>

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

                            <!-- Parent Account -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Parent Account
                                </label>
                                <select
                                    v-model="form.parent_account_id"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                    <option :value="null">None (Top Level)</option>
                                    <option
                                        v-for="parent in props.parentAccounts"
                                        :key="parent.id"
                                        :value="parent.id"
                                    >
                                        {{ parent.account_code }} - {{ parent.account_name }}
                                    </option>
                                </select>
                                <div v-if="form.errors.parent_account_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.parent_account_id }}
                                </div>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea
                                    v-model="form.description"
                                    rows="3"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.description }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        
                        <div class="space-y-6">
                            <!-- Sort Order -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Sort Order
                                </label>
                                <input
                                    v-model="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.sort_order" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.sort_order }}
                                </div>
                            </div>

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

                            <!-- Default Sales -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default_sales"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Default Account for Sales</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500">Used as default for invoices, quotes, jobcards, and credit notes</p>
                            </div>

                            <!-- Default Purchasing -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default_purchasing"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Default Account for Purchasing</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500">Used as default for purchase orders</p>
                            </div>

                            <!-- Default Rounding -->
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default_rounding"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Default Account for Rounding</span>
                                </label>
                                <p class="mt-1 text-sm text-gray-500">Used for invoice rounding-adjustment line items</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="administration.chartOfAccounts.show(props.account.id).url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Updating...' : 'Update Account' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
