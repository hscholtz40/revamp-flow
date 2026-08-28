<script setup lang="ts">
import AddressAutocompleteInput from '@/components/AddressAutocompleteInput.vue';
import SupplierBankDetailsFields from '@/components/suppliers/SupplierBankDetailsFields.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import suppliers from '@/routes/suppliers';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',
    vat_number: '',
    bank_name: '',
    bank_account_name: '',
    bank_account_number: '',
    bank_sort_code: '',
    notes: '',
    is_active: true,
});

function submit() {
    form.post(suppliers.store().url);
}
</script>

<template>
    <Head title="Create Supplier" />
    <AppLayout :breadcrumbs="[
        { title: 'Suppliers', href: suppliers.index().url },
        { title: 'Create Supplier', href: '#' }
    ]">
        <div class="p-6">
            <div class="mb-6">
                <Link
                    :href="suppliers.index().url"
                    class="mb-4 inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Suppliers
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Create Supplier</h1>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Basic Information -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Basic Information</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.name }"
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.name }}
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">
                                    VAT Number
                                </label>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Contact Information</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Phone</label>
                                <input
                                    v-model="form.phone"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Address</h2>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Address</label>
                                <AddressAutocompleteInput
                                    v-model="form.address"
                                    v-model:city="form.city"
                                    v-model:state="form.state"
                                    v-model:postal-code="form.postal_code"
                                    v-model:country="form.country"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                                <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.address }}
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">City</label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">State/Province</label>
                                <input
                                    v-model="form.state"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Postal Code</label>
                                <input
                                    v-model="form.postal_code"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700">Country</label>
                                <input
                                    v-model="form.country"
                                    type="text"
                                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>
                    </div>

                    <SupplierBankDetailsFields :form="form" />

                    <!-- Additional Information -->
                    <div>
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Additional Information</h2>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Notes</label>
                            <textarea
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                placeholder="Any additional notes about this supplier..."
                            ></textarea>
                        </div>

                        <div class="mt-4">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm font-medium text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 border-t pt-6">
                        <Link
                            :href="suppliers.index().url"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Supplier' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

