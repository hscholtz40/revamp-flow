<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import whatsappSettings from '@/routes/whatsapp-settings';
import administration from '@/routes/administration';

interface WhatsAppSettings {
    id?: number;
    provider: string;
    account_sid: string;
    from_number: string;
    is_active: boolean;
    has_api_key?: boolean;
    has_api_secret?: boolean;
}

const props = defineProps<{
    settings: WhatsAppSettings | null;
}>();

const form = useForm({
    provider: props.settings?.provider || 'twilio',
    api_key: '',
    api_secret: '',
    account_sid: props.settings?.account_sid || '',
    from_number: props.settings?.from_number || '',
    is_active: props.settings?.is_active ?? true,
});

const showSecret = ref(false);

const submit = () => {
    if (props.settings?.id) {
        form.put(whatsappSettings.update(props.settings.id).url);
    } else {
        form.post(whatsappSettings.store().url);
    }
};

const toggleSecretVisibility = () => {
    showSecret.value = !showSecret.value;
};

const handleActiveToggle = () => {
    if (!form.is_active) {
        // Clear fields when disabling WhatsApp functionality
        form.api_key = '';
        form.api_secret = '';
        form.account_sid = '';
        form.from_number = '';
    }
};

const isTwilio = () => form.provider === 'twilio';
const isMeta = () => form.provider === 'meta';
</script>

<template>
    <Head title="WhatsApp Settings" />
    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: administration.index().url },
        { title: 'WhatsApp Settings', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">WhatsApp Settings</h1>
                <p class="text-gray-600">Configure WhatsApp Business API settings for sending WhatsApp messages to customers.</p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Provider Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Provider
                        </label>
                        <select
                            v-model="form.provider"
                            class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                            :disabled="!form.is_active"
                        >
                            <!--<option value="twilio">Twilio</option>-->   
                            <option value="meta">Meta (WhatsApp Business API)</option>
                        </select>
                        <div v-if="form.errors.provider" class="text-sm text-red-600 mt-1">
                            {{ form.errors.provider }}
                        </div>
                    </div>

                    <!-- Twilio Fields -->
                    <template v-if="isTwilio()">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Account SID <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.account_sid"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                                    :disabled="!form.is_active"
                                    placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                                    :required="form.is_active"
                                />
                                <div v-if="form.errors.account_sid" class="text-sm text-red-600 mt-1">
                                    {{ form.errors.account_sid }}
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Auth Token <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        v-model="form.api_secret"
                                        :type="showSecret ? 'text' : 'password'"
                                        class="w-full rounded border px-3 py-2 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                        :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                                        :disabled="!form.is_active"
                                        :placeholder="settings?.has_api_secret ? 'Leave blank to keep current token' : 'Your Twilio Auth Token'"
                                        :required="form.is_active && (!settings?.id || !settings?.has_api_secret)"
                                        autocomplete="new-password"
                                    />
                                    <p v-if="form.is_active && settings?.has_api_secret" class="text-xs text-gray-500 mt-1">
                                        Leave blank to keep your current auth token.
                                    </p>
                                    <button
                                        type="button"
                                        @click="toggleSecretVisibility"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                    >
                                        <span class="text-gray-400 hover:text-gray-600">
                                            {{ showSecret ? 'Hide' : 'Show' }}
                                        </span>
                                    </button>
                                </div>
                                <div v-if="form.errors.api_secret" class="text-sm text-red-600 mt-1">
                                    {{ form.errors.api_secret }}
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Meta Fields -->
                    <template v-if="isMeta()">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Access Token <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.api_key"
                                    :type="showSecret ? 'text' : 'password'"
                                    class="w-full rounded border px-3 py-2 pr-10 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                                    :disabled="!form.is_active"
                                    :placeholder="settings?.has_api_key ? 'Leave blank to keep current access token' : 'Your Meta WhatsApp Business API Access Token'"
                                    :required="form.is_active && (!settings?.id || !settings?.has_api_key)"
                                    autocomplete="off"
                                />
                                <p v-if="form.is_active && settings?.has_api_key" class="text-xs text-gray-500 mt-1">
                                    Leave blank to keep your current access token.
                                </p>
                                <button
                                    type="button"
                                    @click="toggleSecretVisibility"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                                >
                                    <span class="text-gray-400 hover:text-gray-600">
                                        {{ showSecret ? 'Hide' : 'Show' }}
                                    </span>
                                </button>
                            </div>
                            <div v-if="form.errors.api_key" class="text-sm text-red-600 mt-1">
                                {{ form.errors.api_key }}
                            </div>
                        </div>

                    </template>

                    <!-- From Number (WhatsApp Business Number) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            WhatsApp Business Number <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.from_number"
                            type="text"
                            class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            :class="{ 'opacity-50 cursor-not-allowed': !form.is_active }"
                            :disabled="!form.is_active"
                            :placeholder="isTwilio() ? '+14155238886' : '1234567890'"
                            :required="form.is_active"
                        />
                        <p class="text-sm text-gray-500 mt-1">
                            <span v-if="isTwilio()">Format: +[country code][number] (e.g., +14155238886)</span>
                            <span v-else>Your WhatsApp Business Phone Number ID (without +)</span>
                        </p>
                        <div v-if="form.errors.from_number" class="text-sm text-red-600 mt-1">
                            {{ form.errors.from_number }}
                        </div>
                    </div>

                    <!-- Active Toggle -->
                    <div class="flex items-center">
                        <input
                            v-model="form.is_active"
                            @change="handleActiveToggle"
                            type="checkbox"
                            id="is_active"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        />
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">
                            Enable WhatsApp functionality
                        </label>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center justify-between pt-6 border-t">
                        <div class="text-sm text-gray-500">
                            <p v-if="!settings" class="text-amber-600">
                                <strong>Note:</strong> WhatsApp functionality is not configured. Configure these settings to enable WhatsApp sending.
                            </p>
                            <p v-else-if="!settings.is_active" class="text-red-600">
                                <strong>Note:</strong> WhatsApp functionality is currently disabled.
                            </p>
                            <p v-else class="text-green-600">
                                <strong>Status:</strong> WhatsApp functionality is active and configured.
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
                <h3 class="text-lg font-medium text-blue-900 mb-3">WhatsApp Configuration Help</h3>
                <div class="text-sm text-blue-800 space-y-4">
                    <div>
                        <p><strong>Meta WhatsApp Business API Setup:</strong></p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Create a Meta Business Account at <a href="https://business.facebook.com" target="_blank" class="underline">business.facebook.com</a></li>
                            <li>Set up a WhatsApp Business Account through Meta Business Manager</li>
                            <li>Go to <a href="https://developers.facebook.com" target="_blank" class="underline">developers.facebook.com</a> and create a new app</li>
                            <li>Add the WhatsApp product to your app</li>
                            <li>Get your Access Token from the App Dashboard → WhatsApp → API Setup</li>
                            <li>Find your Phone Number ID in the same section (this is a numeric ID, not your actual phone number)</li>
                            <li>Enter your Access Token and Phone Number ID above to enable WhatsApp functionality</li>
                        </ul>
                        
                        <p class="mt-3"><strong>Important Notes:</strong></p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li><strong>Phone Number ID:</strong> This is a numeric identifier (e.g., "123456789012345"), not your actual WhatsApp phone number</li>
                            <li><strong>Access Token:</strong> Keep this secure and never share it publicly</li>
                            <li><strong>Templates Required:</strong> Message templates must be created and approved in Meta Business Manager before sending messages</li>
                            <li><strong>24-Hour Window:</strong> Free-form text messages can only be sent within 24 hours of a customer's last message</li>
                        </ul>
                        
                        <p class="mt-3"><strong>Template Configuration:</strong></p>
                        <ul class="list-disc list-inside ml-4 space-y-1">
                            <li>Templates must be pre-approved by Meta before use</li>
                            <li>Create templates in Meta Business Manager → WhatsApp → Message Templates</li>
                            <li>Configure template names per reminder type in <strong>Administration → Reminder Settings</strong></li>
                            <li>Each reminder type (invoice created, payment received, etc.) can have its own template</li>
                            <li>Templates support dynamic variables (e.g., {{1}}, {{2}}) for personalized messages</li>
                        </ul>
                        
                        <p class="mt-3"><strong>Getting Your Phone Number ID:</strong></p>
                        <ol class="list-decimal list-inside ml-4 space-y-1">
                            <li>Go to Meta Business Manager → WhatsApp → API Setup</li>
                            <li>Find the "Phone number ID" field (it's a long numeric value)</li>
                            <li>Copy this ID and paste it in the "WhatsApp Business Number" field above</li>
                            <li>Note: This is different from your actual WhatsApp phone number</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

