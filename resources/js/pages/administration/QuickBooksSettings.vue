<template>
    <AppLayout>
        <div class="space-y-6">
            <div v-if="settings.needs_reauthorization" class="rounded border border-red-400 bg-red-100 px-4 py-3 text-red-700">
                <div class="flex items-center justify-between">
                    <div>
                        <strong>Authentication Error:</strong> Your QuickBooks connection needs attention. Re-authorize to restore access.
                    </div>
                    <button
                        type="button"
                        class="ml-4 rounded bg-red-600 px-3 py-1 text-sm text-white hover:bg-red-700"
                        @click="authorize"
                    >
                        Re-authorize Now
                    </button>
                </div>
            </div>

            <div v-if="settings.realm_name && !settings.has_refresh_token && !settings.needs_reauthorization" class="rounded border border-orange-400 bg-orange-100 px-4 py-3 text-orange-700">
                <div class="flex items-center justify-between">
                    <div>
                        <strong>Warning:</strong> No refresh token was stored. Re-authorize with <code class="text-xs">offline_access</code> scope if sync fails after the access token expires.
                    </div>
                    <button type="button" class="ml-4 rounded bg-orange-600 px-3 py-1 text-sm text-white hover:bg-orange-700" @click="authorize">
                        Re-authorize Now
                    </button>
                </div>
            </div>

            <div class="rounded-lg bg-white p-6 shadow">
                <div class="mb-6 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">QuickBooks Online Integration</h2>
                        <p class="mt-1 text-sm text-gray-600">Company: {{ currentCompany.name }}</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Status:</span>
                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statusClass">
                            {{ statusText }}
                        </span>
                    </div>
                </div>

                <div v-if="quickbooksUseSandbox" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <strong>Sandbox mode</strong> is enabled (<code class="text-xs">QUICKBOOKS_USE_SANDBOX=true</code>). Use a sandbox app and company from the Intuit Developer portal. Set to
                    <code class="text-xs">false</code> for production API hosts.
                </div>

                <div v-if="availableCompanies.length > 1" class="mb-6 rounded-lg bg-gray-50 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Switch Company</h3>
                            <p class="text-sm text-gray-600">Configure QuickBooks settings per company</p>
                        </div>
                        <select
                            class="rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            :value="currentCompany.id"
                            @change="switchCompany"
                        >
                            <option v-for="company in availableCompanies" :key="company.id" :value="company.id">
                                {{ company.name }}{{ company.is_default ? ' (Default)' : '' }}
                            </option>
                        </select>
                    </div>
                </div>

                <p class="mb-4 text-sm text-gray-600">
                    Register your app at
                    <a href="https://developer.intuit.com/" class="text-blue-600 underline" target="_blank" rel="noopener noreferrer">Intuit Developer</a>
                    and add this redirect URI exactly:
                    <code class="block mt-1 break-all rounded bg-gray-100 px-2 py-1 text-xs">{{ redirectUri }}</code>
                </p>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="flex items-center">
                        <input id="qb_is_enabled" v-model="form.is_enabled" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                        <label for="qb_is_enabled" class="ml-2 block text-sm text-gray-900">Enable QuickBooks Integration</label>
                    </div>

                    <div v-if="form.is_enabled" class="space-y-4 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900">OAuth Configuration</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Client ID</label>
                                <input
                                    v-model="form.client_id"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.client_id }"
                                    placeholder="Intuit app Client ID"
                                />
                                <div v-if="form.errors.client_id" class="mt-1 text-sm text-red-500">{{ form.errors.client_id }}</div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Client Secret</label>
                                <input
                                    v-model="form.client_secret"
                                    type="password"
                                    class="w-full rounded border px-3 py-2"
                                    autocomplete="off"
                                    :placeholder="settings.has_client_secret ? 'Leave blank to keep existing secret' : 'Intuit app Client Secret'"
                                />
                                <p v-if="settings.has_client_secret" class="mt-1 text-xs text-gray-500">Leave blank to keep your current client secret.</p>
                            </div>
                        </div>

                        <div v-if="oauthCredentialsReady" class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-medium text-blue-900">
                                        {{ settings.realm_name ? 'QuickBooks connection' : 'Authorization required' }}
                                    </h4>
                                    <p v-if="!settings.realm_name" class="mt-1 text-sm text-blue-700">Click Authorize to sign in with Intuit and select your QuickBooks company.</p>
                                </div>
                                <button
                                    v-if="!settings.realm_name"
                                    type="button"
                                    class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                                    @click="authorize"
                                >
                                    Authorize with Intuit
                                </button>
                                <div v-else class="text-right">
                                    <p class="text-sm font-medium text-green-700">Connected</p>
                                    <p class="text-xs text-green-600">{{ settings.realm_name }}</p>
                                    <p v-if="settings.realm_id" class="text-xs text-gray-500">Realm ID: {{ settings.realm_id }}</p>
                                    <p v-if="refreshTokenExpiryHint" class="mt-1 max-w-md text-xs text-gray-600">{{ refreshTokenExpiryHint }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="settings.realm_name" class="flex justify-end">
                            <button type="button" class="text-sm text-red-600 hover:text-red-800" @click="disconnect">Disconnect QuickBooks</button>
                        </div>
                    </div>

                    <div v-if="form.is_enabled && settings.realm_name" class="space-y-4 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900">Sync Settings</h3>
                        <p class="text-sm text-gray-600">
                            Direction toggles are saved for future sync jobs. Manual actions below currently show a notice until QuickBooks data sync is implemented.
                        </p>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-medium text-gray-900">Customers</h4>
                                <input v-model="form.sync_customers" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                            </div>
                            <div v-if="form.sync_customers" class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_customers_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_customers_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-medium text-gray-900">Products</h4>
                                <input v-model="form.sync_products" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                            </div>
                            <div v-if="form.sync_products" class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_products_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_products_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="font-medium text-gray-900">Invoices</h4>
                                <input v-model="form.sync_invoices" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                            </div>
                            <div v-if="form.sync_invoices" class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_invoices_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_invoices_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Credit Notes</h4>
                            <div class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_credit_notes_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_credit_notes_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Purchase Orders</h4>
                            <div class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_purchase_orders_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_purchase_orders_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Suppliers</h4>
                            <div class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_suppliers_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_suppliers_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Quotes</h4>
                            <div class="ml-6 space-y-2">
                                <label class="flex items-center">
                                    <input v-model="form.sync_quotes_to_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync to QuickBooks</span>
                                </label>
                                <label class="flex items-center">
                                    <input v-model="form.sync_quotes_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Sync from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Tax Rates</h4>
                            <div class="ml-6">
                                <label class="flex items-center">
                                    <input v-model="form.sync_tax_rates_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Import from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Bank Accounts</h4>
                            <div class="ml-6">
                                <label class="flex items-center">
                                    <input v-model="form.sync_bank_accounts_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Import from QuickBooks</span>
                                </label>
                            </div>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-4">
                            <h4 class="mb-3 font-medium text-gray-900">Chart of Accounts</h4>
                            <div class="ml-6">
                                <label class="flex items-center">
                                    <input v-model="form.sync_chart_of_accounts_from_quickbooks" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-blue-600" />
                                    <span class="ml-2 text-sm text-gray-700">Import from QuickBooks</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t pt-6">
                        <button type="submit" class="rounded bg-blue-600 px-6 py-2 text-white hover:bg-blue-700 disabled:opacity-50" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="form.is_enabled && settings.realm_name" class="rounded-lg bg-white p-6 shadow">
                <h3 class="mb-4 text-lg font-medium text-gray-900">Manual Sync</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <button
                        v-if="form.sync_customers_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/customers', 'customers')"
                    >
                        {{ syncing === 'customers' ? 'Working...' : 'Sync Customers to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_products_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/products', 'products')"
                    >
                        {{ syncing === 'products' ? 'Working...' : 'Sync Products to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_suppliers_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/suppliers', 'suppliers')"
                    >
                        {{ syncing === 'suppliers' ? 'Working...' : 'Sync Suppliers to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_quotes_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/quotes', 'quotes')"
                    >
                        {{ syncing === 'quotes' ? 'Working...' : 'Sync Quotes to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_invoices_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/invoices', 'invoices')"
                    >
                        {{ syncing === 'invoices' ? 'Working...' : 'Sync Invoices to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_credit_notes_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/credit-notes', 'credit-notes')"
                    >
                        {{ syncing === 'credit-notes' ? 'Working...' : 'Sync Credit Notes to QuickBooks' }}
                    </button>
                    <button
                        v-if="form.sync_purchase_orders_to_quickbooks"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                        :disabled="isSyncing"
                        @click="postSync('/quickbooks/sync/purchase-orders', 'purchase-orders')"
                    >
                        {{ syncing === 'purchase-orders' ? 'Working...' : 'Sync Purchase Orders to QuickBooks' }}
                    </button>
                </div>

                <div class="mt-6 rounded-lg bg-blue-50 p-4">
                    <h3 class="mb-3 text-lg font-medium text-blue-900">Import from QuickBooks</h3>
                    <div class="flex flex-wrap gap-3">
                        <button
                            v-if="form.sync_customers_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/customers-from-quickbooks', 'customers-from-qb')"
                        >
                            {{ syncing === 'customers-from-qb' ? 'Working...' : 'Import Customers' }}
                        </button>
                        <button
                            v-if="form.sync_customers_from_quickbooks"
                            class="rounded bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="resyncCustomers"
                        >
                            {{ syncing === 'customers-resync' ? 'Working...' : 'Resync All Customers' }}
                        </button>
                        <button
                            v-if="form.sync_products_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/products-from-quickbooks', 'products-from-qb')"
                        >
                            {{ syncing === 'products-from-qb' ? 'Working...' : 'Import Products' }}
                        </button>
                        <button
                            v-if="form.sync_suppliers_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/suppliers-from-quickbooks', 'suppliers-from-qb')"
                        >
                            {{ syncing === 'suppliers-from-qb' ? 'Working...' : 'Import Suppliers' }}
                        </button>
                        <button
                            v-if="form.sync_quotes_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/quotes-from-quickbooks', 'quotes-from-qb')"
                        >
                            {{ syncing === 'quotes-from-qb' ? 'Working...' : 'Import Quotes' }}
                        </button>
                        <button
                            v-if="form.sync_invoices_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/invoices-from-quickbooks', 'invoices-from-qb')"
                        >
                            {{ syncing === 'invoices-from-qb' ? 'Working...' : 'Import Invoices' }}
                        </button>
                        <button
                            v-if="form.sync_credit_notes_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/credit-notes-from-quickbooks', 'cn-from-qb')"
                        >
                            {{ syncing === 'cn-from-qb' ? 'Working...' : 'Import Credit Notes' }}
                        </button>
                        <button
                            v-if="form.sync_purchase_orders_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/purchase-orders-from-quickbooks', 'po-from-qb')"
                        >
                            {{ syncing === 'po-from-qb' ? 'Working...' : 'Import Purchase Orders' }}
                        </button>
                        <button
                            v-if="form.sync_tax_rates_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/tax-rates-from-quickbooks', 'tax-from-qb')"
                        >
                            {{ syncing === 'tax-from-qb' ? 'Working...' : 'Import Tax Rates' }}
                        </button>
                        <button
                            v-if="form.sync_bank_accounts_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/bank-accounts-from-quickbooks', 'bank-from-qb')"
                        >
                            {{ syncing === 'bank-from-qb' ? 'Working...' : 'Import Bank Accounts' }}
                        </button>
                        <button
                            v-if="form.sync_chart_of_accounts_from_quickbooks"
                            class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="isSyncing"
                            @click="postSync('/quickbooks/sync/chart-of-accounts-from-quickbooks', 'coa-from-qb')"
                        >
                            {{ syncing === 'coa-from-qb' ? 'Working...' : 'Import Chart of Accounts' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

interface Props {
    settings: {
        id: number;
        is_enabled: boolean;
        client_id: string | null;
        realm_id: string | null;
        realm_name: string | null;
        has_client_secret: boolean;
        has_access_token: boolean;
        has_refresh_token: boolean;
        is_connected: boolean;
        needs_reauthorization: boolean;
        sync_customers: boolean;
        sync_products: boolean;
        sync_invoices: boolean;
        sync_customers_to_quickbooks: boolean;
        sync_customers_from_quickbooks: boolean;
        sync_products_to_quickbooks: boolean;
        sync_products_from_quickbooks: boolean;
        sync_invoices_to_quickbooks: boolean;
        sync_invoices_from_quickbooks: boolean;
        sync_credit_notes_to_quickbooks: boolean;
        sync_credit_notes_from_quickbooks: boolean;
        sync_purchase_orders_to_quickbooks: boolean;
        sync_purchase_orders_from_quickbooks: boolean;
        sync_suppliers_to_quickbooks: boolean;
        sync_suppliers_from_quickbooks: boolean;
        sync_quotes_to_quickbooks: boolean;
        sync_quotes_from_quickbooks: boolean;
        sync_tax_rates_from_quickbooks: boolean;
        sync_bank_accounts_from_quickbooks: boolean;
        sync_chart_of_accounts_from_quickbooks: boolean;
        refresh_token_expires_at: string | null;
    };
    currentCompany: {
        id: number;
        name: string;
    };
    availableCompanies: Array<{
        id: number;
        name: string;
        is_default: boolean;
    }>;
    quickbooksUseSandbox: boolean;
}

const props = defineProps<Props>();

const refreshTokenExpiryHint = computed(() => {
    const raw = props.settings.refresh_token_expires_at;
    if (!raw) {
        return '';
    }
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    return `Refresh token expires (Intuit): ${d.toLocaleString()}`;
});

const redirectUri = typeof window !== 'undefined' ? `${window.location.origin}/quickbooks/callback` : '/quickbooks/callback';

const syncing = ref<string | null>(null);
const isSyncing = computed(() => syncing.value !== null);

const form = useForm({
    is_enabled: props.settings.is_enabled,
    client_id: props.settings.client_id || '',
    client_secret: '',
    sync_customers: props.settings.sync_customers,
    sync_products: props.settings.sync_products,
    sync_invoices: props.settings.sync_invoices,
    sync_customers_to_quickbooks: props.settings.sync_customers_to_quickbooks,
    sync_customers_from_quickbooks: props.settings.sync_customers_from_quickbooks,
    sync_products_to_quickbooks: props.settings.sync_products_to_quickbooks,
    sync_products_from_quickbooks: props.settings.sync_products_from_quickbooks,
    sync_invoices_to_quickbooks: props.settings.sync_invoices_to_quickbooks,
    sync_invoices_from_quickbooks: props.settings.sync_invoices_from_quickbooks,
    sync_credit_notes_to_quickbooks: props.settings.sync_credit_notes_to_quickbooks,
    sync_credit_notes_from_quickbooks: props.settings.sync_credit_notes_from_quickbooks,
    sync_purchase_orders_to_quickbooks: props.settings.sync_purchase_orders_to_quickbooks,
    sync_purchase_orders_from_quickbooks: props.settings.sync_purchase_orders_from_quickbooks,
    sync_suppliers_to_quickbooks: props.settings.sync_suppliers_to_quickbooks,
    sync_suppliers_from_quickbooks: props.settings.sync_suppliers_from_quickbooks,
    sync_quotes_to_quickbooks: props.settings.sync_quotes_to_quickbooks,
    sync_quotes_from_quickbooks: props.settings.sync_quotes_from_quickbooks,
    sync_tax_rates_from_quickbooks: props.settings.sync_tax_rates_from_quickbooks,
    sync_bank_accounts_from_quickbooks: props.settings.sync_bank_accounts_from_quickbooks,
    sync_chart_of_accounts_from_quickbooks: props.settings.sync_chart_of_accounts_from_quickbooks,
});

const oauthCredentialsReady = computed(() => {
    const id = (form.client_id || '').trim();
    return Boolean(id && (form.client_secret.trim() !== '' || props.settings.has_client_secret));
});

watch(
    () => props.settings,
    (s) => {
        form.is_enabled = s.is_enabled;
        form.client_id = s.client_id || '';
        form.client_secret = '';
        form.sync_customers = s.sync_customers;
        form.sync_products = s.sync_products;
        form.sync_invoices = s.sync_invoices;
        form.sync_customers_to_quickbooks = s.sync_customers_to_quickbooks;
        form.sync_customers_from_quickbooks = s.sync_customers_from_quickbooks;
        form.sync_products_to_quickbooks = s.sync_products_to_quickbooks;
        form.sync_products_from_quickbooks = s.sync_products_from_quickbooks;
        form.sync_invoices_to_quickbooks = s.sync_invoices_to_quickbooks;
        form.sync_invoices_from_quickbooks = s.sync_invoices_from_quickbooks;
        form.sync_credit_notes_to_quickbooks = s.sync_credit_notes_to_quickbooks;
        form.sync_credit_notes_from_quickbooks = s.sync_credit_notes_from_quickbooks;
        form.sync_purchase_orders_to_quickbooks = s.sync_purchase_orders_to_quickbooks;
        form.sync_purchase_orders_from_quickbooks = s.sync_purchase_orders_from_quickbooks;
        form.sync_suppliers_to_quickbooks = s.sync_suppliers_to_quickbooks;
        form.sync_suppliers_from_quickbooks = s.sync_suppliers_from_quickbooks;
        form.sync_quotes_to_quickbooks = s.sync_quotes_to_quickbooks;
        form.sync_quotes_from_quickbooks = s.sync_quotes_from_quickbooks;
        form.sync_tax_rates_from_quickbooks = s.sync_tax_rates_from_quickbooks;
        form.sync_bank_accounts_from_quickbooks = s.sync_bank_accounts_from_quickbooks;
        form.sync_chart_of_accounts_from_quickbooks = s.sync_chart_of_accounts_from_quickbooks;
    },
    { deep: true },
);

const statusText = computed(() => {
    if (!form.is_enabled) return 'Disabled';
    if (!props.settings.is_connected) return 'Not Connected';
    return 'Connected';
});

const statusClass = computed(() => {
    if (!form.is_enabled) return 'bg-gray-100 text-gray-800';
    if (!props.settings.is_connected) return 'bg-yellow-100 text-yellow-800';
    return 'bg-green-100 text-green-800';
});

const submit = () => {
    form.put('/administration/quickbooks-settings');
};

const authorize = () => {
    window.location.href = '/quickbooks/authorize';
};

const disconnect = () => {
    if (confirm('Disconnect QuickBooks for this company?')) {
        form.delete('/quickbooks/disconnect');
    }
};

const switchCompany = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const companyId = parseInt(target.value, 10);
    const switchForm = useForm({ company_id: companyId });
    switchForm.post('/quickbooks/switch-company');
};

const postSync = (url: string, key: string) => {
    syncing.value = key;
    const f = useForm({});
    f.post(url, {
        onFinish: () => {
            syncing.value = null;
        },
    });
};

const resyncCustomers = () => {
    if (!confirm('Resync all customers from QuickBooks? (Placeholder until sync is implemented.)')) {
        return;
    }
    postSync('/quickbooks/sync/customers-from-quickbooks/resync', 'customers-resync');
};
</script>
