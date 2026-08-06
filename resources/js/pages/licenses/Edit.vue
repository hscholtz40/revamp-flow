<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft } from 'lucide-vue-next';
import licenses from '@/routes/licenses';

interface Customer {
    id: number;
    name: string;
    email: string | null;
}

interface License {
    id: number;
    license_key: string;
    url: string | null;
    latitude: string | number | null;
    longitude: string | number | null;
    location_address: string | null;
    customer_id: number;
    limited_users: number;
    standard_users: number;
    status: string;
    notes: string | null;
    expires_at: string | null;
    billing_cycle: string | null;
    pricing_model: string | null;
    price_standard_monthly: string | number | null;
    price_limited_monthly: string | number | null;
    price_standard_annual: string | number | null;
    price_limited_annual: string | number | null;
    fixed_amount_monthly: string | number | null;
    fixed_amount_annual: string | number | null;
    auto_email_invoice: boolean;
    customer: Customer | null;
}

interface Props {
    license: License;
    customers: Customer[];
    canViewFullLicenseKey?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canViewFullLicenseKey: false,
});

const pageTitle = computed(() => {
    if (props.canViewFullLicenseKey) {
        return `Edit License - ${props.license.license_key}`;
    }
    const suffix = props.license.customer?.name ? ` — ${props.license.customer.name}` : '';
    return `Edit License #${props.license.id}${suffix}`;
});

const breadcrumbKeyLabel = computed(() => {
    if (props.canViewFullLicenseKey) {
        return props.license.license_key;
    }
    return props.license.customer?.name ?? `License #${props.license.id}`;
});

const form = useForm({
    customer_id: props.license.customer_id,
    url: props.license.url || '',
    latitude: props.license.latitude ?? '',
    longitude: props.license.longitude ?? '',
    location_address: props.license.location_address || '',
    limited_users: props.license.limited_users,
    standard_users: props.license.standard_users,
    status: props.license.status,
    notes: props.license.notes || '',
    expires_at: props.license.expires_at ? props.license.expires_at.substring(0, 10) : '',
    billing_cycle: (props.license.billing_cycle || '') as '' | 'monthly' | 'annual',
    pricing_model: (props.license.pricing_model || '') as '' | 'per_user' | 'fixed',
    price_standard_monthly: props.license.price_standard_monthly ?? '',
    price_limited_monthly: props.license.price_limited_monthly ?? '',
    price_standard_annual: props.license.price_standard_annual ?? '',
    price_limited_annual: props.license.price_limited_annual ?? '',
    fixed_amount_monthly: props.license.fixed_amount_monthly ?? '',
    fixed_amount_annual: props.license.fixed_amount_annual ?? '',
    auto_email_invoice: props.license.auto_email_invoice ?? true,
});

function submit() {
    form.put(licenses.update(props.license.id).url);
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout :breadcrumbs="[
        { title: 'Licenses', href: licenses.index().url },
        { title: breadcrumbKeyLabel, href: licenses.show(props.license.id).url },
        { title: 'Edit', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="licenses.show(props.license.id).url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to License
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Edit License</h1>
                <p class="mt-1 font-mono text-sm text-gray-500">{{ props.license.license_key }}</p>
                <p v-if="!canViewFullLicenseKey" class="mt-1 text-sm text-gray-500">
                    Full license keys are visible only to administrators.
                </p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Customer Selection -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Customer</h2>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Select Customer <span class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="form.customer_id"
                                required
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.customer_id }"
                            >
                                <option value="">— Select a customer —</option>
                                <option v-for="customer in props.customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}{{ customer.email ? ` (${customer.email})` : '' }}
                                </option>
                            </select>
                            <div v-if="form.errors.customer_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.customer_id }}
                            </div>
                        </div>
                    </div>

                    <!-- Instance URL -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Instance URL</h2>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">
                                Authorized URL
                            </label>
                            <input
                                v-model="form.url"
                                type="url"
                                placeholder="https://customer-instance.example.com"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                :class="{ 'border-red-500': form.errors.url }"
                            />
                            <p class="mt-1 text-xs text-gray-500">The URL of the instance authorized to use this license. Validation requests must originate from this URL.</p>
                            <div v-if="form.errors.url" class="mt-1 text-sm text-red-600">
                                {{ form.errors.url }}
                            </div>
                        </div>
                    </div>

                    <!-- Contractor Location -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Contractor Location</h2>
                        <p class="mb-4 text-xs text-gray-500">Used to match this contractor to nearby jobs by proximity. Enter coordinates (and optionally a readable address).</p>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Latitude</label>
                                <input
                                    v-model="form.latitude"
                                    type="number"
                                    step="any"
                                    placeholder="-26.2041"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.latitude }"
                                />
                                <div v-if="form.errors.latitude" class="mt-1 text-sm text-red-600">{{ form.errors.latitude }}</div>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Longitude</label>
                                <input
                                    v-model="form.longitude"
                                    type="number"
                                    step="any"
                                    placeholder="28.0473"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.longitude }"
                                />
                                <div v-if="form.errors.longitude" class="mt-1 text-sm text-red-600">{{ form.errors.longitude }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                                <input
                                    v-model="form.location_address"
                                    type="text"
                                    placeholder="123 Main Rd, Johannesburg"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.location_address }"
                                />
                                <div v-if="form.errors.location_address" class="mt-1 text-sm text-red-600">{{ form.errors.location_address }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- User Limits -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">User Limits</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Limited Users <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model.number="form.limited_users"
                                    type="number"
                                    min="0"
                                    required
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.limited_users }"
                                />
                                <div v-if="form.errors.limited_users" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.limited_users }}
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Standard Users <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model.number="form.standard_users"
                                    type="number"
                                    min="0"
                                    required
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.standard_users }"
                                />
                                <div v-if="form.errors.standard_users" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.standard_users }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- License Settings -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">License Settings</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                                <select
                                    v-model="form.status"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="active">Active</option>
                                    <option value="suspended">Suspended</option>
                                    <option value="expired">Expired</option>
                                    <option value="revoked">Revoked</option>
                                </select>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Expiry Date</label>
                                <input
                                    v-model="form.expires_at"
                                    type="date"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Leave blank for no expiry.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Billing -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Billing</h2>
                        <p class="mb-4 text-xs text-gray-500">When billing is newly enabled, the first invoice is created on save. Recurring invoices recalculate from current seats and prices.</p>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Billing Cycle</label>
                                <select
                                    v-model="form.billing_cycle"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">No billing</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="annual">Annual</option>
                                </select>
                            </div>
                            <div v-if="form.billing_cycle">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Pricing Model</label>
                                <select
                                    v-model="form.pricing_model"
                                    required
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">— Select —</option>
                                    <option value="per_user">Per user</option>
                                    <option value="fixed">Fixed amount</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="form.billing_cycle === 'monthly' && form.pricing_model === 'per_user'" class="mt-4 grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Standard user price (monthly)</label>
                                <input v-model="form.price_standard_monthly" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Limited user price (monthly)</label>
                                <input v-model="form.price_limited_monthly" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2" />
                            </div>
                        </div>
                        <div v-if="form.billing_cycle === 'annual' && form.pricing_model === 'per_user'" class="mt-4 grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Standard user price (annual)</label>
                                <input v-model="form.price_standard_annual" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Limited user price (annual)</label>
                                <input v-model="form.price_limited_annual" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2" />
                            </div>
                        </div>
                        <div v-if="form.billing_cycle === 'monthly' && form.pricing_model === 'fixed'" class="mt-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Fixed monthly amount</label>
                            <input v-model="form.fixed_amount_monthly" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2 md:max-w-xs" />
                        </div>
                        <div v-if="form.billing_cycle === 'annual' && form.pricing_model === 'fixed'" class="mt-4">
                            <label class="mb-1 block text-sm font-medium text-gray-700">Fixed annual amount</label>
                            <input v-model="form.fixed_amount_annual" type="number" min="0" step="0.01" class="w-full rounded border px-3 py-2 md:max-w-xs" />
                        </div>
                        <div v-if="form.billing_cycle" class="mt-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                <input v-model="form.auto_email_invoice" type="checkbox" class="rounded border-gray-300" />
                                Automatically email invoices to the customer
                            </label>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            placeholder="Optional notes about this license..."
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 border-t pt-6">
                        <Link
                            :href="licenses.show(props.license.id).url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
