<template>
    <AppLayout>
        <Head title="Localization" />

        <div class="space-y-6">
            <div class="bg-white shadow rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900">Localization</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Company: {{ company.name }}. Defaults apply to lists, forms, PDFs, and customer-facing emails for this company.
                </p>
            </div>

            <form @submit.prevent="submit" class="bg-white shadow rounded-lg p-6 space-y-6">
                <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Number formatting</h3>
                    <p class="text-xs text-gray-600">
                        Used for currency and quantities (e.g. European style often uses comma for decimals and period or space for thousands).
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Decimal separator</label>
                            <input
                                v-model="form.locale_decimal_separator"
                                type="text"
                                maxlength="8"
                                class="w-full rounded border px-3 py-2 font-mono"
                                :class="{ 'border-red-500': form.errors.locale_decimal_separator }"
                                placeholder="."
                                aria-describedby="decimal-hint"
                            />
                            <p id="decimal-hint" class="text-xs text-gray-500 mt-1">Usually <code class="bg-gray-100 px-1 rounded">.</code> or <code class="bg-gray-100 px-1 rounded">,</code></p>
                            <div v-if="form.errors.locale_decimal_separator" class="text-red-500 text-sm mt-1">
                                {{ form.errors.locale_decimal_separator }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Thousands separator</label>
                            <input
                                v-model="form.locale_thousands_separator"
                                type="text"
                                maxlength="8"
                                class="w-full rounded border px-3 py-2 font-mono"
                                :class="{ 'border-red-500': form.errors.locale_thousands_separator }"
                                placeholder=","
                                aria-describedby="thousands-hint"
                            />
                            <p id="thousands-hint" class="text-xs text-gray-500 mt-1">Often <code class="bg-gray-100 px-1 rounded">,</code>, <code class="bg-gray-100 px-1 rounded">.</code>, space, or apostrophe</p>
                            <div v-if="form.errors.locale_thousands_separator" class="text-red-500 text-sm mt-1">
                                {{ form.errors.locale_thousands_separator }}
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                        <span class="font-medium text-gray-900">Preview:</span>
                        {{ preview }}
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-900">Date and time</h3>
                    <p class="text-xs text-gray-600">
                        These settings control how dates and times are displayed throughout the application.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Timezone</label>
                            <select
                                v-model="form.locale_timezone"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.locale_timezone }"
                            >
                                <option v-for="timezone in timezones" :key="timezone" :value="timezone">{{ timezone }}</option>
                            </select>
                            <div v-if="form.errors.locale_timezone" class="text-red-500 text-sm mt-1">
                                {{ form.errors.locale_timezone }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date format</label>
                            <select
                                v-model="form.locale_date_format"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.locale_date_format }"
                            >
                                <option v-for="option in dateFormatOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <div v-if="form.errors.locale_date_format" class="text-red-500 text-sm mt-1">
                                {{ form.errors.locale_date_format }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time format</label>
                            <select
                                v-model="form.locale_time_format"
                                class="w-full rounded border px-3 py-2"
                                :class="{ 'border-red-500': form.errors.locale_time_format }"
                            >
                                <option value="24h">24-hour</option>
                                <option value="12h">12-hour (AM/PM)</option>
                            </select>
                            <div v-if="form.errors.locale_time_format" class="text-red-500 text-sm mt-1">
                                {{ form.errors.locale_time_format }}
                            </div>
                        </div>
                    </div>
                    <div class="rounded-md bg-gray-50 border border-gray-200 p-3 text-sm text-gray-700">
                        <span class="font-medium text-gray-900">Date/time preview:</span>
                        {{ dateTimePreview }}
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save localization' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDecimalWithSeparators } from '@/composables/useNumberFormat';
import { formatLocalizedDateTime } from '@/composables/useDateTimeFormat';

const props = defineProps<{
    localization: {
        locale_decimal_separator: string;
        locale_thousands_separator: string;
        locale_timezone: string;
        locale_date_format: 'dd/mm/yyyy' | 'mm/dd/yyyy' | 'yyyy-mm-dd' | 'd mmm yyyy';
        locale_time_format: '24h' | '12h';
    };
    timezones: string[];
    company: {
        id: number;
        name: string;
    };
}>();

const form = useForm({
    locale_decimal_separator: props.localization.locale_decimal_separator,
    locale_thousands_separator: props.localization.locale_thousands_separator,
    locale_timezone: props.localization.locale_timezone,
    locale_date_format: props.localization.locale_date_format,
    locale_time_format: props.localization.locale_time_format,
});

const timezones = props.timezones;
const dateFormatOptions = [
    { value: 'dd/mm/yyyy', label: 'DD/MM/YYYY' },
    { value: 'mm/dd/yyyy', label: 'MM/DD/YYYY' },
    { value: 'yyyy-mm-dd', label: 'YYYY-MM-DD' },
    { value: 'd mmm yyyy', label: 'D MMM YYYY' },
] as const;

const preview = computed(() => {
    const dec = form.locale_decimal_separator || '.';
    const thou = form.locale_thousands_separator ?? ',';
    if (dec === thou) {
        return 'R— (separators must differ)';
    }
    const sample = formatDecimalWithSeparators(1234567.89, 2, 2, dec, thou);
    return `R${sample} (example amount)`;
});

const dateTimePreview = computed(() => {
    const previewDate = new Date('2026-04-08T14:35:00Z');
    return formatLocalizedDateTime(previewDate, {
        timezone: form.locale_timezone,
        date_format: form.locale_date_format,
        time_format: form.locale_time_format,
    });
});

function submit() {
    form.put('/administration/localization', { preserveScroll: true });
}
</script>
