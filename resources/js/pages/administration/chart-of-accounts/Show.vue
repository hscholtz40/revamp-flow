<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, BookOpen, Calendar } from 'lucide-vue-next';
import administration from '@/routes/administration';

interface ChartOfAccount {
    id: number;
    account_code: string;
    account_name: string;
    account_type: string;
    parent_account_id: number | null;
    parent_account?: ChartOfAccount | null;
    child_accounts?: ChartOfAccount[];
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
    account: ChartOfAccount;
}

const props = defineProps<Props>();

function deleteAccount() {
    if (props.account.child_accounts && props.account.child_accounts.length > 0) {
        alert(`Cannot delete "${props.account.account_name}" because it has child accounts. Please delete or reassign child accounts first.`);
        return;
    }
    
    if (confirm(`Are you sure you want to delete "${props.account.account_name}"?`)) {
        router.delete(administration.chartOfAccounts.destroy(props.account.id).url);
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
    <Head :title="props.account.account_name" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'Chart of Accounts', href: administration.chartOfAccounts.index().url },
        { title: props.account.account_name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="administration.chartOfAccounts.index().url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Chart of Accounts
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="administration.chartOfAccounts.edit(props.account.id).url"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                    <button
                        @click="deleteAccount"
                        class="flex items-center gap-2 rounded-md border border-red-300 px-4 py-2 text-red-700 hover:bg-red-50"
                    >
                        <Trash2 class="h-4 w-4" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <!-- Account Header -->
                <div class="mb-8 rounded-lg border bg-white p-6">
                    <div class="flex items-start gap-6">
                        <!-- Icon -->
                        <div class="flex-shrink-0">
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-blue-100">
                                <BookOpen class="h-8 w-8 text-blue-600" />
                            </div>
                        </div>

                        <!-- Account Info -->
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-gray-900">{{ props.account.account_name }}</h1>
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        props.account.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.account.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="props.account.is_default_sales" class="rounded-full bg-blue-100 px-3 py-1 text-sm font-medium text-blue-700">Default Sales</span>
                                <span v-if="props.account.is_default_purchasing" class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700">Default Purchasing</span>
                                <span v-if="props.account.is_default_rounding" class="rounded-full bg-purple-100 px-3 py-1 text-sm font-medium text-purple-700">Default Rounding</span>
                            </div>
                            
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span class="font-mono font-medium">{{ props.account.account_code }}</span>
                                <span
                                    :class="[
                                        'rounded-full px-2 py-1 text-xs font-semibold',
                                        getAccountTypeColor(props.account.account_type)
                                    ]"
                                >
                                    {{ props.account.account_type }}
                                </span>
                            </div>
                            
                            <p v-if="props.account.description" class="mt-2 text-gray-600">
                                {{ props.account.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Account Details Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Basic Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center gap-3">
                                    <BookOpen class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Code</div>
                                        <div class="font-mono text-gray-900">{{ props.account.account_code }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <BookOpen class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Name</div>
                                        <div class="text-gray-900">{{ props.account.account_name }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <BookOpen class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Account Type</div>
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-2 py-1 text-xs font-semibold',
                                                getAccountTypeColor(props.account.account_type)
                                            ]"
                                        >
                                            {{ props.account.account_type }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <BookOpen class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Parent Account</div>
                                        <div class="text-gray-900">{{ props.account.parent_account?.account_name || '—' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Child Accounts -->
                        <div v-if="props.account.child_accounts && props.account.child_accounts.length > 0" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Child Accounts</h2>
                            <div class="space-y-2">
                                <Link
                                    v-for="child in props.account.child_accounts"
                                    :key="child.id"
                                    :href="administration.chartOfAccounts.show(child.id).url"
                                    class="block rounded-md border border-gray-200 p-3 hover:bg-gray-50"
                                >
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="font-mono text-sm text-gray-600">{{ child.account_code }}</span>
                                            <span class="ml-2 font-medium text-gray-900">{{ child.account_name }}</span>
                                        </div>
                                        <span
                                            :class="[
                                                'rounded-full px-2 py-1 text-xs font-semibold',
                                                getAccountTypeColor(child.account_type)
                                            ]"
                                        >
                                            {{ child.account_type }}
                                        </span>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="props.account.description" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                            <div class="text-gray-700 whitespace-pre-wrap">{{ props.account.description }}</div>
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
                                            props.account.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.account.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Child Accounts</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.account.child_accounts?.length || 0 }}
                                    </span>
                                </div>

                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Sort Order</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.account.sort_order }}
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
                                            {{ new Date(props.account.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.account.updated_at).toLocaleDateString() }}
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
