<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShieldX, ArrowLeft } from 'lucide-vue-next';

const props = defineProps<{
    module: string;
    ability: string;
    message: string;
    user?: any;
    requestedUrl?: string;
}>();

// Convert module and ability to more user-friendly text
const getModuleDisplayName = (module: string) => {
    const moduleNames: Record<string, string> = {
        'customers': 'Customers',
        'users': 'Users',
        'groups': 'Groups',
        'contacts': 'Contacts',
        'products': 'Products & Services',
        'jobcards': 'Jobcards',
        'quotes': 'Quotes',
        'invoices': 'Invoices',
    };
    return moduleNames[module] || module.charAt(0).toUpperCase() + module.slice(1);
};

const getAbilityDisplayName = (ability: string) => {
    const abilityNames: Record<string, string> = {
        'list': 'view the list of',
        'view': 'view',
        'create': 'create',
        'edit': 'edit',
        'delete': 'delete',
        'edit_completed': 'edit completed',
        'edit_salesperson': 'edit salesperson',
    };
    return abilityNames[ability] || ability;
};

const moduleDisplayName = getModuleDisplayName(props.module);
const abilityDisplayName = getAbilityDisplayName(props.ability);
</script>

<template>
    <Head title="Access Denied" />

    <AppLayout>
        <div class="flex min-h-[60vh] items-center justify-center p-4">
            <div class="max-w-md text-center">
                <!-- Icon -->
                <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-red-100">
                    <ShieldX class="h-10 w-10 text-red-600" />
                </div>

                <!-- Title -->
                <h1 class="mb-4 text-2xl font-bold text-gray-900">Access Denied</h1>

                <!-- Message -->
                <p class="mb-6 text-gray-600">
                    You don't have permission to {{ abilityDisplayName }} {{ moduleDisplayName }}.
                </p>

                <!-- Additional Info -->
                <div class="mb-8 rounded-lg bg-yellow-50 border border-yellow-200 p-4">
                    <p class="text-sm text-yellow-800">
                        <strong>Need access?</strong> Contact your administrator to request permission for this feature.
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex flex-col gap-3 sm:flex-row sm:justify-center">
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Go to Dashboard
                    </Link>
                    
                    <button
                        @click="$inertia.reload()"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Try Again
                    </button>
                </div>

                <!-- Technical Details (for debugging) -->
                <details class="mt-8 text-left">
                    <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700">
                        Technical Details
                    </summary>
                    <div class="mt-2 rounded bg-gray-100 p-3 text-xs text-gray-600">
                        <p><strong>Module:</strong> {{ module }}</p>
                        <p><strong>Ability:</strong> {{ ability }}</p>
                        <p><strong>Message:</strong> {{ message }}</p>
                        <p v-if="requestedUrl"><strong>Requested URL:</strong> {{ requestedUrl }}</p>
                        <p v-if="user"><strong>User:</strong> {{ user.name }} ({{ user.email }})</p>
                    </div>
                </details>
            </div>
        </div>
    </AppLayout>
</template>
