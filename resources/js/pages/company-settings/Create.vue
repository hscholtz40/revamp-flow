<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import companySettings from '@/routes/company-settings';
import { ref } from 'vue';

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
    website: '',
    description: '',
    invoice_footer: '',
    jobcard_footer: '',
    quote_footer: '',
    default_invoice_terms: '',
    default_quote_terms: '',
    default_jobcard_terms: '',
    is_active: true,
    is_default: false,
    logo: null as File | null,
});

const logoPreview = ref<string | null>(null);

function handleLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        form.logo = target.files[0];
        
        // Create preview
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target?.result as string;
        };
        reader.readAsDataURL(target.files[0]);
    }
}

function submit() {
    form.post(companySettings.store().url, {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Create Company" />

    <AppLayout :breadcrumbs="[
        { title: 'Administration', href: '/administration' },
        { title: 'Company Settings', href: companySettings.index().url },
        { title: 'Create', href: '#' }
    ]">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center gap-4">
                <Link
                    :href="companySettings.index().url"
                    class="flex items-center gap-2 text-gray-600 hover:text-gray-900"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Companies
                </Link>
            </div>

            <div class="mx-auto max-w-4xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-900">Create New Company</h1>
                    <p class="text-gray-600">Add a new company to your organization</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Company Logo -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Company Logo</h2>
                        <div class="flex items-center gap-6">
                            <div v-if="logoPreview" class="h-20 w-20 overflow-hidden rounded-lg border">
                                <img :src="logoPreview" alt="Company Logo" class="h-full w-full object-cover" />
                            </div>
                            <div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="handleLogoChange"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <p class="mt-1 text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                            </div>
                        </div>
                    </div>

                    <!-- Company Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Company Information</h2>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Company Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                    required
                                />
                                <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Phone</label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Website</label>
                                <input
                                    v-model="form.website"
                                    type="url"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.website" class="mt-1 text-sm text-red-600">{{ form.errors.website }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Address Information</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="3"
                                    class="w-full rounded border px-3 py-2"
                                ></textarea>
                                <div v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</div>
                            </div>
                            
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium">City</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.city" class="mt-1 text-sm text-red-600">{{ form.errors.city }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">State/Province</label>
                                    <input
                                        v-model="form.state"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.state" class="mt-1 text-sm text-red-600">{{ form.errors.state }}</div>
                                </div>
                                
                                <div>
                                    <label class="mb-1 block text-sm font-medium">Postal Code</label>
                                    <input
                                        v-model="form.postal_code"
                                        type="text"
                                        class="w-full rounded border px-3 py-2"
                                    />
                                    <div v-if="form.errors.postal_code" class="mt-1 text-sm text-red-600">{{ form.errors.postal_code }}</div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">Country</label>
                                <input
                                    v-model="form.country"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.country" class="mt-1 text-sm text-red-600">{{ form.errors.country }}</div>
                            </div>
                            
                            <div>
                                <label class="mb-1 block text-sm font-medium">VAT Number</label>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    class="w-full rounded border px-3 py-2"
                                />
                                <div v-if="form.errors.vat_number" class="mt-1 text-sm text-red-600">{{ form.errors.vat_number }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Description</h2>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Company Description</label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                class="w-full rounded border px-3 py-2"
                                placeholder="Tell us about your company..."
                            ></textarea>
                            <div v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</div>
                        </div>
                    </div>

                    <!-- Document Footers -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Document Footers</h2>
                        <p class="mb-4 text-sm text-gray-600">These footer texts will appear at the bottom of the respective PDF documents.</p>
                        
                        <div class="space-y-6">
                            <!-- Invoice Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Invoice Footer</label>
                                <textarea
                                    v-model="form.invoice_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on invoice PDFs (e.g., payment terms, thank you message, etc.)"
                                ></textarea>
                                <div v-if="form.errors.invoice_footer" class="mt-1 text-sm text-red-600">{{ form.errors.invoice_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all invoice PDFs generated for this company.</p>
                            </div>

                            <!-- Quote Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Quote Footer</label>
                                <textarea
                                    v-model="form.quote_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on quote PDFs (e.g., validity period, terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.quote_footer" class="mt-1 text-sm text-red-600">{{ form.errors.quote_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all quote PDFs generated for this company.</p>
                            </div>

                            <!-- Jobcard Footer -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Jobcard Footer</label>
                                <textarea
                                    v-model="form.jobcard_footer"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter footer text that will appear on jobcard PDFs (e.g., completion notes, warranty info, etc.)"
                                ></textarea>
                                <div v-if="form.errors.jobcard_footer" class="mt-1 text-sm text-red-600">{{ form.errors.jobcard_footer }}</div>
                                <p class="mt-1 text-xs text-gray-500">This text will appear at the bottom of all jobcard PDFs generated for this company.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Default Terms & Conditions -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Default Terms & Conditions</h2>
                        <p class="mb-4 text-sm text-gray-600">These terms will be automatically populated when creating new documents. They can be customized for each individual document.</p>
                        
                        <div class="space-y-6">
                            <!-- Invoice Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Invoice Terms</label>
                                <textarea
                                    v-model="form.default_invoice_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for invoices (e.g., payment terms, late fees, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_invoice_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_invoice_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new invoices.</p>
                            </div>

                            <!-- Quote Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Quote Terms</label>
                                <textarea
                                    v-model="form.default_quote_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for quotes (e.g., validity period, acceptance terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_quote_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_quote_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new quotes.</p>
                            </div>

                            <!-- Jobcard Terms -->
                            <div>
                                <label class="mb-1 block text-sm font-medium">Default Jobcard Terms</label>
                                <textarea
                                    v-model="form.default_jobcard_terms"
                                    rows="4"
                                    class="w-full rounded border px-3 py-2"
                                    placeholder="Enter default terms and conditions for jobcards (e.g., warranty info, completion terms, etc.)"
                                ></textarea>
                                <div v-if="form.errors.default_jobcard_terms" class="mt-1 text-sm text-red-600">{{ form.errors.default_jobcard_terms }}</div>
                                <p class="mt-1 text-xs text-gray-500">These terms will be pre-filled when creating new jobcards.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="rounded-lg border bg-white p-6">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900">Settings</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Active (available for selection)</span>
                                </label>
                            </div>

                            <div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="form.is_default"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Set as default company</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="flex items-center justify-end gap-4">
                        <Link
                            :href="companySettings.index().url"
                            class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Company' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
