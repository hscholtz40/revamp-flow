<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import { getSafeExternalUrl } from '@/composables/useSafeExternalUrl';
import { getCsrfToken } from '@/lib/csrf';

interface NoteUser {
    id: number;
    name: string;
}

interface NoteItem {
    id: number;
    subject: string;
    description?: string | null;
    created_at: string;
    user?: NoteUser | null;
    attachment_original_name?: string | null;
    attachment_size?: number | null;
    attachment_url?: string | null;
}

interface RecordOption {
    id: number;
    label: string;
}

interface ModuleOption {
    value: string;
    label: string;
}

interface NotesResponse {
    data: NoteItem[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    module?: string | null;
    recordId?: number | null;
    title?: string;
    showModuleSelector?: boolean;
    modules?: ModuleOption[];
}

const props = withDefaults(defineProps<Props>(), {
    module: null,
    recordId: null,
    title: 'Notes',
    showModuleSelector: false,
    modules: () => [],
});

const notes = ref<NoteItem[]>([]);
const loading = ref(false);
const saving = ref(false);
const showCreateModal = ref(false);
const search = ref('');
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const errorMessage = ref('');

const formModule = ref<string>(props.module || '');
const formRecordId = ref<number | null>(props.recordId || null);
const formSubject = ref('');
const formDescription = ref('');
const formAttachment = ref<File | null>(null);
const recordSearch = ref('');
const recordResults = ref<RecordOption[]>([]);
const searchingRecords = ref(false);

const canLoadNotes = computed(() => !!formModule.value && !!formRecordId.value);
const isContextLocked = computed(() => !props.showModuleSelector && !!props.module && !!props.recordId);
const { formatDateTime } = useDateTimeFormat();

const fileSize = (size?: number | null) => {
    const value = Number(size || 0);
    if (!value) return '';
    if (value >= 1024 * 1024) return `${(value / (1024 * 1024)).toFixed(2)} MB`;
    if (value >= 1024) return `${(value / 1024).toFixed(1)} KB`;
    return `${value} B`;
};

const formatDate = (value: string) => {
    return formatDateTime(value);
};

const getSafeAttachmentUrl = (url?: string | null) => getSafeExternalUrl(url);

const resetForm = () => {
    formSubject.value = '';
    formDescription.value = '';
    formAttachment.value = null;
    if (!isContextLocked.value) {
        formRecordId.value = null;
        recordSearch.value = '';
    }
};

const fetchNotes = async (targetPage = 1) => {
    if (!canLoadNotes.value) {
        notes.value = [];
        total.value = 0;
        page.value = 1;
        lastPage.value = 1;
        return;
    }

    loading.value = true;
    errorMessage.value = '';
    try {
        const params = new URLSearchParams({
            module: formModule.value,
            record_id: String(formRecordId.value),
            page: String(targetPage),
            per_page: '10',
        });
        if (search.value.trim()) {
            params.set('search', search.value.trim());
        }
        const response = await fetch(`/notes/record?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('Failed to load notes');
        }

        const payload: NotesResponse = await response.json();
        notes.value = payload.data || [];
        page.value = payload.current_page || 1;
        lastPage.value = payload.last_page || 1;
        total.value = payload.total || 0;
    } catch (error) {
        errorMessage.value = 'Unable to load notes right now.';
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const submitNote = async () => {
    if (!formModule.value || !formRecordId.value || !formSubject.value.trim()) {
        errorMessage.value = 'Subject and related record are required.';
        return;
    }

    saving.value = true;
    errorMessage.value = '';

    try {
        const payload = new FormData();
        payload.append('module', formModule.value);
        payload.append('record_id', String(formRecordId.value));
        payload.append('subject', formSubject.value.trim());
        payload.append('description', formDescription.value.trim());
        if (formAttachment.value) {
            payload.append('attachment', formAttachment.value);
        }

        const response = await fetch('/notes', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                Accept: 'application/json',
            },
            body: payload,
        });

        if (!response.ok) {
            throw new Error('Unable to create note');
        }

        resetForm();
        showCreateModal.value = false;
        await fetchNotes(1);
    } catch (error) {
        errorMessage.value = 'Unable to save note.';
        console.error(error);
    } finally {
        saving.value = false;
    }
};

let searchDebounce: number | undefined;
watch(search, () => {
    window.clearTimeout(searchDebounce);
    searchDebounce = window.setTimeout(() => fetchNotes(1), 250);
});

watch(
    () => [props.module, props.recordId],
    () => {
        if (props.module) formModule.value = props.module;
        if (props.recordId) formRecordId.value = props.recordId;
        fetchNotes(1);
    }
);

watch(formModule, () => {
    if (!isContextLocked.value) {
        formRecordId.value = null;
        recordSearch.value = '';
        recordResults.value = [];
    }
});

let recordDebounce: number | undefined;
watch(recordSearch, () => {
    if (isContextLocked.value || !formModule.value || !recordSearch.value.trim()) {
        recordResults.value = [];
        return;
    }
    window.clearTimeout(recordDebounce);
    recordDebounce = window.setTimeout(async () => {
        searchingRecords.value = true;
        try {
            const params = new URLSearchParams({
                module: formModule.value,
                q: recordSearch.value.trim(),
            });
            const response = await fetch(`/notes/related-records?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            recordResults.value = response.ok ? await response.json() : [];
        } finally {
            searchingRecords.value = false;
        }
    }, 250);
});

onMounted(() => {
    fetchNotes(1);
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900">{{ title }}</h3>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">{{ total }} total</span>
                    <button
                        type="button"
                        class="rounded bg-blue-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                        @click="showCreateModal = true"
                    >
                        Create Note
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-4 p-4">
            <div>
                <div class="mb-3 flex items-center gap-2">
                    <input
                        v-model="search"
                        type="text"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Search notes..."
                    />
                </div>

                <div v-if="loading" class="py-4 text-sm text-gray-500">Loading notes...</div>
                <div v-else-if="notes.length === 0" class="py-4 text-sm text-gray-500">No notes yet.</div>
                <div v-else class="space-y-3">
                    <div v-for="note in notes" :key="note.id" class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="flex items-start justify-between gap-2">
                            <h4 class="text-sm font-semibold text-gray-900">{{ note.subject }}</h4>
                            <span class="text-xs text-gray-500">{{ formatDate(note.created_at) }}</span>
                        </div>
                        <p v-if="note.description" class="mt-1 whitespace-pre-wrap text-sm text-gray-700">{{ note.description }}</p>
                        <div class="mt-2 flex items-center justify-between gap-2 text-xs text-gray-500">
                            <span>By {{ note.user?.name || 'System' }}</span>
                            <a
                                v-if="getSafeAttachmentUrl(note.attachment_url)"
                                :href="getSafeAttachmentUrl(note.attachment_url) || '#'"
                                rel="noopener noreferrer"
                                class="text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                {{ note.attachment_original_name || 'Download attachment' }}
                                <span v-if="note.attachment_size">({{ fileSize(note.attachment_size) }})</span>
                            </a>
                        </div>
                    </div>
                </div>

                <div v-if="lastPage > 1" class="mt-4 flex items-center justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="page <= 1"
                        @click="fetchNotes(page - 1)"
                    >
                        Previous
                    </button>
                    <span class="text-xs text-gray-600">Page {{ page }} of {{ lastPage }}</span>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        :disabled="page >= lastPage"
                        @click="fetchNotes(page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <div v-if="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto bg-gray-600 bg-opacity-50">
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="w-full max-w-2xl rounded-lg border border-gray-200 bg-white shadow-lg">
                    <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
                        <h4 class="text-lg font-semibold text-gray-900">Create Note</h4>
                        <p class="mt-1 text-sm text-gray-600">Add a note linked to this record</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div class="grid gap-3 md:grid-cols-2">
                            <div v-if="showModuleSelector">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Related Module</label>
                                <select v-model="formModule" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select module</option>
                                    <option v-for="m in modules" :key="m.value" :value="m.value">
                                        {{ m.label }}
                                    </option>
                                </select>
                            </div>
                            <div v-if="showModuleSelector">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Related Record</label>
                                <input
                                    v-model="recordSearch"
                                    type="text"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Search record"
                                />
                                <div v-if="searchingRecords" class="mt-1 text-xs text-gray-500">Searching...</div>
                                <div v-if="recordResults.length > 0" class="mt-1 max-h-36 overflow-auto rounded-md border border-gray-200 bg-white shadow-sm">
                                    <button
                                        v-for="record in recordResults"
                                        :key="record.id"
                                        type="button"
                                        class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50"
                                        @click="formRecordId = record.id; recordSearch = record.label; recordResults = []"
                                    >
                                        {{ record.label }}
                                    </button>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Subject</label>
                                <input
                                    v-model="formSubject"
                                    type="text"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Subject"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                                <textarea
                                    v-model="formDescription"
                                    rows="4"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Description"
                                />
                            </div>
                            <div class="md:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Attachment</label>
                                <input
                                    type="file"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200"
                                    @change="formAttachment = ($event.target as HTMLInputElement)?.files?.[0] || null"
                                />
                            </div>
                        </div>
                        <div v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</div>
                    </div>
                    <div class="flex items-center justify-end gap-3 border-t border-gray-200 px-6 py-4">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            @click="showCreateModal = false"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                            :disabled="saving"
                            @click="submitNote"
                        >
                            {{ saving ? 'Saving...' : 'Create Note' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
