<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import companySettings from '@/routes/company-settings';
import { Plus, Edit, Trash2, Eye, Building2, Star } from 'lucide-vue-next';

interface Company {
    id: number;
    name: string;
    email: string;
    phone: string;
    address: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    website: string;
    logo_path: string;
    description: string;
    is_active: boolean;
    is_default: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    companies: Company[];
    currentCompany: Company;
}

const props = defineProps<Props>();

function deleteCompany(company: Company) {
    if (confirm(`Are you sure you want to delete "${company.name}"?`)) {
        router.delete(companySettings.destroy(company.id).url);
    }
}

function setAsDefault(company: Company) {
    router.post(companySettings.setDefault(company.id).url);
}

function switchToCompany(company: Company) {
    router.post(companySettings.switch(company.id).url);
}
</script>

<template>
    <Head title="Company Settings" />
    
    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Company Settings', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Company Settings</h1>
                    <p class="text-gray-600">Manage your companies and organization information</p>
                    <div v-if="props.currentCompany" class="mt-2">
                        <span class="text-sm text-gray-500">Current Company: </span>
                        <span class="text-sm font-medium text-blue-600">{{ props.currentCompany.name }}</span>
                    </div>
                    <div v-else class="mt-2">
                        <span class="text-sm text-red-600">No accessible companies found</span>
                    </div>
                </div>
                <Link
                    :href="companySettings.create().url"
                    class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                >
                    <Plus class="h-4 w-4" />
                    Add Company
                </Link>
            </div>

            <!-- Companies List -->
            <div v-if="props.companies.length === 0" class="rounded-lg border bg-white p-8 text-center">
                <Building2 class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-lg font-medium text-gray-900">No accessible companies</h3>
                <p class="mt-1 text-gray-500">You don't have access to any companies or no companies are active.</p>
                <p class="mt-2 text-sm text-gray-400">Contact an administrator to get access to companies.</p>
                <div class="mt-6">
                    <Link
                        :href="companySettings.create().url"
                        class="inline-flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
                    >
                        <Plus class="h-4 w-4" />
                        Add Company
                    </Link>
                </div>
            </div>

            <div v-else class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="company in props.companies"
                    :key="company.id"
                    class="rounded-lg border bg-white p-6 shadow-sm hover:shadow-md transition-shadow"
                >
                    <!-- Company Header -->
                    <div class="mb-4 flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100">
                                <Building2 class="h-6 w-6 text-blue-600" />
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-gray-900">{{ company.name }}</h3>
                                    <Star v-if="company.is_default" class="h-4 w-4 text-yellow-500 fill-current" />
                                </div>
                                <p class="text-sm text-gray-500">{{ company.city }}, {{ company.country }}</p>
                            </div>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-2 py-1 text-xs font-medium',
                                company.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            ]"
                        >
                            {{ company.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <!-- Company Details -->
                    <div class="mb-4 space-y-2 text-sm text-gray-600">
                        <div v-if="company.email" class="flex items-center gap-2">
                            <span class="font-medium">Email:</span>
                            <span>{{ company.email }}</span>
                        </div>
                        <div v-if="company.phone" class="flex items-center gap-2">
                            <span class="font-medium">Phone:</span>
                            <span>{{ company.phone }}</span>
                        </div>
                        <div v-if="company.website" class="flex items-center gap-2">
                            <span class="font-medium">Website:</span>
                            <a :href="company.website" target="_blank" class="text-blue-600 hover:underline">
                                {{ company.website }}
                            </a>
                        </div>
                    </div>

                    <!-- Description -->
                    <p v-if="company.description" class="mb-4 text-sm text-gray-600 line-clamp-2">
                        {{ company.description }}
                    </p>

                    <!-- Actions -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="props.currentCompany && props.currentCompany.id !== company.id"
                            @click="switchToCompany(company)"
                            class="flex items-center gap-1 rounded border border-blue-300 px-3 py-1 text-sm text-blue-700 hover:bg-blue-50"
                        >
                            <Building2 class="h-3 w-3" />
                            Switch To
                        </button>
                        <Link
                            :href="companySettings.show(company.id).url"
                            class="flex items-center gap-1 rounded border px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <Eye class="h-3 w-3" />
                            View
                        </Link>
                        <Link
                            :href="companySettings.edit(company.id).url"
                            class="flex items-center gap-1 rounded border px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                        >
                            <Edit class="h-3 w-3" />
                            Edit
                        </Link>
                        <button
                            v-if="!company.is_default"
                            @click="setAsDefault(company)"
                            class="flex items-center gap-1 rounded border border-yellow-300 px-3 py-1 text-sm text-yellow-700 hover:bg-yellow-50"
                        >
                            <Star class="h-3 w-3" />
                            Set Default
                        </button>
                        <button
                            @click="deleteCompany(company)"
                            class="flex items-center gap-1 rounded border border-red-300 px-3 py-1 text-sm text-red-700 hover:bg-red-50"
                        >
                            <Trash2 class="h-3 w-3" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
