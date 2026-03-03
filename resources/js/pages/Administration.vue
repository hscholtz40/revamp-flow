<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import administration from '@/routes/administration';
import products from '@/routes/products';
import users from '@/routes/users';
import groups from '@/routes/groups';
import companySettings from '@/routes/company-settings';
import smsSettings from '@/routes/sms-settings';
import whatsappSettings from '@/routes/whatsapp-settings';
import { Users, UsersRound, Shield, Building2, UserCheck, Package, Tag, MessageSquare, Zap, FileText, ClipboardList, Database, Percent, BookOpen, CreditCard, ArrowUpCircle, Eye, KeyRound, Hash } from 'lucide-vue-next';
import auditLogs from '@/routes/audit-logs';
import backups from '@/routes/backups/index';
import { ref } from 'vue';

const upgradeForm = useForm({});
const isUpgrading = ref(false);

const upgradeDatabase = () => {
    if (confirm('Are you sure you want to upgrade the database? This will run pending migrations.')) {
        isUpgrading.value = true;
        upgradeForm.post('/administration/upgrade-database', {
            preserveScroll: true,
            onFinish: () => {
                isUpgrading.value = false;
            },
        });
    }
};

const props = defineProps<{
    stats: {
        users_count: number;
        groups_count: number;
        products_count: number;
        audit_logs_count: number;
        backups_count: number;
        teams_count: number;
    };
}>();
</script>

<template>
    <Head title="Administration" />

    <AppLayout :breadcrumbs="[{ title: 'Administration', href: administration.index().url }]">
        <div class="p-4">
            <!-- Flash Messages -->
            <div v-if="$page.props.flash?.success" class="mb-4 rounded-lg bg-green-100 border border-green-400 px-4 py-3 text-green-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-lg bg-red-100 border border-red-400 px-4 py-3 text-red-700">
                {{ $page.props.flash.error }}
            </div>
            
            <h1 class="mb-6 text-2xl font-bold">Administration</h1>
            
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Users Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Users</h2>
                            <p class="text-sm text-gray-600">Manage user accounts and permissions</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ props.stats.users_count }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Users class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link 
                            v-if="$page.props.auth?.abilities?.users?.list"
                            :href="users.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Users
                        </Link>
                        <Link 
                            v-if="$page.props.auth?.abilities?.users?.create"
                            :href="users.create().url" 
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add User
                        </Link>
                    </div>
                </div>

                <!-- Groups Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Groups</h2>
                            <p class="text-sm text-gray-600">Manage user groups and permissions</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ props.stats.groups_count }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Shield class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link 
                            v-if="$page.props.auth?.abilities?.groups?.list"
                            :href="groups.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Groups
                        </Link>
                        <Link 
                            v-if="$page.props.auth?.abilities?.groups?.create"
                            :href="groups.create().url" 
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add Group
                        </Link>
                    </div>
                </div>

                <!-- Teams Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Teams</h2>
                            <p class="text-sm text-gray-600">Organize users into teams for jobcard assignment</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ props.stats.teams_count }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <UsersRound class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link 
                            href="/administration/teams"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Teams
                        </Link>
                        <Link 
                            href="/administration/teams/create"
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add Team
                        </Link>
                    </div>
                </div>

                <!-- Categories Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Categories</h2>
                            <p class="text-sm text-gray-600">Manage product and service categories</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Tag class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link
                            :href="administration.categories.index().url"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Categories
                        </Link>
                    </div>
                </div>

                <!-- Company Settings Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Companies</h2>
                            <p class="text-sm text-gray-600">Manage company information and branding</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Building2 class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="companySettings.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Companies
                        </Link>
                    </div>
                </div>

                <!-- SMS Settings Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">SMS Settings</h2>
                            <p class="text-sm text-gray-600">Configure BulkSMS for customer messaging</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <MessageSquare class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="smsSettings.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Configure SMS
                        </Link>
                    </div>
                </div>

                <!-- WhatsApp Settings Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">WhatsApp Settings</h2>
                            <p class="text-sm text-gray-600">Configure WhatsApp Business API for customer messaging</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <MessageSquare class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="whatsappSettings.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Configure WhatsApp
                        </Link>
                    </div>
                </div>

                <!-- Xero Integration Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Xero Integration</h2>
                            <p class="text-sm text-gray-600">Sync customers, products, and invoices with Xero</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Zap class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            href="/administration/xero-settings" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Configure Xero
                        </Link>
                    </div>
                </div>

                <!-- PDF Templates Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">PDF Templates</h2>
                            <p class="text-sm text-gray-600">Manage PDF templates for invoices, quotes, jobcards, and proforma invoices</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <FileText class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="administration.pdfTemplates.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Templates
                        </Link>
                    </div>
                </div>

                <!-- Audit Logs Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Audit Logs</h2>
                            <p class="text-sm text-gray-600">Track all changes and activities in the system</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ props.stats.audit_logs_count }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <ClipboardList class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="auditLogs.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Audit Logs
                        </Link>
                    </div>
                </div>

                <!-- Backups & Restore Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Backups & Restore</h2>
                            <p class="text-sm text-gray-600">Automated backups, scheduling, and one-click restore</p>
                            <p class="mt-2 text-2xl font-bold text-primary">{{ props.stats.backups_count }}</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Database class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="backups.index().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Backups
                        </Link>
                    </div>
                </div>

                <!-- Database Upgrade Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Database Upgrade</h2>
                            <p class="text-sm text-gray-600">Run pending database migrations to upgrade the system</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <ArrowUpCircle class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <button
                            @click="upgradeDatabase"
                            :disabled="isUpgrading"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            <span v-if="isUpgrading">Upgrading...</span>
                            <span v-else>Upgrade Database</span>
                        </button>
                    </div>
                </div>

                <!-- Module Visibility Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Module Visibility</h2>
                            <p class="text-sm text-gray-600">Control which modules appear in the sidebar menu</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Eye class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link 
                            :href="administration.moduleVisibility().url" 
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Modules
                        </Link>
                    </div>
                </div>

                <!-- License Card -->
                <div v-if="!$page.props.isLicensingInstance" class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">License</h2>
                            <p class="text-sm text-gray-600">Set and validate this instance license key</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <KeyRound class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link
                            href="/administration/license"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage License
                        </Link>
                    </div>
                </div>

                <!-- Document Numbering Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Document Numbering</h2>
                            <p class="text-sm text-gray-600">Configure prefixes and next numbers for invoices, quotes, jobcards, credit notes, and purchase orders</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Hash class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto pt-4">
                        <Link
                            href="/administration/document-numbering"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            Manage Numbering
                        </Link>
                    </div>
                </div>

                <!-- Tax Rates Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Tax Rates</h2>
                            <p class="text-sm text-gray-600">Manage tax rates for invoices, quotes, and jobcards</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <Percent class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link
                            :href="administration.taxRates.index().url"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Tax Rates
                        </Link>
                        <Link
                            :href="administration.taxRates.create().url"
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add Tax Rate
                        </Link>
                    </div>
                </div>

                <!-- Chart of Accounts Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Chart of Accounts</h2>
                            <p class="text-sm text-gray-600">Manage your company's chart of accounts</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <BookOpen class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link
                            :href="administration.chartOfAccounts.index().url"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Accounts
                        </Link>
                        <Link
                            :href="administration.chartOfAccounts.create().url"
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add Account
                        </Link>
                    </div>
                </div>

                <!-- Bank Accounts Card -->
                <div class="flex flex-col rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Bank Accounts</h2>
                            <p class="text-sm text-gray-600">Manage bank accounts for your company</p>
                        </div>
                        <div class="rounded-full bg-primary/10 p-3">
                            <CreditCard class="h-6 w-6 text-primary" />
                        </div>
                    </div>
                    <div class="mt-auto flex gap-2 pt-4">
                        <Link
                            :href="administration.bankAccounts.index().url"
                            class="rounded bg-primary px-3 py-2 text-sm font-medium text-white hover:bg-primary/90 transition-colors"
                        >
                            View Bank Accounts
                        </Link>
                        <Link
                            :href="administration.bankAccounts.create().url"
                            class="rounded border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Add Bank Account
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
