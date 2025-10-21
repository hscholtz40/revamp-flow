<template>
    <AppLayout>
        <div class="space-y-6">
            <!-- Flash Messages -->
            <div v-if="$page.props.flash?.success" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.warning" class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded">
                {{ $page.props.flash.warning }}
            </div>
            <div v-if="$page.props.flash?.info" class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                {{ $page.props.flash.info }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                {{ $page.props.flash.error }}
            </div>
            
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
            <div v-if="settings.tenant_name && !settings.refresh_token && !settings.needs_reauthorization" class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded">
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
                                    placeholder="Enter Xero Client Secret"
                                />
                                <div v-if="form.errors.client_secret" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.client_secret }}
                                </div>
                            </div>
                        </div>

                        <!-- Authorization Section -->
                        <div v-if="form.client_id && form.client_secret" class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="text-sm font-medium text-blue-900">Authorization Required</h4>
                                    <p class="text-sm text-blue-700 mt-1">
                                        Click "Authorize with Xero" to connect your Xero account.
                                    </p>
                                </div>
                                <button
                                    v-if="!settings.tenant_name"
                                    type="button"
                                    @click="authorize"
                                    class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
                                >
                                    Authorize with Xero
                                </button>
                                <div v-else class="text-right">
                                    <p class="text-sm font-medium text-green-700">Connected</p>
                                    <p class="text-xs text-green-600">{{ settings.tenant_name }}</p>
                                    <button
                                        v-if="!settings.refresh_token"
                                        type="button"
                                        @click="authorize"
                                        class="mt-2 text-xs bg-orange-600 text-white px-2 py-1 rounded hover:bg-orange-700"
                                    >
                                        Re-authorize (Missing Refresh Token)
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Disconnect Button -->
                        <div v-if="settings.tenant_name" class="flex justify-end">
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
                            </div>
                        </div>
                    </div>

                    <!-- Payment Account Configuration -->
                    <div v-if="form.is_enabled && settings.tenant_name" class="space-y-4 border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900">Payment Account Configuration</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Configure which Xero accounts to use for different payment methods. These accounts must exist in your Xero organization and be suitable for receiving payments.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cash Payments Account</label>
                                <input
                                    v-model="form.payment_account_cash"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.payment_account_cash }"
                                    placeholder="e.g., 090"
                                />
                                <div v-if="form.errors.payment_account_cash" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.payment_account_cash }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Account code for cash payments</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Card Payments Account</label>
                                <input
                                    v-model="form.payment_account_card"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.payment_account_card }"
                                    placeholder="e.g., 091"
                                />
                                <div v-if="form.errors.payment_account_card" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.payment_account_card }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Account code for card payments</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">EFT/Bank Transfer Account</label>
                                <input
                                    v-model="form.payment_account_eft"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    :class="{ 'border-red-500': form.errors.payment_account_eft }"
                                    placeholder="e.g., 092"
                                />
                                <div v-if="form.errors.payment_account_eft" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.payment_account_eft }}
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Account code for EFT/bank transfer payments</p>
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
                </div>

                <!-- Initial Sync from Xero -->
                <div v-if="settings.tenant_name" class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="text-lg font-medium text-blue-900 mb-3">Initial Sync from Xero</h3>
                    <p class="text-sm text-blue-700 mb-4">
                        Import existing customers and products from your Xero account to get started.
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
                            v-if="form.sync_products_from_xero"
                            @click="syncProductsFromXero"
                            :disabled="syncing"
                            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ syncing === 'products-from-xero' ? 'Importing...' : 'Import Products from Xero' }}
                        </button>
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

interface Props {
    settings: {
        id: number;
        is_enabled: boolean;
        client_id: string | null;
        client_secret: string | null;
        tenant_name: string | null;
        refresh_token: string | null;
        needs_reauthorization: boolean;
        sync_customers: boolean;
        sync_products: boolean;
        sync_invoices: boolean;
        sync_customers_to_xero: boolean;
        sync_customers_from_xero: boolean;
        sync_products_to_xero: boolean;
        sync_products_from_xero: boolean;
        sync_invoices_to_xero: boolean;
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
}

const props = defineProps<Props>();

const syncing = ref<string | null>(null);

const form = useForm({
    is_enabled: props.settings.is_enabled,
    client_id: props.settings.client_id || '',
    client_secret: props.settings.client_secret || '',
    sync_customers: props.settings.sync_customers,
    sync_products: props.settings.sync_products,
    sync_invoices: props.settings.sync_invoices,
    sync_customers_to_xero: props.settings.sync_customers_to_xero,
    sync_customers_from_xero: props.settings.sync_customers_from_xero,
    sync_products_to_xero: props.settings.sync_products_to_xero,
    sync_products_from_xero: props.settings.sync_products_from_xero,
    sync_invoices_to_xero: props.settings.sync_invoices_to_xero,
    payment_account_cash: props.settings.payment_account_cash || '',
    payment_account_card: props.settings.payment_account_card || '',
    payment_account_eft: props.settings.payment_account_eft || '',
});

// Watch for prop changes and update form when company switches
watch(() => props.settings, (newSettings) => {
    form.is_enabled = newSettings.is_enabled;
    form.client_id = newSettings.client_id || '';
    form.client_secret = newSettings.client_secret || '';
    form.sync_customers = newSettings.sync_customers;
    form.sync_products = newSettings.sync_products;
    form.sync_invoices = newSettings.sync_invoices;
    form.sync_customers_to_xero = newSettings.sync_customers_to_xero;
    form.sync_customers_from_xero = newSettings.sync_customers_from_xero;
    form.sync_products_to_xero = newSettings.sync_products_to_xero;
    form.sync_products_from_xero = newSettings.sync_products_from_xero;
    form.sync_invoices_to_xero = newSettings.sync_invoices_to_xero;
    form.payment_account_cash = newSettings.payment_account_cash || '';
    form.payment_account_card = newSettings.payment_account_card || '';
    form.payment_account_eft = newSettings.payment_account_eft || '';
}, { deep: true });

const statusText = computed(() => {
    if (!form.is_enabled) return 'Disabled';
    if (!props.settings.tenant_name) return 'Not Connected';
    return 'Connected';
});

const statusClass = computed(() => {
    if (!form.is_enabled) return 'bg-gray-100 text-gray-800';
    if (!props.settings.tenant_name) return 'bg-yellow-100 text-yellow-800';
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

const switchCompany = (event: Event) => {
    const target = event.target as HTMLSelectElement;
    const companyId = parseInt(target.value);
    
    const switchForm = useForm({
        company_id: companyId
    });
    
    switchForm.post('/xero/switch-company');
};

</script>
