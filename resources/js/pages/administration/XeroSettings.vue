<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Authentication Issues Warning -->
            <div v-if="settings.needs_reauthorization" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <div class="flex items-center justify-between">
                    <div>
                        <strong>Authentication Error:</strong> Your Xero connection has failed authentication. 
                        This will cause sync operations to fail. Please re-authorize to restore functionality.
                    </div>
                    <button
                        @click="authorize"
                        class="ml-4 bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                    >
                        Re-authorize Now
                    </button>
                </div>
            </div>
            
            <!-- Refresh Token Warning -->
            <div v-if="settings.tenant_name && !settings.has_refresh_token && !settings.needs_reauthorization" class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded">
                <div class="flex items-center justify-between">
                    <div>
                        <strong>Warning:</strong> Your Xero connection is missing a refresh token. 
                        This may cause the integration to fail when the access token expires. 
                        Please re-authorize to fix this issue.
                    </div>
                    <button
                        @click="authorize"
                        class="ml-4 bg-orange-600 text-white px-3 py-1 rounded text-sm hover:bg-orange-700"
                    >
                        Re-authorize Now
                    </button>
                </div>
            </div>

            <div class="bg-white shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Xero Integration Settings</h2>
                        <p class="text-sm text-gray-600 mt-1">Company: {{ currentCompany.name }}</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Status:</span>
                        <span :class="statusClass" class="px-2 py-1 rounded-full text-xs font-medium">
                            {{ statusText }}
                        </span>
                    </div>
                </div>

                <div v-if="xeroApiUsage" class="mb-6 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-indigo-900">Xero API Usage</h3>
                        <span class="text-xs text-indigo-700">
                            This minute: {{ xeroApiUsage.minute_total }}
                            <template v-if="xeroApiUsage.budget_per_minute > 0">/ {{ xeroApiUsage.budget_per_minute }}</template>
                        </span>
                    </div>
                    <p class="text-xs text-indigo-700 mt-1">Today total calls: {{ xeroApiUsage.day_total }}</p>
                    <div class="mt-3">
                        <p class="text-xs font-medium text-indigo-900 mb-2">Top endpoints today</p>
                        <div v-if="xeroApiUsage.top_endpoints.length" class="space-y-1">
                            <div v-for="row in xeroApiUsage.top_endpoints" :key="row.endpoint" class="flex items-center justify-between text-xs">
                                <span class="text-indigo-800 truncate mr-2">{{ row.endpoint }}</span>
                                <span class="text-indigo-900 font-medium">{{ row.count }}</span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-indigo-700">No API calls recorded yet.</p>
                    </div>
                </div>

                <!-- Company Selector -->
                <div v-if="availableCompanies.length > 1" class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-medium text-gray-900">Switch Company</h3>
                            <p class="text-sm text-gray-600">Select a different company to configure its Xero settings</p>
                        </div>
                        <select
                            :value="currentCompany.id"
                            @change="switchCompany"
                            class="rounded border border-gray-300 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option
                                v-for="company in availableCompanies"
                                :key="company.id"
                                :value="company.id"
                            >
                                {{ company.name }}{{ company.is_default ? ' (Default)' : '' }}
                            </option>
                        </select>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Enable Integration -->
                    <div class="flex items-center">
                        <input
                            id="is_enabled"
                            v-model="form.is_enabled"
                            type="checkbox"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="is_enabled" class="ml-2 block text-sm text-gray-900">
                            Enable Xero Integration
                        </label>
                    </div>

                    <!-- OAuth Configuration -->
                    <div v-if="form.is_enabled" class="space-y-4 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900">OAuth Configuration</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                                <input
                                    v-model="form.client_id"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.client_id }"
                                    placeholder="Enter Xero Client ID"
                                />
                                <div v-if="form.errors.client_id" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.client_id }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                                <input
                                    v-model="form.client_secret"
                                    type="password"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.client_secret }"
                                    :placeholder="settings.has_client_secret ? 'Leave blank to keep existing secret' : 'Enter Xero Client Secret'"
                                    autocomplete="off"
                                />
                                <p v-if="settings.has_client_secret" class="text-xs text-gray-500 mt-1">Leave blank to keep your current client secret.</p>
                                <div v-if="form.errors.client_secret" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.client_secret }}
                                </div>
                            </div>
                        </div>

                        <!-- Authorization Section -->
                        <div v-if="oauthCredentialsReady" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-blue-900">
                                        {{ settings.tenant_name ? 'Xero Connection' : 'Authorization Required' }}
                                    </h4>
                                    <p v-if="!settings.tenant_name && !availableTenants.length" class="text-sm text-blue-700 mt-1">
                                        Click "Authorize with Xero" to connect your Xero account.
                                    </p>
                                </div>
                                <button
                                    v-if="!settings.tenant_name && !availableTenants.length"
                                    type="button"
                                    @click="authorize"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                >
                                    Authorize with Xero
                                </button>
                                <div v-else-if="settings.tenant_name && !changingTenant" class="text-right">
                                    <p class="text-sm font-medium text-green-700">Connected</p>
                                    <p class="text-xs text-green-600">{{ settings.tenant_name }}</p>
                                    <button
                                        v-if="!settings.has_refresh_token"
                                        type="button"
                                        @click="authorize"
                                        class="mt-2 text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700"
                                    >
                                        Re-authorize (Missing Refresh Token)
                                    </button>
                                </div>
                            </div>

                            <!-- Tenant Selection (shown after auth when multiple tenants, or when changing) -->
                            <div v-if="availableTenants.length > 0 && (!settings.tenant_name || changingTenant)" class="mt-4 border-t border-blue-200 pt-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">Select Xero Organisation</h4>
                                <p class="text-sm text-blue-700 mb-3">
                                    Choose the Xero organisation to connect with this company.
                                </p>
                                <div class="flex items-center gap-3">
                                    <select
                                        v-model="selectedTenantId"
                                        class="flex-1 rounded border border-blue-300 px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500"
                                    >
                                        <option value="" disabled>Select an organisation...</option>
                                        <option
                                            v-for="tenant in availableTenants"
                                            :key="tenant.tenantId"
                                            :value="tenant.tenantId"
                                        >
                                            {{ tenant.tenantName }}
                                        </option>
                                    </select>
                                    <button
                                        type="button"
                                        @click="selectTenant"
                                        :disabled="!selectedTenantId"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50 text-sm"
                                    >
                                        Connect
                                    </button>
                                    <button
                                        v-if="changingTenant"
                                        type="button"
                                        @click="changingTenant = false; availableTenants = []"
                                        class="text-gray-600 hover:text-gray-800 px-3 py-2 text-sm"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Connected Actions -->
                        <div v-if="settings.tenant_name" class="flex items-center justify-between">
                            <button
                                type="button"
                                @click="fetchTenants"
                                :disabled="fetchingTenants"
                                class="text-blue-600 hover:text-blue-800 text-sm"
                            >
                                {{ fetchingTenants ? 'Loading...' : 'Change Organisation' }}
                            </button>
                            <button
                                type="button"
                                @click="disconnect"
                                class="text-red-600 hover:text-red-800 text-sm"
                            >
                                Disconnect from Xero
                            </button>
                        </div>
                    </div>

                    <!-- Sync Settings -->
                    <div v-if="form.is_enabled && settings.tenant_name" class="space-y-4 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900">Sync Settings</h3>
                        
                        <!-- Customers -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Customers</h4>
                                <input
                                    v-model="form.sync_customers"
                                    type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                            </div>
                            <div v-if="form.sync_customers" class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_customers_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_customers_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Products -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Products</h4>
                                <input
                                    v-model="form.sync_products"
                                    type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                            </div>
                            <div v-if="form.sync_products" class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_products_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_products_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Invoices -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Invoices</h4>
                                <input
                                    v-model="form.sync_invoices"
                                    type="checkbox"
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                />
                            </div>
                            <div v-if="form.sync_invoices" class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_invoices_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero (including payments)</span>
                                </label>
                                <p class="text-xs text-gray-500 ml-6">Payments will be automatically synced when invoices are synced to Xero</p>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_invoices_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Credit Notes -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Credit Notes</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_credit_notes_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_credit_notes_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Purchase Orders -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Purchase Orders</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_purchase_orders_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_purchase_orders_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Suppliers -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Suppliers</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_suppliers_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_suppliers_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Quotes -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Quotes</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_quotes_to_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync to Xero</span>
                                </label>
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_quotes_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Sync from Xero</span>
                                </label>
                            </div>
                        </div>

                        <!-- Tax Rates -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Tax Rates</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_tax_rates_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Import from Xero</span>
                                </label>
                                <p class="text-xs text-gray-500 ml-6">Import tax rates from Xero to JobCardOnline</p>
                            </div>
                        </div>

                        <!-- Bank Accounts -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Bank Accounts</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_bank_accounts_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Import from Xero</span>
                                </label>
                                <p class="text-xs text-gray-500 ml-6">Import bank accounts from Xero to JobCardOnline</p>
                            </div>
                        </div>

                        <!-- Chart of Accounts -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-medium text-gray-900">Chart of Accounts</h4>
                            </div>
                            <div class="space-y-2 ml-6">
                                <label class="flex items-center">
                                    <input
                                        v-model="form.sync_chart_of_accounts_from_xero"
                                        type="checkbox"
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                    />
                                    <span class="ml-2 text-sm text-gray-700">Import from Xero</span>
                                </label>
                                <p class="text-xs text-gray-500 ml-6">Import chart of accounts from Xero to JobCardOnline</p>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end pt-6 border-t">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Settings' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Sync Actions -->
            <div v-if="form.is_enabled && settings.tenant_name" class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Manual Sync</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <button
                        v-if="form.sync_customers_to_xero"
                        @click="syncCustomers"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'customers' ? 'Syncing...' : 'Sync Customers to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_products_to_xero"
                        @click="syncProducts"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'products' ? 'Syncing...' : 'Sync Products to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_suppliers_to_xero"
                        @click="syncSuppliers"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'suppliers' ? 'Syncing...' : 'Sync Suppliers to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_quotes_to_xero"
                        @click="syncQuotes"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'quotes' ? 'Syncing...' : 'Sync Quotes to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_invoices_to_xero"
                        @click="syncInvoices"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'invoices' ? 'Syncing...' : 'Sync Invoices to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_credit_notes_to_xero"
                        @click="syncCreditNotes"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'credit-notes' ? 'Syncing...' : 'Sync Credit Notes to Xero' }}
                    </button>
                    <button
                        v-if="form.sync_purchase_orders_to_xero"
                        @click="syncPurchaseOrders"
                        :disabled="syncing"
                        class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ syncing === 'purchase-orders' ? 'Syncing...' : 'Sync Purchase Orders to Xero' }}
                    </button>
                </div>

                <!-- Initial Sync from Xero -->
                <div v-if="settings.tenant_name" class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">Initial Sync from Xero</h3>
                    <p class="text-sm text-blue-700 mb-4">
                        Import existing customers, products, suppliers, quotes, tax rates, bank accounts, and chart of accounts from your Xero account to get started.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <button
                            v-if="form.sync_customers_from_xero"
                            @click="syncCustomersFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'customers-from-xero' ? 'Importing...' : 'Import Customers from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_customers_from_xero"
                            @click="resyncCustomersFromXero"
                            :disabled="syncing"
                            class="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ syncing === 'customers-resync-from-xero' ? 'Resyncing...' : 'Resync All Customers from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_products_from_xero"
                            @click="syncProductsFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'products-from-xero' ? 'Importing...' : 'Import Products from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_suppliers_from_xero"
                            @click="syncSuppliersFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'suppliers-from-xero' ? 'Importing...' : 'Import Suppliers from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_quotes_from_xero"
                            @click="syncQuotesFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'quotes-from-xero' ? 'Importing...' : 'Import Quotes from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_invoices_from_xero"
                            @click="syncInvoicesFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'invoices-from-xero' ? 'Importing...' : 'Import Invoices from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_credit_notes_from_xero"
                            @click="syncCreditNotesFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'credit-notes-from-xero' ? 'Importing...' : 'Import Credit Notes from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_purchase_orders_from_xero"
                            @click="syncPurchaseOrdersFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'purchase-orders-from-xero' ? 'Importing...' : 'Import Purchase Orders from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_tax_rates_from_xero"
                            @click="syncTaxRatesFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'tax-rates-from-xero' ? 'Importing...' : 'Import Tax Rates from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_bank_accounts_from_xero"
                            @click="syncBankAccountsFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'bank-accounts-from-xero' ? 'Importing...' : 'Import Bank Accounts from Xero' }}
                        </button>
                        <button
                            v-if="form.sync_chart_of_accounts_from_xero"
                            @click="syncChartOfAccountsFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'chart-of-accounts-from-xero' ? 'Importing...' : 'Import Chart of Accounts from Xero' }}
                        </button>
                    </div>

                    <div class="mt-6 border-t border-blue-200 pt-4">
                        <h4 class="text-sm font-medium text-blue-900 mb-2">Reset Initial Sync Status</h4>
                        <p class="text-sm text-blue-700 mb-3">
                            Use this if you want to re-run a module's initial import flow from scratch.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <button @click="resetInitialSyncStatus('customer', 'Customers')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-customer' ? 'Resetting...' : 'Reset Customers Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('product', 'Products')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-product' ? 'Resetting...' : 'Reset Products Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('supplier', 'Suppliers')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-supplier' ? 'Resetting...' : 'Reset Suppliers Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('quote', 'Quotes')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-quote' ? 'Resetting...' : 'Reset Quotes Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('invoice', 'Invoices')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-invoice' ? 'Resetting...' : 'Reset Invoices Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('credit_note', 'Credit Notes')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-credit_note' ? 'Resetting...' : 'Reset Credit Notes Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('purchase_order', 'Purchase Orders')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-purchase_order' ? 'Resetting...' : 'Reset Purchase Orders Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('tax_rate', 'Tax Rates')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-tax_rate' ? 'Resetting...' : 'Reset Tax Rates Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('bank_account', 'Bank Accounts')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-bank_account' ? 'Resetting...' : 'Reset Bank Accounts Initial Sync' }}
                            </button>
                            <button @click="resetInitialSyncStatus('chart_of_account', 'Chart of Accounts')" :disabled="syncing" class="bg-white border border-blue-300 text-blue-700 px-3 py-2 rounded hover:bg-blue-100 disabled:opacity-50 text-sm">
                                {{ syncing === 'reset-chart_of_account' ? 'Resetting...' : 'Reset Chart of Accounts Initial Sync' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

interface XeroTenant {
    tenantId: string;
    tenantName: string;
    tenantType: string;
}

interface Props {
    settings: {
        id: number;
        is_enabled: boolean;
        client_id: string | null;
        tenant_id: string | null;
        tenant_name: string | null;
        has_client_secret: boolean;
        has_access_token: boolean;
        has_refresh_token: boolean;
        is_connected: boolean;
        needs_reauthorization: boolean;
        sync_customers: boolean;
        sync_products: boolean;
        sync_invoices: boolean;
        sync_customers_to_xero: boolean;
        sync_customers_from_xero: boolean;
        sync_products_to_xero: boolean;
        sync_products_from_xero: boolean;
        sync_invoices_to_xero: boolean;
        sync_invoices_from_xero: boolean;
        sync_credit_notes_to_xero: boolean;
        sync_credit_notes_from_xero: boolean;
        sync_purchase_orders_to_xero: boolean;
        sync_purchase_orders_from_xero: boolean;
        sync_suppliers_to_xero: boolean;
        sync_suppliers_from_xero: boolean;
        sync_quotes_to_xero: boolean;
        sync_quotes_from_xero: boolean;
        sync_tax_rates_from_xero: boolean;
        sync_bank_accounts_from_xero: boolean;
        sync_chart_of_accounts_from_xero: boolean;
        payment_account_cash: string | null;
        payment_account_card: string | null;
        payment_account_eft: string | null;
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
    xeroTenants: XeroTenant[];
    xeroApiUsage: {
        minute_total: number;
        day_total: number;
        budget_per_minute: number;
        top_endpoints: Array<{
            endpoint: string;
            count: number;
        }>;
        captured_at: string | null;
    };
}

const props = defineProps<Props>();

const syncing = ref<string | null>(null);
const availableTenants = ref<XeroTenant[]>(props.xeroTenants || []);
const selectedTenantId = ref<string>(props.settings.tenant_id || '');
const changingTenant = ref(false);
const fetchingTenants = ref(false);
const xeroApiUsage = computed(() => props.xeroApiUsage);

const form = useForm({
    is_enabled: props.settings.is_enabled,
    client_id: props.settings.client_id || '',
    client_secret: '',
    sync_customers: props.settings.sync_customers,
    sync_products: props.settings.sync_products,
    sync_invoices: props.settings.sync_invoices,
    sync_customers_to_xero: props.settings.sync_customers_to_xero,
    sync_customers_from_xero: props.settings.sync_customers_from_xero,
    sync_products_to_xero: props.settings.sync_products_to_xero,
    sync_products_from_xero: props.settings.sync_products_from_xero,
    sync_invoices_to_xero: props.settings.sync_invoices_to_xero,
    sync_invoices_from_xero: props.settings.sync_invoices_from_xero,
    sync_credit_notes_to_xero: props.settings.sync_credit_notes_to_xero,
    sync_credit_notes_from_xero: props.settings.sync_credit_notes_from_xero,
    sync_purchase_orders_to_xero: props.settings.sync_purchase_orders_to_xero,
    sync_purchase_orders_from_xero: props.settings.sync_purchase_orders_from_xero,
    sync_suppliers_to_xero: props.settings.sync_suppliers_to_xero,
    sync_suppliers_from_xero: props.settings.sync_suppliers_from_xero,
    sync_quotes_to_xero: props.settings.sync_quotes_to_xero,
    sync_quotes_from_xero: props.settings.sync_quotes_from_xero,
    sync_tax_rates_from_xero: props.settings.sync_tax_rates_from_xero,
    sync_bank_accounts_from_xero: props.settings.sync_bank_accounts_from_xero,
    sync_chart_of_accounts_from_xero: props.settings.sync_chart_of_accounts_from_xero,
});

const oauthCredentialsReady = computed(() => {
    const id = (form.client_id || '').trim();
    return Boolean(id && (form.client_secret.trim() !== '' || props.settings.has_client_secret));
});

// Watch for prop changes and update form when company switches
watch(() => props.settings, (newSettings) => {
    form.is_enabled = newSettings.is_enabled;
    form.client_id = newSettings.client_id || '';
    form.client_secret = '';
    form.sync_customers = newSettings.sync_customers;
    form.sync_products = newSettings.sync_products;
    form.sync_invoices = newSettings.sync_invoices;
    form.sync_customers_to_xero = newSettings.sync_customers_to_xero;
    form.sync_customers_from_xero = newSettings.sync_customers_from_xero;
    form.sync_products_to_xero = newSettings.sync_products_to_xero;
    form.sync_products_from_xero = newSettings.sync_products_from_xero;
    form.sync_invoices_to_xero = newSettings.sync_invoices_to_xero;
    form.sync_invoices_from_xero = newSettings.sync_invoices_from_xero;
    form.sync_credit_notes_to_xero = newSettings.sync_credit_notes_to_xero;
    form.sync_credit_notes_from_xero = newSettings.sync_credit_notes_from_xero;
    form.sync_purchase_orders_to_xero = newSettings.sync_purchase_orders_to_xero;
    form.sync_purchase_orders_from_xero = newSettings.sync_purchase_orders_from_xero;
    form.sync_suppliers_to_xero = newSettings.sync_suppliers_to_xero;
    form.sync_suppliers_from_xero = newSettings.sync_suppliers_from_xero;
    form.sync_quotes_to_xero = newSettings.sync_quotes_to_xero;
    form.sync_quotes_from_xero = newSettings.sync_quotes_from_xero;
    form.sync_tax_rates_from_xero = newSettings.sync_tax_rates_from_xero;
    form.sync_bank_accounts_from_xero = newSettings.sync_bank_accounts_from_xero;
    form.sync_chart_of_accounts_from_xero = newSettings.sync_chart_of_accounts_from_xero;
}, { deep: true });

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
    form.put('/administration/xero-settings');
};

const authorize = () => {
    window.location.href = '/xero/authorize';
};

const disconnect = () => {
    if (confirm('Are you sure you want to disconnect from Xero?')) {
        form.delete('/xero/disconnect');
    }
};

const selectedTenant = computed(() =>
    availableTenants.value.find(t => t.tenantId === selectedTenantId.value)
);

const selectTenant = () => {
    if (!selectedTenant.value) return;
    const tenantForm = useForm({
        tenant_id: selectedTenant.value.tenantId,
        tenant_name: selectedTenant.value.tenantName,
    });
    tenantForm.post('/xero/select-tenant', {
        onSuccess: () => {
            availableTenants.value = [];
            changingTenant.value = false;
        },
    });
};

const fetchTenants = () => {
    fetchingTenants.value = true;
    changingTenant.value = true;
    const fetchForm = useForm({});
    fetchForm.post('/xero/fetch-tenants', {
        onFinish: () => {
            fetchingTenants.value = false;
        },
    });
};

watch(() => props.xeroTenants, (newTenants) => {
    if (newTenants && newTenants.length) {
        availableTenants.value = newTenants;
    }
}, { immediate: false });

const syncCustomers = () => {
    syncing.value = 'customers';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/customers', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncProducts = () => {
    syncing.value = 'products';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/products', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncCustomersFromXero = () => {
    syncing.value = 'customers-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/customers-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const resyncCustomersFromXero = () => {
    if (!confirm('Resync all customers from Xero? This will ignore last modified dates and refresh all customer records in JobCardOnline.')) {
        return;
    }

    syncing.value = 'customers-resync-from-xero';

    const syncForm = useForm({});
    syncForm.post('/xero/sync/customers-from-xero/resync', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncProductsFromXero = () => {
    syncing.value = 'products-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/products-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncSuppliers = () => {
    syncing.value = 'suppliers';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/suppliers', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncSuppliersFromXero = () => {
    syncing.value = 'suppliers-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/suppliers-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncQuotes = () => {
    syncing.value = 'quotes';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/quotes', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncQuotesFromXero = () => {
    syncing.value = 'quotes-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/quotes-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncInvoices = () => {
    syncing.value = 'invoices';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/invoices', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncInvoicesFromXero = () => {
    syncing.value = 'invoices-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/invoices-from-xero', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncCreditNotes = () => {
    syncing.value = 'credit-notes';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/credit-notes', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncCreditNotesFromXero = () => {
    syncing.value = 'credit-notes-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/credit-notes-from-xero', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncPurchaseOrders = () => {
    syncing.value = 'purchase-orders';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/purchase-orders', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncPurchaseOrdersFromXero = () => {
    syncing.value = 'purchase-orders-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/purchase-orders-from-xero', {
        onFinish: () => { syncing.value = null; }
    });
};

const syncTaxRatesFromXero = () => {
    syncing.value = 'tax-rates-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/tax-rates-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncBankAccountsFromXero = () => {
    syncing.value = 'bank-accounts-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/bank-accounts-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const syncChartOfAccountsFromXero = () => {
    syncing.value = 'chart-of-accounts-from-xero';
    
    const syncForm = useForm({});
    syncForm.post('/xero/sync/chart-of-accounts-from-xero', {
        onSuccess: () => {
            syncing.value = null;
        },
        onError: () => {
            syncing.value = null;
        },
        onFinish: () => {
            syncing.value = null;
        }
    });
};

const switchCompany = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const companyId = parseInt(target.value);
    
    const switchForm = useForm({
        company_id: companyId
    });
    
    switchForm.post('/xero/switch-company');
};

const resetInitialSyncStatus = (module: string, label: string) => {
    if (!confirm(`Reset initial sync status for ${label}?`)) {
        return;
    }

    syncing.value = `reset-${module}`;
    const resetForm = useForm({ module });
    resetForm.post('/xero/reset-initial-sync-status', {
        onFinish: () => {
            syncing.value = null;
        },
    });
};

</script>
