<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Search, Key, Eye, Edit, Trash2, Copy, Check } from 'lucide-vue-next';
import licenses from '@/routes/licenses';

interface Customer {
    id: number;
    name: string;
}

interface License {
    id: number;
    license_key: string;
    url: string | null;
    limited_users: number;
    standard_users: number;
    deployed_at: string | null;
    status: 'active' | 'suspended' | 'expired' | 'revoked';
    notes: string | null;
    expires_at: string | null;
    created_at: string;
    customer: Customer | null;
}

interface Props {
    licenses?: {
        data: License[];
        links: any[];
        meta: any;
        from?: number;
        to?: number;
        total?: number;
    };
    filters?: {
        search?: string;
        status?: string;
        deployed?: string;
    };
    canViewFullLicenseKey?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    licenses: () => ({ data: [], links: [], meta: {} }),
    filters: () => ({}),
    canViewFullLicenseKey: false,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');
const deployedFilter = ref(props.filters?.deployed || '');
const copiedId = ref<number | null>(null);

const hasLicenses = computed(() => {
    return props.licenses?.data && props.licenses.data.length > 0;
});

const licensesData = computed(() => props.licenses);

const paginationFrom = computed(() => {
    if (props.licenses?.meta?.from !== undefined) return props.licenses.meta.from;
    if (props.licenses?.from !== undefined) return props.licenses.from;
    return props.licenses?.data?.length > 0 ? 1 : 0;
});

const paginationTo = computed(() => {
    if (props.licenses?.meta?.to !== undefined) return props.licenses.meta.to;
    if (props.licenses?.to !== undefined) return props.licenses.to;
    return props.licenses?.data?.length ?? 0;
});

const paginationTotal = computed(() => {
    if (props.licenses?.meta?.total !== undefined && props.licenses.meta.total > 0) return props.licenses.meta.total;
    if (props.licenses?.total !== undefined && props.licenses.total > 0) return props.licenses.total;
    return props.licenses?.data?.length ?? 0;
});

function applyFilters() {
    const params: Record<string, string> = {};
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (statusFilter.value) params.status = statusFilter.value;
    if (deployedFilter.value) params.deployed = deployedFilter.value;

    router.get(licenses.index().url, params, {
        preserveState: true,
        replace: true,
    });
}

function clearFilters() {
    search.value = '';
    statusFilter.value = '';
    deployedFilter.value = '';
    applyFilters();
}

function deleteLicense(license: License) {
    const label = props.canViewFullLicenseKey
        ? license.license_key
        : license.customer?.name ?? `license #${license.id}`;
    if (confirm(`Are you sure you want to delete this license (${label})?`)) {
        router.delete(licenses.destroy(license.id).url);
    }
}

function copyLicenseKey(license: License) {
    navigator.clipboard.writeText(license.license_key).then(() => {
        copiedId.value = license.id;
        setTimeout(() => {
            copiedId.value = null;
        }, 2000);
    });
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'active': return 'bg-green-100 text-green-800';
        case 'suspended': return 'bg-yellow-100 text-yellow-800';
        case 'expired': return 'bg-gray-100 text-gray-800';
        case 'revoked': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-ZA', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Licenses" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Licenses</h1>
                    <p class="text-gray-600">Manage license keys for your customers</p>
                </div>
                <Link
                    :href="licenses.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Create License
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4">
                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Search</label>
                        <div class="relative">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="canViewFullLicenseKey ? 'Search by key or customer...' : 'Search by customer...'"
                                class="w-full rounded border border-gray-300 pl-10 pr-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Deployment</label>
                        <select
                            v-model="deployedFilter"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All</option>
                            <option value="not_deployed">Not Deployed</option>
                            <option value="deployed">Deployed</option>
                        </select>
                    </div>
                    <div class="min-w-[150px]">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                        <select
                            v-model="statusFilter"
                            class="w-full rounded border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Statuses</option>
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="expired">Expired</option>
                            <option value="revoked">Revoked</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="applyFilters"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            Filter
                        </button>
                        <button
                            @click="clearFilters"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Licenses Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">License Key</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">URL</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Users</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Deployed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Expires</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template v-if="hasLicenses">
                            <tr
                                v-for="license in licensesData.data"
                                :key="license.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(licenses.show(license.id).url)"
                                @keydown.enter.prevent="router.visit(licenses.show(license.id).url)"
                                @keydown.space.prevent="router.visit(licenses.show(license.id).url)"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <Key class="h-4 w-4 text-gray-400" />
                                        <code class="text-sm font-mono text-gray-900">{{ license.license_key }}</code>
                                        <button
                                            v-if="canViewFullLicenseKey"
                                            type="button"
                                            @click.stop="copyLicenseKey(license)"
                                            class="text-gray-400 hover:text-gray-600"
                                            title="Copy license key"
                                        >
                                            <Check v-if="copiedId === license.id" class="h-4 w-4 text-green-500" />
                                            <Copy v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                    {{ license.customer?.name || '—' }}
                                </td>
                                <td class="max-w-[200px] truncate px-6 py-4 text-sm text-gray-500" :title="license.url || ''">
                                    {{ license.url || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <div class="flex flex-col">
                                        <span>{{ license.limited_users }} Limited</span>
                                        <span>{{ license.standard_users }} Standard</span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        :class="statusBadgeClass(license.status)"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold capitalize"
                                    >
                                        {{ license.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span
                                        v-if="license.deployed_at"
                                        class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800"
                                    >
                                        Deployed
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800"
                                    >
                                        Not Deployed
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ formatDate(license.expires_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ formatDate(license.created_at) }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="licenses.edit(license.id).url"
                                            class="inline-flex items-center justify-center rounded-md p-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 md:p-0 md:hover:bg-transparent hover:text-indigo-900"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            type="button"
                                            @click="deleteLicense(license)"
                                            class="inline-flex items-center justify-center rounded-md p-2 text-sm font-medium text-red-600 hover:bg-red-50 md:p-0 md:hover:bg-transparent hover:text-red-900"
                                        >
                                            <ListTableActionLabel label="Delete">
                                                <Trash2 class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-else>
                            <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">
                                No licenses found. <Link :href="licenses.create().url" class="text-blue-600 hover:text-blue-900">Create your first license</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="props.licenses?.links" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ paginationFrom }} to {{ paginationTo }} of {{ paginationTotal }} licenses
                </div>
                <div v-if="props.licenses.links.length > 0" class="flex gap-2">
                    <Link
                        v-for="link in props.licenses.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50',
                            !link.url ? 'cursor-not-allowed opacity-50' : ''
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
