<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import administration from '@/routes/administration';
import products from '@/routes/products';
import users from '@/routes/users';
import groups from '@/routes/groups';
import companySettings from '@/routes/company-settings';
import smsSettings from '@/routes/sms-settings';
import whatsappSettings from '@/routes/whatsapp-settings';
import { Users, Shield, Building2, UserCheck, Package, Tag, MessageSquare, Zap, FileText, ClipboardList, Database, Percent, BookOpen, CreditCard } from 'lucide-vue-next';
import auditLogs from '@/routes/audit-logs';
import backups from '@/routes/backups/index';

const props = defineProps<{
    stats: {
        users_count: number;
        groups_count: number;
        products_count: number;
        audit_logs_count: number;
        backups_count: number;
    };
}>();
</script>

<template>
    <Head title="Administration" />

    <AppLayout :breadcrumbs="[{ title: 'Administration', href: administration.index().url }]">
        <div class="p-4">
            <h1 class="mb-6 text-2xl font-bold">Administration</h1>
            
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                <!-- Users Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Users</h2>
                            <p class="text-sm text-gray-600">Manage user accounts and permissions</p>
                            <p class="mt-2 text-2xl font-bold text-blue-600">{{ props.stats.users_count }}</p>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3">
                            <Users class="h-6 w-6 text-blue-600" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Link 
                            v-if="$page.props.auth?.abilities?.users?.list"
                            :href="users.index().url" 
                            class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            View Users
                        </Link>
                        <Link 
                            v-if="$page.props.auth?.abilities?.users?.create"
                            :href="users.create().url" 
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Add User
                        </Link>
                    </div>
                </div>

                <!-- Groups Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Groups</h2>
                            <p class="text-sm text-gray-600">Manage user groups and permissions</p>
                            <p class="mt-2 text-2xl font-bold text-green-600">{{ props.stats.groups_count }}</p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3">
                            <Shield class="h-6 w-6 text-green-600" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Link 
                            v-if="$page.props.auth?.abilities?.groups?.list"
                            :href="groups.index().url" 
                            class="rounded bg-green-600 px-3 py-2 text-sm text-white hover:bg-green-700"
                        >
                            View Groups
                        </Link>
                        <Link 
                            v-if="$page.props.auth?.abilities?.groups?.create"
                            :href="groups.create().url" 
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Add Group
                        </Link>
                    </div>
                </div>

                <!-- Categories Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Categories</h2>
                            <p class="text-sm text-gray-600">Manage product and service categories</p>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3">
                            <Tag class="h-6 w-6 text-purple-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link
                            :href="administration.categories.index().url"
                            class="rounded bg-purple-600 px-3 py-2 text-sm text-white hover:bg-purple-700"
                        >
                            Manage Categories
                        </Link>
                    </div>
                </div>

                <!-- Company Settings Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Companies</h2>
                            <p class="text-sm text-gray-600">Manage company information and branding</p>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3">
                            <Building2 class="h-6 w-6 text-purple-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="companySettings.index().url" 
                            class="rounded bg-purple-600 px-3 py-2 text-sm text-white hover:bg-purple-700"
                        >
                            Manage Companies
                        </Link>
                    </div>
                </div>

                <!-- SMS Settings Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">SMS Settings</h2>
                            <p class="text-sm text-gray-600">Configure BulkSMS for customer messaging</p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3">
                            <MessageSquare class="h-6 w-6 text-green-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="smsSettings.index().url" 
                            class="rounded bg-green-600 px-3 py-2 text-sm text-white hover:bg-green-700"
                        >
                            Configure SMS
                        </Link>
                    </div>
                </div>

                <!-- WhatsApp Settings Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">WhatsApp Settings</h2>
                            <p class="text-sm text-gray-600">Configure WhatsApp Business API for customer messaging</p>
                        </div>
                        <div class="rounded-full bg-green-100 p-3">
                            <MessageSquare class="h-6 w-6 text-green-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="whatsappSettings.index().url" 
                            class="rounded bg-green-600 px-3 py-2 text-sm text-white hover:bg-green-700"
                        >
                            Configure WhatsApp
                        </Link>
                    </div>
                </div>

                <!-- Xero Integration Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Xero Integration</h2>
                            <p class="text-sm text-gray-600">Sync customers, products, and invoices with Xero</p>
                        </div>
                        <div class="rounded-full bg-orange-100 p-3">
                            <Zap class="h-6 w-6 text-orange-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            href="/administration/xero-settings" 
                            class="rounded bg-orange-600 px-3 py-2 text-sm text-white hover:bg-orange-700"
                        >
                            Configure Xero
                        </Link>
                    </div>
                </div>

                <!-- PDF Templates Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">PDF Templates</h2>
                            <p class="text-sm text-gray-600">Manage PDF templates for invoices, quotes, jobcards, and proforma invoices</p>
                        </div>
                        <div class="rounded-full bg-indigo-100 p-3">
                            <FileText class="h-6 w-6 text-indigo-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="administration.pdfTemplates.index().url" 
                            class="rounded bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700"
                        >
                            Manage Templates
                        </Link>
                    </div>
                </div>

                <!-- Audit Logs Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Audit Logs</h2>
                            <p class="text-sm text-gray-600">Track all changes and activities in the system</p>
                            <p class="mt-2 text-2xl font-bold text-purple-600">{{ props.stats.audit_logs_count }}</p>
                        </div>
                        <div class="rounded-full bg-purple-100 p-3">
                            <ClipboardList class="h-6 w-6 text-purple-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="auditLogs.index().url" 
                            class="rounded bg-purple-600 px-3 py-2 text-sm text-white hover:bg-purple-700"
                        >
                            View Audit Logs
                        </Link>
                    </div>
                </div>

                <!-- Backups & Restore Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Backups & Restore</h2>
                            <p class="text-sm text-gray-600">Automated backups, scheduling, and one-click restore</p>
                            <p class="mt-2 text-2xl font-bold text-blue-600">{{ props.stats.backups_count }}</p>
                        </div>
                        <div class="rounded-full bg-blue-100 p-3">
                            <Database class="h-6 w-6 text-blue-600" />
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link 
                            :href="backups.index().url" 
                            class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Manage Backups
                        </Link>
                    </div>
                </div>

                <!-- Tax Rates Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Tax Rates</h2>
                            <p class="text-sm text-gray-600">Manage tax rates for invoices, quotes, and jobcards</p>
                        </div>
                        <div class="rounded-full bg-red-100 p-3">
                            <Percent class="h-6 w-6 text-red-600" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="administration.taxRates.index().url"
                            class="rounded bg-red-600 px-3 py-2 text-sm text-white hover:bg-red-700"
                        >
                            View Tax Rates
                        </Link>
                        <Link
                            :href="administration.taxRates.create().url"
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Add Tax Rate
                        </Link>
                    </div>
                </div>

                <!-- Chart of Accounts Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Chart of Accounts</h2>
                            <p class="text-sm text-gray-600">Manage your company's chart of accounts</p>
                        </div>
                        <div class="rounded-full bg-teal-100 p-3">
                            <BookOpen class="h-6 w-6 text-teal-600" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="administration.chartOfAccounts.index().url"
                            class="rounded bg-teal-600 px-3 py-2 text-sm text-white hover:bg-teal-700"
                        >
                            View Accounts
                        </Link>
                        <Link
                            :href="administration.chartOfAccounts.create().url"
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Add Account
                        </Link>
                    </div>
                </div>

                <!-- Bank Accounts Card -->
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold">Bank Accounts</h2>
                            <p class="text-sm text-gray-600">Manage bank accounts for your company</p>
                        </div>
                        <div class="rounded-full bg-cyan-100 p-3">
                            <CreditCard class="h-6 w-6 text-cyan-600" />
                        </div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <Link
                            :href="administration.bankAccounts.index().url"
                            class="rounded bg-cyan-600 px-3 py-2 text-sm text-white hover:bg-cyan-700"
                        >
                            View Bank Accounts
                        </Link>
                        <Link
                            :href="administration.bankAccounts.create().url"
                            class="rounded border px-3 py-2 text-sm hover:bg-gray-50"
                        >
                            Add Bank Account
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
