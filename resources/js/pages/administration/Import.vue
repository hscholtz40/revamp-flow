<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type EntityKey = 'customers' | 'suppliers' | 'products';

interface FieldDef {
    key: string;
    label: string;
    required: boolean;
    default_type?: 'text' | 'select';
    default_options?: Record<string, string>;
    default_placeholder?: string;
}

interface EntityDef {
    label: string;
    fields: FieldDef[];
}

const props = defineProps<{
    entityDefinitions: Record<EntityKey, EntityDef>;
}>();

const selectedEntity = ref<EntityKey>('customers');
const csvFile = ref<File | null>(null);
const uploading = ref(false);
const running = ref(false);
const importToken = ref<string | null>(null);
const csvHeaders = ref<string[]>([]);
const sampleRows = ref<Record<string, string>[]>([]);
const mapping = ref<Record<string, string>>({});
const defaults = ref<Record<string, string>>({});
const requiredMissing = ref<string[]>([]);
const uploadError = ref('');
const result = ref<{ created: number; skipped: number; errors: Array<{ row: number; message: string }> } | null>(null);

const currentDef = computed(() => props.entityDefinitions[selectedEntity.value]);
const missingRequiredKeys = computed(() =>
    currentDef.value.fields
        .filter((f) => f.required)
        .map((f) => f.key)
        .filter((key) => {
            const mapped = (mapping.value[key] ?? '__skip') !== '__skip';
            const defaulted = (defaults.value[key] ?? '').trim() !== '';
            return !mapped && !defaulted;
        })
);
const canRunImport = computed(() =>
    Boolean(importToken.value) &&
    missingRequiredKeys.value.length === 0 &&
    !running.value
);

const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

const resetUploadState = () => {
    importToken.value = null;
    csvHeaders.value = [];
    sampleRows.value = [];
    mapping.value = {};
    defaults.value = {};
    requiredMissing.value = [];
    result.value = null;
    uploadError.value = '';
};

const onEntityChange = () => {
    resetUploadState();
};

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    csvFile.value = target.files?.[0] ?? null;
};

const uploadCsv = async () => {
    uploadError.value = '';
    requiredMissing.value = [];
    result.value = null;

    if (!csvFile.value) {
        uploadError.value = 'Please choose a CSV file first.';
        return;
    }

    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('entity', selectedEntity.value);
        formData.append('file', csvFile.value);

        const response = await fetch('/administration/import/upload', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
            },
            body: formData,
        });

        const payload = await response.json();
        if (!response.ok) {
            uploadError.value = payload.message || 'Upload failed.';
            return;
        }

        importToken.value = payload.token;
        csvHeaders.value = payload.headers || [];
        sampleRows.value = payload.sampleRows || [];
        mapping.value = {};
        defaults.value = {};
        for (const field of (payload.fields || [])) {
            mapping.value[field.key] = '__skip';
            defaults.value[field.key] = '';
        }
    } catch {
        uploadError.value = 'Upload failed. Please try again.';
    } finally {
        uploading.value = false;
    }
};

const runImport = async () => {
    if (!canRunImport.value || !importToken.value) {
        return;
    }

    running.value = true;
    uploadError.value = '';
    requiredMissing.value = [];
    result.value = null;

    try {
        const response = await fetch('/administration/import/run', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                token: importToken.value,
                entity: selectedEntity.value,
                mapping: mapping.value,
                defaults: defaults.value,
            }),
        });

        const payload = await response.json();
        if (!response.ok) {
            uploadError.value = payload.message || 'Import failed.';
            requiredMissing.value = payload.missing_required || [];
            return;
        }

        result.value = payload;
    } catch {
        uploadError.value = 'Import failed. Please try again.';
    } finally {
        running.value = false;
    }
};
</script>

<template>
    <Head title="CSV Import" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'CSV Import', href: '#' }
    ]">
        <div class="p-4 space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">CSV Import</h1>
                <p class="text-sm text-gray-600 mt-1">Import customers, suppliers, or products and map CSV columns to system fields.</p>
            </div>

            <div class="rounded-lg border bg-white p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Import Type</label>
                        <select v-model="selectedEntity" @change="onEntityChange" class="w-full rounded border px-3 py-2">
                            <option value="customers">Customers</option>
                            <option value="suppliers">Suppliers</option>
                            <option value="products">Products</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">CSV File</label>
                        <div class="flex gap-2">
                            <input type="file" accept=".csv,text/csv,.txt" @change="onFileChange" class="w-full rounded border px-3 py-2" />
                            <button
                                type="button"
                                @click="uploadCsv"
                                :disabled="uploading"
                                class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ uploading ? 'Uploading...' : 'Upload CSV' }}
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="uploadError" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ uploadError }}
                </div>
            </div>

            <div v-if="importToken" class="rounded-lg border bg-white p-5 space-y-4">
                <h2 class="text-lg font-semibold text-gray-900">Column Mapping</h2>
                <p class="text-sm text-gray-600">
                    Map each system field to a CSV column. Required fields need either a column mapping or a default value.
                </p>

                <div v-if="missingRequiredKeys.length > 0" class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    Required fields missing: {{ missingRequiredKeys.join(', ') }}
                </div>
                <div v-if="requiredMissing.length > 0" class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    Backend validation failed. Required mappings: {{ requiredMissing.join(', ') }}
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div v-for="field in currentDef.fields" :key="field.key">
                        <div class="rounded-lg border border-gray-200 bg-gray-50/40 p-3 space-y-3">
                            <div class="text-sm font-medium text-gray-900">
                                {{ field.label }}
                                <span v-if="field.required" class="text-red-600">*</span>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">CSV column</label>
                                <select v-model="mapping[field.key]" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="__skip">-- Do not import --</option>
                                    <option v-for="(header, headerIdx) in csvHeaders" :key="`${headerIdx}-${header}`" :value="`__idx:${headerIdx}`">
                                        {{ header }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Default value (optional)</label>
                                <select
                                    v-if="field.default_type === 'select' && field.default_options"
                                    v-model="defaults[field.key]"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                >
                                    <option value="">-- No default --</option>
                                    <option v-for="(label, value) in field.default_options" :key="`${field.key}-${value}`" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                                <input
                                    v-else
                                    v-model="defaults[field.key]"
                                    type="text"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                    :placeholder="field.default_placeholder || 'Enter default value'"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">CSV Preview</h3>
                    <div class="overflow-auto rounded border">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th v-for="header in csvHeaders" :key="`h-${header}`" class="px-3 py-2 text-left font-medium text-gray-600 border-b">
                                        {{ header }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, idx) in sampleRows" :key="`r-${idx}`" class="odd:bg-white even:bg-gray-50">
                                    <td v-for="header in csvHeaders" :key="`${idx}-${header}`" class="px-3 py-2 border-b text-gray-800">
                                        {{ row[header] ?? '' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        @click="runImport"
                        :disabled="!canRunImport"
                        class="rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:opacity-50"
                    >
                        {{ running ? 'Importing...' : 'Run Import' }}
                    </button>
                </div>
            </div>

            <div v-if="result" class="rounded-lg border bg-white p-5 space-y-3">
                <h2 class="text-lg font-semibold text-gray-900">Import Result</h2>
                <div class="text-sm text-gray-800">Created: <span class="font-semibold">{{ result.created }}</span></div>
                <div class="text-sm text-gray-800">Skipped: <span class="font-semibold">{{ result.skipped }}</span></div>
                <div v-if="result.errors?.length" class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    <div class="font-medium mb-1">Row errors (first {{ result.errors.length }}):</div>
                    <ul class="space-y-1">
                        <li v-for="err in result.errors" :key="`${err.row}-${err.message}`">
                            Row {{ err.row }}: {{ err.message }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

