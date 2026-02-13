<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Edit, Trash2, Copy, Check, Key, Users, Calendar, Clock, Globe, Rocket, ArrowUpCircle, Tag, ShieldCheck, Lock } from 'lucide-vue-next';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    version: string | null;
    deployed_at: string | null;
    customer_id: number;
    limited_users: number;
    standard_users: number;
    status: 'active' | 'suspended' | 'expired' | 'revoked';
    notes: string | null;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
    customer: Customer | null;
}

interface Props {
    license: License;
}

const props = defineProps<Props>();

const copied = ref(false);
const showDeployDialog = ref(false);
const showUpgradeDialog = ref(false);
const forcingSSL = ref(false);

const deployForm = useForm({
    zip_file: null as File | null,
});

const upgradeForm = useForm({
    zip_file: null as File | null,
});

function copyLicenseKey() {
    navigator.clipboard.writeText(props.license.license_key).then(() => {
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    });
}

function deleteLicense() {
    if (confirm('Are you sure you want to delete this license? This action cannot be undone.')) {
        router.delete(licenses.destroy(props.license.id).url);
    }
}

function onDeployFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        deployForm.zip_file = target.files[0];
    }
}

function onUpgradeFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        upgradeForm.zip_file = target.files[0];
    }
}

function submitDeploy() {
    if (!deployForm.zip_file) return;

    deployForm.post(`/licenses/${props.license.id}/deploy`, {
        forceFormData: true,
        onSuccess: () => {
            showDeployDialog.value = false;
            deployForm.reset();
        },
    });
}

function submitUpgrade() {
    if (!upgradeForm.zip_file) return;

    upgradeForm.post(`/licenses/${props.license.id}/upgrade`, {
        forceFormData: true,
        onSuccess: () => {
            showUpgradeDialog.value = false;
            upgradeForm.reset();
        },
    });
}

function forceSSL() {
    if (!confirm('This will request an AutoSSL certificate and enable HTTPS redirect. Continue?')) return;

    forcingSSL.value = true;
    router.post(`/licenses/${props.license.id}/force-ssl`, {}, {
        onFinish: () => {
            forcingSSL.value = false;
        },
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
        month: 'long',
        day: 'numeric',
    });
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleString('en-ZA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <Head :title="`License - ${props.license.license_key}`" />
    <AppLayout :breadcrumbs="[
        { title: 'Licenses', href: licenses.index().url },
        { title: props.license.license_key, href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="licenses.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Licenses
                </Link>
                <div class="flex items-start justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">License Details</h1>
                        <div class="mt-2 flex items-center gap-3">
                            <span
                                :class="statusBadgeClass(props.license.status)"
                                class="inline-flex rounded-full px-3 py-1 text-sm font-semibold capitalize"
                            >
                                {{ props.license.status }}
                            </span>
                            <span
                                v-if="props.license.deployed_at"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800"
                            >
                                <ShieldCheck class="h-3.5 w-3.5" />
                                Deployed
                            </span>
                            <span
                                v-else
                                class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm font-semibold text-gray-500"
                            >
                                Not Deployed
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            v-if="!props.license.deployed_at"
                            @click="showDeployDialog = true"
                            class="flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                        >
                            <Rocket class="h-4 w-4" />
                            Deploy
                        </button>
                        <button
                            v-if="props.license.deployed_at"
                            @click="showUpgradeDialog = true"
                            class="flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                        >
                            <ArrowUpCircle class="h-4 w-4" />
                            Upgrade
                        </button>
                        <button
                            v-if="props.license.deployed_at && props.license.url"
                            @click="forceSSL"
                            :disabled="forcingSSL"
                            class="flex items-center gap-2 rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Lock class="h-4 w-4" />
                            <span v-if="forcingSSL">Forcing SSL...</span>
                            <span v-else>Force SSL</span>
                        </button>
                        <Link
                            :href="licenses.edit(props.license.id).url"
                            class="flex items-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                        >
                            <Edit class="h-4 w-4" />
                            Edit
                        </Link>
                        <button
                            @click="deleteLicense"
                            class="flex items-center gap-2 rounded-md border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50"
                        >
                            <Trash2 class="h-4 w-4" />
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <!-- License Key Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Key class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">License Key</h2>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4">
                        <code class="flex-1 text-lg font-mono font-semibold text-gray-900 break-all">{{ props.license.license_key }}</code>
                        <button
                            @click="copyLicenseKey"
                            class="flex-shrink-0 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            <Check v-if="copied" class="h-4 w-4 text-green-500" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Customer Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Users class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">Customer</h2>
                    </div>
                    <div v-if="props.license.customer" class="space-y-2">
                        <p class="text-lg font-medium text-gray-900">{{ props.license.customer.name }}</p>
                        <p v-if="props.license.customer.email" class="text-sm text-gray-500">{{ props.license.customer.email }}</p>
                    </div>
                    <p v-else class="text-gray-400">No customer assigned</p>
                </div>

                <!-- Authorized URL Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Globe class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">Authorized URL</h2>
                    </div>
                    <div v-if="props.license.url" class="rounded-lg bg-gray-50 p-4">
                        <a :href="props.license.url" target="_blank" class="text-blue-600 hover:text-blue-800 break-all">
                            {{ props.license.url }}
                        </a>
                        <p class="mt-2 text-xs text-gray-500">Only validation requests from this URL will be accepted.</p>
                    </div>
                    <p v-else class="text-gray-400">No URL restriction — any instance can validate with this key.</p>
                </div>

                <!-- Version Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Tag class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">Version</h2>
                    </div>
                    <div v-if="props.license.version" class="rounded-lg bg-gray-50 p-4">
                        <p class="text-lg font-mono font-semibold text-gray-900">{{ props.license.version }}</p>
                    </div>
                    <p v-else class="text-gray-400">No version information available</p>
                </div>

                <!-- User Limits Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Users class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">User Limits</h2>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="rounded-lg bg-blue-50 p-4 text-center">
                            <p class="text-3xl font-bold text-blue-600">{{ props.license.limited_users }}</p>
                            <p class="mt-1 text-sm text-blue-800">Limited Users</p>
                        </div>
                        <div class="rounded-lg bg-indigo-50 p-4 text-center">
                            <p class="text-3xl font-bold text-indigo-600">{{ props.license.standard_users }}</p>
                            <p class="mt-1 text-sm text-indigo-800">Standard Users</p>
                        </div>
                    </div>
                </div>

                <!-- Dates Card -->
                <div class="rounded-lg border border-gray-200 bg-white p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <Calendar class="h-5 w-5 text-gray-500" />
                        <h2 class="text-lg font-semibold text-gray-900">Dates</h2>
                    </div>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Created</span>
                            <span class="text-sm font-medium text-gray-900">{{ formatDateTime(props.license.created_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Last Updated</span>
                            <span class="text-sm font-medium text-gray-900">{{ formatDateTime(props.license.updated_at) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Deployed</span>
                            <span class="text-sm font-medium" :class="props.license.deployed_at ? 'text-emerald-700' : 'text-gray-400'">
                                {{ props.license.deployed_at ? formatDateTime(props.license.deployed_at) : 'Not deployed' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Expires</span>
                            <span class="text-sm font-medium" :class="props.license.expires_at ? 'text-gray-900' : 'text-gray-400'">
                                {{ props.license.expires_at ? formatDate(props.license.expires_at) : 'Never' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div v-if="props.license.notes" class="mt-6 rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-3 text-lg font-semibold text-gray-900">Notes</h2>
                <p class="whitespace-pre-wrap text-sm text-gray-700">{{ props.license.notes }}</p>
            </div>
        </div>

        <!-- Deploy Dialog -->
        <Dialog v-model:open="showDeployDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Deploy Instance</DialogTitle>
                    <DialogDescription>
                        Upload a zip file to deploy a new instance to <strong>{{ props.license.url }}</strong>.
                        This will create the subdomain, database, and configure the application.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitDeploy" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Zip File <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="file"
                            accept=".zip"
                            @change="onDeployFileChange"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                        />
                        <p v-if="deployForm.errors.zip_file" class="mt-1 text-sm text-red-600">{{ deployForm.errors.zip_file }}</p>
                        <p class="mt-1 text-xs text-gray-500">Upload the application zip file (max 500MB)</p>
                    </div>

                    <div v-if="!props.license.url" class="rounded-md bg-yellow-50 p-3">
                        <p class="text-sm text-yellow-800">
                            Please set a URL on this license before deploying.
                        </p>
                    </div>

                    <DialogFooter>
                        <button
                            type="button"
                            @click="showDeployDialog = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!deployForm.zip_file || deployForm.processing || !props.license.url"
                            class="flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Rocket class="h-4 w-4" />
                            <span v-if="deployForm.processing">Deploying...</span>
                            <span v-else>Deploy</span>
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Upgrade Dialog -->
        <Dialog v-model:open="showUpgradeDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Upgrade Instance</DialogTitle>
                    <DialogDescription>
                        Upload a zip file to upgrade the instance at <strong>{{ props.license.url }}</strong>.
                        This will upload new files and run migrations.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitUpgrade" class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            Zip File <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="file"
                            accept=".zip"
                            @change="onUpgradeFileChange"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-amber-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-700 hover:file:bg-amber-100"
                        />
                        <p v-if="upgradeForm.errors.zip_file" class="mt-1 text-sm text-red-600">{{ upgradeForm.errors.zip_file }}</p>
                        <p class="mt-1 text-xs text-gray-500">Upload the updated application zip file (max 500MB)</p>
                    </div>

                    <div v-if="!props.license.url" class="rounded-md bg-yellow-50 p-3">
                        <p class="text-sm text-yellow-800">
                            Please set a URL on this license before upgrading.
                        </p>
                    </div>

                    <DialogFooter>
                        <button
                            type="button"
                            @click="showUpgradeDialog = false"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="!upgradeForm.zip_file || upgradeForm.processing || !props.license.url"
                            class="flex items-center gap-2 rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <ArrowUpCircle class="h-4 w-4" />
                            <span v-if="upgradeForm.processing">Upgrading...</span>
                            <span v-else>Upgrade</span>
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
