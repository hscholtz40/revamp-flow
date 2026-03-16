<template>
    <div class="relative">
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>
        <div class="relative">
            <input
                v-model="searchQuery"
                @input="handleSearch"
                @focus="searchFocused = true"
                @blur="handleBlur"
                type="text"
                :placeholder="selectedContact ? selectedContact.name : placeholder"
                class="w-full rounded border px-3 py-2"
                :class="{ 'border-red-500': error }"
            />
            <button
                v-if="selectedContact"
                @click.prevent="clearContact"
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Search Results Dropdown -->
        <div
            v-if="searchFocused && (filteredContacts.length > 0 || searchQuery)"
            class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
        >
            <!-- Quick Create Option -->
            <div
                v-if="customerId && searchQuery && !filteredContacts.some(c => c.name.toLowerCase() === searchQuery.toLowerCase())"
                @mousedown.prevent="showQuickCreateModal = true"
                class="px-4 py-2 bg-blue-50 hover:bg-blue-100 cursor-pointer border-b border-gray-200"
            >
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-sm font-medium text-blue-700">Quick Create: "{{ searchQuery }}"</span>
                </div>
            </div>

            <!-- Contact Results -->
            <div
                v-for="contact in filteredContacts"
                :key="contact.id"
                @mousedown.prevent="selectContact(contact)"
                class="px-4 py-2 hover:bg-gray-100 cursor-pointer"
            >
                <div class="font-medium">{{ contact.name }}</div>
                <div class="text-xs text-gray-500">
                    <span v-if="contact.email">{{ contact.email }}</span>
                    <span v-if="contact.phone">
                        <span v-if="contact.email"> • </span>{{ contact.phone }}
                    </span>
                </div>
            </div>
        </div>

        <div v-if="error" class="text-red-500 text-sm mt-1">{{ error }}</div>

        <!-- Quick Create Modal -->
        <div
            v-if="showQuickCreateModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]"
            @click.self="showQuickCreateModal = false"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md" @click.stop>
                <h3 class="text-lg font-semibold mb-4">Quick Create Contact</h3>
                <form @submit.prevent="quickCreateContact">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                            <input
                                v-model="quickCreateForm.name"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input
                                v-model="quickCreateForm.email"
                                type="email"
                                class="w-full rounded border px-3 py-2"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input
                                v-model="quickCreateForm.phone"
                                type="text"
                                class="w-full rounded border px-3 py-2"
                            />
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button
                            type="submit"
                            :disabled="quickCreateForm.processing"
                            class="flex-1 rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:opacity-50"
                        >
                            Create
                        </button>
                        <button
                            type="button"
                            @click="showQuickCreateModal = false"
                            class="flex-1 rounded border px-4 py-2 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
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
    customer_name?: string;
    name: string;
    email?: string | null;
    phone?: string | null;
    is_primary?: boolean;
}

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        customerId: number | null;
        initialContact?: Contact | null;
        label?: string;
        placeholder?: string;
        error?: string;
    }>(),
    {
        initialContact: null,
        label: 'Contact',
        placeholder: 'Search contact by name, email, or phone',
        error: '',
    }
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', contact: Contact | null): void;
}>();

const searchQuery = ref('');
const searchFocused = ref(false);
const filteredContacts = ref<Contact[]>([]);
const searchTimeout = ref<ReturnType<typeof setTimeout> | null>(null);
const showQuickCreateModal = ref(false);

const selectedContact = ref<Contact | null>(null);

const quickCreateForm = ref({
    name: '',
    email: '',
    phone: '',
    processing: false,
});

watch(
    () => [props.modelValue, props.initialContact],
    ([val, initial]) => {
        if (!val) {
            selectedContact.value = null;
            searchQuery.value = '';
        } else if (initial && initial.id === val) {
            selectedContact.value = initial;
            searchQuery.value = initial.name;
        }
    },
    { immediate: true }
);

watch(
    () => props.customerId,
    (val) => {
        if (!val) {
            selectedContact.value = null;
            searchQuery.value = '';
            filteredContacts.value = [];
            emit('update:modelValue', null);
        } else {
            fetchContacts();
        }
    }
);

async function fetchContacts() {
    if (!props.customerId) return;
    try {
        const params: Record<string, string> = { customer_id: String(props.customerId) };
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        const { data } = await axios.get('/api/contacts/search', { params });
        filteredContacts.value = data.contacts || [];
    } catch {
        filteredContacts.value = [];
    }
}

function handleSearch() {
    if (searchTimeout.value) clearTimeout(searchTimeout.value);
    searchTimeout.value = setTimeout(() => {
        if (props.customerId) fetchContacts();
        else filteredContacts.value = [];
    }, 200);
}

function handleBlur() {
    setTimeout(() => {
        searchFocused.value = false;
    }, 150);
}

function selectContact(contact: Contact) {
    selectedContact.value = contact;
    searchQuery.value = contact.name;
    emit('update:modelValue', contact.id);
    emit('select', contact);
    searchFocused.value = false;
}

function clearContact() {
    selectedContact.value = null;
    searchQuery.value = '';
    emit('update:modelValue', null);
    emit('select', null);
}

async function quickCreateContact() {
    if (!props.customerId) return;
    quickCreateForm.value.processing = true;
    try {
        const { data } = await axios.post('/api/contacts/quick-create', {
            customer_id: props.customerId,
            name: quickCreateForm.value.name,
            email: quickCreateForm.value.email || null,
            phone: quickCreateForm.value.phone || null,
        });
        const contact = data.contact;
        showQuickCreateModal.value = false;
        quickCreateForm.value = { name: '', email: '', phone: '', processing: false };
        selectContact(contact);
    } catch {
        quickCreateForm.value.processing = false;
    }
}

// Initialize quick create form name from search when opening
watch(showQuickCreateModal, (open) => {
    if (open) {
        quickCreateForm.value.name = searchQuery.value || '';
    }
});
</script>
