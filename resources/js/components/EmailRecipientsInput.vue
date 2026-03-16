<template>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address(es) *</label>
        <div class="min-h-[42px] rounded border px-3 py-2"
             :class="{ 'border-red-500': error }">
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="(email, idx) in modelValue"
                    :key="`${email}-${idx}`"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-100 text-sm text-gray-800"
                >
                    {{ email }}
                    <button
                        type="button"
                        @click="remove(idx)"
                        class="text-gray-500 hover:text-red-600 focus:outline-none"
                        aria-label="Remove recipient"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
                <button
                    type="button"
                    @click="showAddModal = true"
                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-dashed border-gray-300 text-sm text-gray-600 hover:border-blue-400 hover:text-blue-600 focus:outline-none focus:ring-1 focus:ring-blue-500"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add email/contact
                </button>
            </div>
        </div>
        <div v-if="error" class="text-red-500 text-sm mt-1">{{ error }}</div>

        <!-- Add recipient modal -->
        <div
            v-if="showAddModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]"
            @click.self="showAddModal = false"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl" @click.stop>
                <h3 class="text-lg font-semibold mb-4">Add Recipient</h3>
                <div class="space-y-4">
                    <!-- Tabs: Contact or Manual -->
                    <div class="flex gap-2 border-b border-gray-200">
                        <button
                            type="button"
                            :class="addMode === 'contact' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-2 text-sm font-medium border-b-2 -mb-px"
                            @click="addMode = 'contact'"
                        >
                            From contact
                        </button>
                        <button
                            type="button"
                            :class="addMode === 'email' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                            class="px-3 py-2 text-sm font-medium border-b-2 -mb-px"
                            @click="addMode = 'email'"
                        >
                            Enter email
                        </button>
                    </div>

                    <!-- Contact search -->
                    <div v-if="addMode === 'contact'" class="space-y-2">
                        <p v-if="!customerId" class="text-sm text-amber-600">
                            No customer linked. Enter an email manually or link a customer to the document.
                        </p>
                        <template v-else>
                            <input
                                v-model="contactSearchQuery"
                                @input="searchContacts"
                                type="text"
                                placeholder="Search contact by name, email, or phone"
                                class="w-full rounded border px-3 py-2"
                            />
                            <div
                                v-if="contactSearchQuery && filteredContacts.length > 0"
                                class="max-h-40 overflow-auto border rounded mt-1"
                            >
                                <div
                                    v-for="c in filteredContacts"
                                    :key="c.id"
                                    @click="addFromContact(c)"
                                    class="px-3 py-2 hover:bg-gray-100 cursor-pointer flex justify-between items-center"
                                >
                                    <div>
                                        <span class="font-medium">{{ c.name }}</span>
                                        <span v-if="c.email" class="text-gray-500 text-sm ml-2">{{ c.email }}</span>
                                    </div>
                                    <span v-if="isAlreadyAdded(c.email)" class="text-xs text-amber-600">Already added</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Manual email -->
                    <div v-else class="space-y-2">
                        <input
                            v-model="manualEmail"
                            type="email"
                            placeholder="email@example.com"
                            class="w-full rounded border px-3 py-2"
                            @keydown.enter.prevent="addManualEmail"
                        />
                        <button
                            type="button"
                            @click="addManualEmail"
                            :disabled="!isValidEmail(manualEmail)"
                            class="w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Add
                        </button>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        type="button"
                        @click="closeAddModal"
                        class="rounded border px-4 py-2 hover:bg-gray-50"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import axios from 'axios';

interface Contact {
    id: number;
    customer_id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
}

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        customerId: number | null;
        error?: string;
    }>(),
    { error: '' }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string[]): void;
}>();

const showAddModal = ref(false);
const addMode = ref<'contact' | 'email'>('contact');
const contactSearchQuery = ref('');
const manualEmail = ref('');
const filteredContacts = ref<Contact[]>([]);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

function remove(idx: number) {
    const next = [...props.modelValue];
    next.splice(idx, 1);
    emit('update:modelValue', next);
}

function addEmail(email: string) {
    const trimmed = email.trim().toLowerCase();
    if (!trimmed || !isValidEmail(trimmed)) return;
    const existing = props.modelValue.map(e => e.toLowerCase());
    if (existing.includes(trimmed)) return;
    emit('update:modelValue', [...props.modelValue, trimmed]);
}

function addFromContact(contact: Contact) {
    if (!contact.email) return;
    addEmail(contact.email);
    closeAddModal();
}

function addManualEmail() {
    if (!manualEmail.value) return;
    addEmail(manualEmail.value);
    manualEmail.value = '';
    closeAddModal();
}

function isValidEmail(s: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s?.trim() || '');
}

function isAlreadyAdded(email: string | null | undefined): boolean {
    if (!email) return false;
    return props.modelValue.map(e => e.toLowerCase()).includes(email.trim().toLowerCase());
}

function searchContacts() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (!props.customerId || !contactSearchQuery.value.trim()) {
            filteredContacts.value = [];
            return;
        }
        try {
            const { data } = await axios.get('/api/contacts/search', {
                params: { customer_id: props.customerId, search: contactSearchQuery.value.trim() },
            });
            filteredContacts.value = data.contacts || [];
        } catch {
            filteredContacts.value = [];
        }
    }, 200);
}

function closeAddModal() {
    showAddModal.value = false;
    addMode.value = 'contact';
    contactSearchQuery.value = '';
    manualEmail.value = '';
    filteredContacts.value = [];
}

watch(showAddModal, (open) => {
    if (open) {
        addMode.value = 'contact';
        contactSearchQuery.value = '';
        manualEmail.value = '';
        filteredContacts.value = [];
    }
});
</script>
