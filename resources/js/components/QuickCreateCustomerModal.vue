<script setup lang="ts">
import AddressAutocompleteInput from '@/components/AddressAutocompleteInput.vue';
import type { QuickCreateCustomerPayload } from '@/types/customers';
import type { InertiaForm } from '@inertiajs/vue3';

const paymentTermsOptions = ['COD', 'Net 7 Days', 'Net 14 Days', 'Net 30 Days', 'Net 60 Days'];

defineProps<{
    modelValue: boolean;
    form: InertiaForm<QuickCreateCustomerPayload>;
}>();

const emit = defineEmits<{
    'update:modelValue': [boolean];
    submit: [];
}>();

function close() {
    emit('update:modelValue', false);
}
</script>

<template>
    <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        @click.self="close"
    >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-lg bg-white shadow-xl" @click.stop>
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-semibold text-gray-900">Quick Create Customer</h3>
            </div>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="emit('submit')">
                <div class="space-y-6 overflow-y-auto px-6 py-4">
                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">Company details</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Company name *</span>
                                <input v-model="form.name" type="text" class="w-full rounded border px-3 py-2" required />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Registration number</span>
                                <input v-model="form.registration_number" type="text" class="w-full rounded border px-3 py-2" />
                                <div v-if="form.errors.registration_number" class="mt-1 text-sm text-red-600">{{ form.errors.registration_number }}</div>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Company email *</span>
                                <input v-model="form.email" type="email" class="w-full rounded border px-3 py-2" required />
                                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                            </label>
                            <label class="block md:col-span-2">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Company address</span>
                                <AddressAutocompleteInput
                                    v-model="form.address"
                                    v-model:city="form.city"
                                    v-model:country="form.country"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">City</span>
                                <input v-model="form.city" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Country</span>
                                <input v-model="form.country" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Company cell</span>
                                <input v-model="form.company_cell" type="text" class="w-full rounded border px-3 py-2" />
                                <div v-if="form.errors.company_cell" class="mt-1 text-sm text-red-600">{{ form.errors.company_cell }}</div>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Company tel</span>
                                <input v-model="form.company_tel" type="text" class="w-full rounded border px-3 py-2" />
                                <div v-if="form.errors.company_tel" class="mt-1 text-sm text-red-600">{{ form.errors.company_tel }}</div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">Contact person</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Name</span>
                                <input v-model="form.contact_first_name" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Surname</span>
                                <input v-model="form.contact_last_name" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Cell</span>
                                <input v-model="form.contact_cell" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Email</span>
                                <input v-model="form.contact_email" type="email" class="w-full rounded border px-3 py-2" />
                                <div v-if="form.errors.contact_email" class="mt-1 text-sm text-red-600">{{ form.errors.contact_email }}</div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h4 class="mb-3 text-sm font-semibold text-gray-900">Account settings</h4>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">Payment terms</span>
                                <select v-model="form.terms" class="w-full rounded border px-3 py-2">
                                    <option v-for="term in paymentTermsOptions" :key="term" :value="term">{{ term }}</option>
                                </select>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-sm font-medium text-gray-700">VAT number</span>
                                <input v-model="form.vat_number" type="text" class="w-full rounded border px-3 py-2" />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 border-t border-gray-200 px-6 py-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create' }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded border px-4 py-2 hover:bg-gray-50"
                        @click="close"
                    >
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
