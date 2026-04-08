<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, Building2, Star, Calendar, Mail, Phone, Globe, MapPin } from 'lucide-vue-next';
import companySettings from '@/routes/company-settings';
import { computed } from 'vue';
import { getSafeExternalUrl } from '@/composables/useSafeExternalUrl';

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
    company: Company;
}

const props = defineProps<Props>();
const safeWebsiteUrl = computed(() => getSafeExternalUrl(props.company.website));
</script>

<template>
    <Head :title="props.company.name" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Company Settings', href: companySettings.index().url },
        { title: props.company.name, href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="companySettings.index().url"
                        class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        Back to Companies
                    </Link>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="companySettings.edit(props.company.id).url"
                        class="flex items-center gap-2 rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                    >
                        <Edit class="h-4 w-4" />
                        Edit
                    </Link>
                </div>
            </div>

            <div class="mx-auto max-w-4xl">
                <!-- Company Header -->
                <div class="mb-8 rounded-lg border bg-white p-6">
                    <div class="flex items-start gap-6">
                        <!-- Company Logo -->
                        <div class="flex-shrink-0">
                            <div class="flex h-20 w-20 items-center justify-center rounded-lg bg-blue-100">
                                <Building2 class="h-10 w-10 text-blue-600" />
                            </div>
                        </div>

                        <!-- Company Info -->
                        <div class="flex-1">
                            <div class="mb-2 flex items-center gap-3">
                                <h1 class="text-2xl font-bold text-gray-900">{{ props.company.name }}</h1>
                                <Star v-if="props.company.is_default" class="h-6 w-6 text-yellow-500 fill-current" />
                            </div>
                            
                            <div class="mb-4 flex items-center gap-4">
                                <span
                                    :class="[
                                        'rounded-full px-3 py-1 text-sm font-medium',
                                        props.company.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    ]"
                                >
                                    {{ props.company.is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <span v-if="props.company.is_default" class="rounded-full bg-yellow-100 px-3 py-1 text-sm font-medium text-yellow-800">
                                    Default Company
                                </span>
                            </div>
                            
                            <p v-if="props.company.description" class="text-gray-600">
                                {{ props.company.description }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Company Details Grid -->
                <div class="grid gap-6 lg:grid-cols-3">
                    <!-- Main Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Contact Information -->
                        <div class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Contact Information</h2>
                            
                            <div class="grid gap-4 md:grid-cols-2">
                                <div v-if="props.company.email" class="flex items-center gap-3">
                                    <Mail class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Email</div>
                                        <div class="text-gray-900">{{ props.company.email }}</div>
                                    </div>
                                </div>

                                <div v-if="props.company.phone" class="flex items-center gap-3">
                                    <Phone class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Phone</div>
                                        <div class="text-gray-900">{{ props.company.phone }}</div>
                                    </div>
                                </div>

                                <div v-if="safeWebsiteUrl" class="flex items-center gap-3">
                                    <Globe class="h-5 w-5 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Website</div>
                                        <a :href="safeWebsiteUrl" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">
                                            {{ safeWebsiteUrl }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div v-if="props.company.address || props.company.city" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Address Information</h2>
                            
                            <div class="flex items-start gap-3">
                                <MapPin class="h-5 w-5 text-gray-400 mt-0.5" />
                                <div class="text-gray-900">
                                    <div v-if="props.company.address">{{ props.company.address }}</div>
                                    <div v-if="props.company.city || props.company.state || props.company.postal_code">
                                        {{ [props.company.city, props.company.state, props.company.postal_code].filter(Boolean).join(', ') }}
                                    </div>
                                    <div v-if="props.company.country">{{ props.company.country }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div v-if="props.company.description" class="rounded-lg border bg-white p-6">
                            <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                            <div class="text-gray-700 whitespace-pre-wrap">{{ props.company.description }}</div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Stats -->
                        <div class="rounded-lg border bg-white p-6">
                            <h3 class="mb-4 text-lg font-semibold text-gray-900">Company Status</h3>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Status</span>
                                    <span
                                        :class="[
                                            'text-sm font-medium',
                                            props.company.is_active ? 'text-green-600' : 'text-red-600'
                                        ]"
                                    >
                                        {{ props.company.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-500">Default</span>
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ props.company.is_default ? 'Yes' : 'No' }}
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
                                            {{ new Date(props.company.created_at).toLocaleDateString() }}
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <Calendar class="h-4 w-4 text-gray-400" />
                                    <div>
                                        <div class="text-sm font-medium text-gray-500">Last Updated</div>
                                        <div class="text-sm text-gray-900">
                                            {{ new Date(props.company.updated_at).toLocaleDateString() }}
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
