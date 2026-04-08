<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import smsSettings from '@/routes/sms-settings';
import administration from '@/routes/administration';

interface SMSSettings {
    id?: number;
    bulksms_username: string;
    bulksms_sender_name?: string;
    is_active: boolean;
    has_bulksms_password?: boolean;
}

const props = defineProps<{
    settings: SMSSettings | null;
}>();

const form = useForm({
    bulksms_username: props.settings?.bulksms_username || '',
    bulksms_password: '',
    bulksms_sender_name: props.settings?.bulksms_sender_name || '',
    is_active: props.settings?.is_active ?? true,
});

const showPassword = ref(false);

const submit = () => {
    if (props.settings?.id) {
        form.put(smsSettings.update(props.settings.id).url);
    } else {
        form.post(smsSettings.store().url);
    }
};

const togglePasswordVisibility = () => {
    showPassword.value = !showPassword.value;
};

const handleActiveToggle = () => {
    if (!form.is_active) {
        // Clear fields when disabling SMS functionality
        form.bulksms_username = '';
        form.bulksms_password = '';
        form.bulksms_sender_name = '';
    }
};
</script>

<template>
    <Head title="SMS Settings" />
    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'SMS Settings', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">SMS Settings</h1>
                <p class="text-gray-600">Configure BulkSMS settings for sending SMS messages to customers.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BulkSMS Username
                            </label>
                            <input
                                v-model="form.bulksms_username"
                                type="text"
                                class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                                :disabled="!form.is_active"
                                placeholder="Your BulkSMS username"
                                :required="form.is_active"
                            />
                            <div v-if="form.errors.bulksms_username" class="text-sm text-red-600 mt-1">
                                {{ form.errors.bulksms_username }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BulkSMS Password
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.bulksms_password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="w-full rounded border px-3 py-2 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                                    :disabled="!form.is_active"
                                    :placeholder="settings?.has_bulksms_password ? 'Leave blank to keep current password' : 'Your BulkSMS password'"
                                    :required="form.is_active && (!settings?.id || !settings?.has_bulksms_password)"
                                    autocomplete="new-password"
                                />
                                <p v-if="form.is_active && settings?.has_bulksms_password" class="text-xs text-gray-500 mt-1">
                                    Leave blank to keep your current password.
                                </p>
                                <button
                                    type="button"
                                    @click="togglePasswordVisibility"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                >
                                    <span class="text-gray-400 hover:text-gray-600">
                                        {{ showPassword ? 'Hide' : 'Show' }}
                                    </span>
                                </button>
                            </div>
                            <div v-if="form.errors.bulksms_password" class="text-sm text-red-600 mt-1">
                                {{ form.errors.bulksms_password }}
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Sender Name (Optional)
                        </label>
                        <input
                            v-model="form.bulksms_sender_name"
                            type="text"
                            class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                            :disabled="!form.is_active"
                            placeholder="Company Name"
                            maxlength="11"
                        />
                        <p class="text-sm text-gray-500 mt-1">Maximum 11 characters. This will appear as the sender name in SMS messages.</p>
                        <div v-if="form.errors.bulksms_sender_name" class="text-sm text-red-600 mt-1">
                            {{ form.errors.bulksms_sender_name }}
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input
                            v-model="form.is_active"
                            @change="handleActiveToggle"
                            type="checkbox"
                            id="is_active"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Enable SMS functionality
                        </label>
                    </div>

                    <div class="flex items-center justify-between pt-6 border-t">
                        <div class="text-sm text-gray-500">
                            <p v-if="!settings" class="text-amber-600">
                                <strong>Note:</strong> SMS functionality is not configured. Configure these settings to enable SMS sending.
                            </p>
                            <p v-else-if="!settings.is_active" class="text-red-600">
                                <strong>Note:</strong> SMS functionality is currently disabled.
                            </p>
                            <p v-else class="text-green-600">
                                <strong>Status:</strong> SMS functionality is active and configured.
                            </p>
                        </div>
                        
                        <div class="flex gap-3">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : (settings ? 'Update Settings' : 'Save Settings') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-8 bg-blue-50 rounded-lg p-6">
                <h3 class="text-lg font-medium text-blue-900 mb-3">BulkSMS Configuration Help</h3>
                <div class="text-sm text-blue-800 space-y-2">
                    <p><strong>Getting Started:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>Sign up for a BulkSMS account at <a href="https://www.bulksms.com" target="_blank" rel="noopener noreferrer" class="underline">bulksms.com</a></li>
                        <li>Get your username and password from your BulkSMS dashboard</li>
                        <li>Enter your credentials above to enable SMS functionality</li>
                    </ul>
                    
                    <p class="mt-4"><strong>Sender Name:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>Optional field for customizing the sender name in SMS messages</li>
                        <li>Maximum 11 characters allowed</li>
                        <li>If left empty, BulkSMS will use their default sender name</li>
                    </ul>
                    
                    <p class="mt-4"><strong>Phone Number Format:</strong></p>
                    <ul class="list-disc list-inside ml-4 space-y-1">
                        <li>South African numbers: +27XXXXXXXXX (automatically formatted)</li>
                        <li>International numbers: +[country code][number]</li>
                        <li>Example: +27123456789 for South Africa</li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
