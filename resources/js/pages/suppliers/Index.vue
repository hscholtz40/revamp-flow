<script setup lang="ts">
import ListTableActionLabel from '@/components/ListTableActionLabel.vue';
import { useAuthAbility } from '@/composables/useAuthAbilities';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Plus, Search, Building2, Edit, Trash2, Phone, Mail } from 'lucide-vue-next';
import suppliers from '@/routes/suppliers';

interface Supplier {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    vat_number: string | null;
    is_active: boolean;
    created_at: string;
}

interface Props {
    suppliers?: {
        data: Supplier[];
        links: any[];
        meta: any;
    };
    filters?: {
        search?: string;
        active_only?: boolean;
        sort_by?: string;
        sort_dir?: 'asc' | 'desc';
    };
}

const props = withDefaults(defineProps<Props>(), {
    suppliers: () => ({ data: [], links: [], meta: {} }),
    filters: () => ({}),
});

const canSuppliersCreate = useAuthAbility('suppliers', 'create');
const canSuppliersEdit = useAuthAbility('suppliers', 'edit');
const canSuppliersDelete = useAuthAbility('suppliers', 'delete');

const search = ref(props.filters?.search || '');
const activeOnly = ref(props.filters?.active_only || false);
const sortBy = ref(props.filters?.sort_by || 'name');
const sortDir = ref(props.filters?.sort_dir || 'asc');

// Computed to check if suppliers exist
const hasSuppliers = computed(() => {
    return props.suppliers?.data && props.suppliers.data.length > 0;
});

// Alias props.suppliers to avoid conflict with imported suppliers route
const suppliersData = computed(() => props.suppliers);

// Computed properties for pagination info
const paginationFrom = computed(() => {
    if (props.suppliers?.meta?.from !== undefined) return props.suppliers.meta.from;
    if (props.suppliers?.from !== undefined) return props.suppliers.from;
    return props.suppliers?.data?.length > 0 ? 1 : 0;
});

const paginationTo = computed(() => {
    if (props.suppliers?.meta?.to !== undefined) return props.suppliers.meta.to;
    if (props.suppliers?.to !== undefined) return props.suppliers.to;
    return props.suppliers?.data?.length ?? 0;
});

const paginationTotal = computed(() => {
    if (props.suppliers?.meta?.total !== undefined && props.suppliers.meta.total > 0) return props.suppliers.meta.total;
    if (props.suppliers?.total !== undefined && props.suppliers.total > 0) return props.suppliers.total;
    // If meta.total is 0 but we have data, use data length as fallback
    return props.suppliers?.data?.length ?? 0;
});

function applyFilters() {
    const params: Record<string, string | boolean> = {};
    
    if (search.value && search.value.trim()) params.search = search.value.trim();
    if (activeOnly.value) params.active_only = activeOnly.value;
    params.sort_by = sortBy.value;
    params.sort_dir = sortDir.value;
    
    router.get(suppliers.index().url, params, {
        preserveState: true,
        replace: true,
    });
}

function toggleSort(field: string) {
    if (sortBy.value === field) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortBy.value = field;
        sortDir.value = 'asc';
    }
    applyFilters();
}

function sortIndicator(field: string) {
    if (sortBy.value !== field) return '↕';
    return sortDir.value === 'asc' ? '↑' : '↓';
}

function clearFilters() {
    search.value = '';
    activeOnly.value = false;
    applyFilters();
}

function deleteSupplier(supplier: Supplier) {
    if (confirm(`Are you sure you want to delete "${supplier.name}"?`)) {
        router.delete(suppliers.destroy(supplier.id).url);
    }
}
</script>

<template>
    <Head title="Suppliers" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Suppliers</h1>
                    <p class="text-gray-600">Manage your suppliers and vendors</p>
                </div>
                <Link
                    v-if="canSuppliersCreate"
                    :href="suppliers.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Supplier
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
                                placeholder="Search suppliers..."
                                class="w-full rounded border border-gray-300 pl-10 pr-4 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            <input
                                v-model="activeOnly"
                                type="checkbox"
                                class="mr-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                @change="applyFilters"
                            />
                            Active Only
                        </label>
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

            <!-- Suppliers Table -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"><button type="button" @click="toggleSort('name')" class="inline-flex items-center gap-1 hover:text-gray-700">Name {{ sortIndicator('name') }}</button></th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"><button type="button" @click="toggleSort('email')" class="inline-flex items-center gap-1 hover:text-gray-700">Contact {{ sortIndicator('email') }}</button></th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"><button type="button" @click="toggleSort('city')" class="inline-flex items-center gap-1 hover:text-gray-700">Location {{ sortIndicator('city') }}</button></th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"><button type="button" @click="toggleSort('vat_number')" class="inline-flex items-center gap-1 hover:text-gray-700">VAT Number {{ sortIndicator('vat_number') }}</button></th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"><button type="button" @click="toggleSort('is_active')" class="inline-flex items-center gap-1 hover:text-gray-700">Status {{ sortIndicator('is_active') }}</button></th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <template v-if="hasSuppliers">
                            <tr
                                v-for="supplier in suppliersData.data"
                                :key="supplier.id"
                                class="cursor-pointer hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
                                tabindex="0"
                                role="link"
                                @click="router.visit(suppliers.show(supplier.id).url)"
                                @keydown.enter.prevent="router.visit(suppliers.show(supplier.id).url)"
                                @keydown.space.prevent="router.visit(suppliers.show(supplier.id).url)"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex items-center">
                                        <Building2 class="mr-2 h-5 w-5 text-gray-400" />
                                        <div>
                                            <div class="font-medium text-gray-900">{{ supplier.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <div v-if="supplier.email" class="flex items-center gap-1">
                                        <Mail class="h-4 w-4" />
                                        {{ supplier.email }}
                                    </div>
                                    <div v-if="supplier.phone" class="flex items-center gap-1 mt-1">
                                        <Phone class="h-4 w-4" />
                                        {{ supplier.phone }}
                                    </div>
                                    <div v-if="!supplier.email && !supplier.phone" class="text-gray-400">—</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    <div v-if="supplier.city || supplier.country">
                                        {{ [supplier.city, supplier.country].filter(Boolean).join(', ') }}
                                    </div>
                                    <div v-else class="text-gray-400">—</div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ supplier.vat_number || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span
                                        :class="supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'"
                                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                                    >
                                        {{ supplier.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium" @click.stop>
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            v-if="canSuppliersEdit"
                                            :href="suppliers.edit(supplier.id).url"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                        >
                                            <ListTableActionLabel label="Edit">
                                                <Edit class="h-4 w-4" />
                                            </ListTableActionLabel>
                                        </Link>
                                        <button
                                            v-if="canSuppliersDelete"
                                            type="button"
                                            @click="deleteSupplier(supplier)"
                                            class="inline-flex items-center justify-center px-2 py-1.5 md:px-3 md:py-1 border border-transparent text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
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
                            <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                                No suppliers found.
                                <Link
                                    v-if="canSuppliersCreate"
                                    :href="suppliers.create().url"
                                    class="text-blue-600 hover:text-blue-900"
                                >
                                    Create your first supplier
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="props.suppliers?.links" class="mt-4 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing {{ paginationFrom }} to {{ paginationTo }} of {{ paginationTotal }} suppliers
                </div>
                <div v-if="props.suppliers.links.length > 0" class="flex gap-2">
                    <Link
                        v-for="link in props.suppliers.links"
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

