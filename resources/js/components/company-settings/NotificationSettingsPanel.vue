<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface NotificationEventConfig {
    enabled: boolean;
    notify_admin: boolean;
    notify_client: boolean;
    notify_staff: boolean;
}

interface NotificationEventDefinition {
    label: string;
    description: string;
    default: NotificationEventConfig;
}

interface NotificationSettings {
    automation_enabled: boolean;
    events: Record<string, NotificationEventConfig>;
}

const props = defineProps<{
    companyId: number;
    notificationSettings: NotificationSettings;
    notificationEventCatalog: Record<string, NotificationEventDefinition>;
}>();

function buildInitialEvents(): Record<string, NotificationEventConfig> {
    return Object.fromEntries(
        Object.keys(props.notificationEventCatalog).map((key) => [
            key,
            {
                enabled: props.notificationSettings.events?.[key]?.enabled ?? props.notificationEventCatalog[key].default.enabled,
                notify_admin: props.notificationSettings.events?.[key]?.notify_admin ?? props.notificationEventCatalog[key].default.notify_admin,
                notify_client: props.notificationSettings.events?.[key]?.notify_client ?? props.notificationEventCatalog[key].default.notify_client,
                notify_staff: props.notificationSettings.events?.[key]?.notify_staff ?? props.notificationEventCatalog[key].default.notify_staff,
            },
        ]),
    );
}

const form = useForm({
    automation_enabled: props.notificationSettings.automation_enabled ?? false,
    events: buildInitialEvents(),
});

const eventEntries = computed(() =>
    Object.entries(props.notificationEventCatalog).map(([key, definition]) => ({
        key,
        ...definition,
    })),
);

function submit() {
    form.put(`/company-settings/${props.companyId}/notification-settings`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-lg border bg-white p-6">
            <h2 class="mb-2 text-lg font-semibold text-gray-900">System email notifications</h2>
            <p class="mb-4 text-sm text-gray-600">
                Choose which system events send automatic emails, and which recipients receive them.
                Customer reminder automation is configured separately under Automated Reminders.
            </p>

            <label class="flex items-center gap-2">
                <input
                    v-model="form.automation_enabled"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                <span class="text-sm font-medium text-gray-700">Enable system email notifications</span>
            </label>
        </div>

        <div
            v-for="event in eventEntries"
            :key="event.key"
            class="rounded-lg border bg-white p-6"
        >
            <div class="mb-4">
                <h3 class="text-base font-semibold text-gray-900">{{ event.label }}</h3>
                <p class="mt-1 text-sm text-gray-600">{{ event.description }}</p>
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-2">
                    <input
                        v-model="form.events[event.key].enabled"
                        type="checkbox"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        :disabled="!form.automation_enabled"
                    />
                    <span class="text-sm font-medium text-gray-700">Send email for this event</span>
                </label>

                <div class="grid gap-3 md:grid-cols-3">
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.events[event.key].notify_admin"
                            type="checkbox"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            :disabled="!form.automation_enabled || !form.events[event.key].enabled"
                        />
                        <span class="text-sm text-gray-700">Admin / company email</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.events[event.key].notify_client"
                            type="checkbox"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            :disabled="!form.automation_enabled || !form.events[event.key].enabled"
                        />
                        <span class="text-sm text-gray-700">Client / contractor / contact</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input
                            v-model="form.events[event.key].notify_staff"
                            type="checkbox"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                            :disabled="!form.automation_enabled || !form.events[event.key].enabled"
                        />
                        <span class="text-sm text-gray-700">Assigned staff</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
            >
                {{ form.processing ? 'Saving...' : 'Save notification settings' }}
            </button>
        </div>
    </form>
</template>
