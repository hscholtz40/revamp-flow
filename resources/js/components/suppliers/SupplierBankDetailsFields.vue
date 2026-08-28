<script setup lang="ts">
interface SupplierBankForm {
    bank_name: string;
    bank_account_name: string;
    bank_account_number: string;
    bank_sort_code: string;
    errors: Record<string, string | undefined>;
}

defineProps<{
    form: SupplierBankForm;
}>();
</script>

<template>
    <div>
        <h2 class="mb-4 text-lg font-semibold text-gray-900">Bank Details for Payments</h2>
        <p class="mb-4 text-sm text-gray-600">
            Optional banking information used when paying this supplier.
        </p>
        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Bank Name</label>
                <input
                    v-model="form.bank_name"
                    type="text"
                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': form.errors.bank_name }"
                />
                <div v-if="form.errors.bank_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_name }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Account Name</label>
                <input
                    v-model="form.bank_account_name"
                    type="text"
                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': form.errors.bank_account_name }"
                />
                <div v-if="form.errors.bank_account_name" class="mt-1 text-sm text-red-600">{{ form.errors.bank_account_name }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Account Number</label>
                <input
                    v-model="form.bank_account_number"
                    type="text"
                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': form.errors.bank_account_number }"
                />
                <div v-if="form.errors.bank_account_number" class="mt-1 text-sm text-red-600">{{ form.errors.bank_account_number }}</div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">Branch Code</label>
                <input
                    v-model="form.bank_sort_code"
                    type="text"
                    class="w-full rounded border px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': form.errors.bank_sort_code }"
                />
                <div v-if="form.errors.bank_sort_code" class="mt-1 text-sm text-red-600">{{ form.errors.bank_sort_code }}</div>
            </div>
        </div>
    </div>
</template>
