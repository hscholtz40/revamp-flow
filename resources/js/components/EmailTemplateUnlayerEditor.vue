<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue';

type UnlayerDesign = Record<string, unknown>;

interface UnlayerExportData {
    design: UnlayerDesign;
    html: string;
}

interface UnlayerEditorInstance {
    loadDesign: (design: UnlayerDesign) => void;
    exportHtml: (callback: (data: UnlayerExportData) => void) => void;
    addEventListener: (event: string, callback: () => void) => void;
    registerCallback?: (event: string, callback: (file: unknown, done: (result: unknown) => void) => void) => void;
}

interface UnlayerEditorRef {
    editor: UnlayerEditorInstance;
}

const EmailEditor = defineAsyncComponent({
    loader: async () => {
        const module = await import('vue-email-editor');
        return (module as { default?: unknown; EmailEditor?: unknown }).default
            ?? (module as { default?: unknown; EmailEditor?: unknown }).EmailEditor
            ?? module;
    },
    suspensible: false,
});

const DESIGN_MARKER_PREFIX = '<!-- unlayer-design:';
const DESIGN_MARKER_SUFFIX = ' -->';

const props = defineProps<{
    modelValue: string;
    imageUploadUrl?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
}>();

const isClient = ref(false);
const activeTab = ref<'designer' | 'html'>('designer');
const emailEditorRef = ref<UnlayerEditorRef | null>(null);
const isReady = ref(false);
const editorLoadError = ref(false);
const manualHtml = ref('');
const pendingDesign = ref<UnlayerDesign | null>(null);

const uploadUrl = computed(() => props.imageUploadUrl || '/administration/email-templates/upload-image');

const wrapWithDesignMarker = (design: UnlayerDesign, html: string) => {
    const encoded = encodeURIComponent(JSON.stringify(design));
    return `${DESIGN_MARKER_PREFIX}${encoded}${DESIGN_MARKER_SUFFIX}\n${html}`;
};

const unwrapDesignMarker = (input: string) => {
    if (!input?.startsWith(DESIGN_MARKER_PREFIX)) {
        return { design: null as UnlayerDesign | null, html: input || '' };
    }

    const endIndex = input.indexOf(DESIGN_MARKER_SUFFIX);
    if (endIndex === -1) {
        return { design: null as UnlayerDesign | null, html: input || '' };
    }

    const encoded = input.slice(DESIGN_MARKER_PREFIX.length, endIndex).trim();
    const html = input.slice(endIndex + DESIGN_MARKER_SUFFIX.length).replace(/^\n/, '');

    try {
        const decoded = decodeURIComponent(encoded);
        return {
            design: JSON.parse(decoded) as UnlayerDesign,
            html,
        };
    } catch (_error) {
        return { design: null as UnlayerDesign | null, html: input || '' };
    }
};

const syncFromEditor = () => {
    const editor = emailEditorRef.value?.editor;
    if (!editor || !isReady.value) {
        return;
    }

    editor.exportHtml((data) => {
        manualHtml.value = data.html || '';
        emit('update:modelValue', wrapWithDesignMarker(data.design || {}, data.html || ''));
    });
};

const findFile = (payload: unknown): File | null => {
    if (!payload || typeof payload !== 'object') {
        return null;
    }

    const direct = (payload as { file?: File }).file;
    if (direct instanceof File) {
        return direct;
    }

    const attachments = (payload as { attachments?: unknown[] }).attachments;
    if (Array.isArray(attachments)) {
        const first = attachments.find((item) => item instanceof File);
        if (first instanceof File) {
            return first;
        }
    }

    return null;
};

const registerImageUpload = () => {
    const editor = emailEditorRef.value?.editor;
    if (!editor?.registerCallback) {
        return;
    }

    editor.registerCallback('image', async (payload: unknown, done: (result: unknown) => void) => {
        const imageFile = findFile(payload);
        if (!imageFile) {
            done({ progress: 100, url: '' });
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const formData = new FormData();
            formData.append('image', imageFile);

            const response = await fetch(uploadUrl.value, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json();
            const src = data?.data?.[0]?.src || '';
            done({ progress: 100, url: src });
        } catch (_error) {
            done({ progress: 100, url: '' });
        }
    });
};

const onEditorReady = () => {
    isReady.value = true;
    editorLoadError.value = false;
    registerImageUpload();

    const editor = emailEditorRef.value?.editor;
    if (!editor) {
        return;
    }

    if (pendingDesign.value) {
        editor.loadDesign(pendingDesign.value);
    }

    editor.addEventListener('design:updated', () => {
        syncFromEditor();
    });
};

watch(
    () => props.modelValue,
    (newValue) => {
        const parsed = unwrapDesignMarker(newValue || '');
        pendingDesign.value = parsed.design;
        manualHtml.value = parsed.html;

        // Keep designer in sync when modelValue changes after initial mount
        const editor = emailEditorRef.value?.editor;
        if (isReady.value && editor && parsed.design) {
            editor.loadDesign(parsed.design);
        }
    },
    { immediate: true },
);

watch(
    () => manualHtml.value,
    (newHtml) => {
        if (activeTab.value === 'html') {
            emit('update:modelValue', newHtml || '');
        }
    },
);

onMounted(() => {
    isClient.value = true;
});
</script>

<template>
    <div class="space-y-3">
        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="activeTab = 'designer'"
                :class="[
                    'rounded-md px-3 py-1.5 text-xs font-medium',
                    activeTab === 'designer' ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700',
                ]"
            >
                Designer
            </button>
            <button
                type="button"
                @click="activeTab = 'html'"
                :class="[
                    'rounded-md px-3 py-1.5 text-xs font-medium',
                    activeTab === 'html' ? 'bg-blue-600 text-white' : 'border border-gray-300 bg-white text-gray-700',
                ]"
            >
                Manual HTML
            </button>
            <button
                v-if="activeTab === 'designer'"
                type="button"
                @click="syncFromEditor"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
            >
                Sync Designer HTML
            </button>
        </div>

        <div v-if="activeTab === 'designer'" class="h-[680px] overflow-hidden rounded-md border border-gray-200">
            <component
                :is="EmailEditor"
                v-if="isClient && !editorLoadError"
                ref="emailEditorRef"
                :min-height="680"
                class="h-full"
                @load="onEditorReady"
                @ready="onEditorReady"
                @error="editorLoadError = true"
            />
            <div v-else-if="!isClient" class="flex h-[680px] items-center justify-center text-sm text-gray-500">Loading editor...</div>
            <div v-else class="flex h-[680px] flex-col items-center justify-center gap-2 p-6 text-center text-sm text-gray-600">
                <p>Email designer failed to load.</p>
                <p class="text-xs text-gray-500">You can continue using the Manual HTML tab while we retry.</p>
            </div>
        </div>

        <div v-else>
            <textarea
                v-model="manualHtml"
                rows="24"
                class="w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                placeholder="<p>Write your email HTML here...</p>"
            ></textarea>
        </div>
    </div>
</template>
