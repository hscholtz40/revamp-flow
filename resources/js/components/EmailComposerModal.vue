<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import EmailTemplateUnlayerEditor from '@/components/EmailTemplateUnlayerEditor.vue';

interface EmailTemplate {
    id: number;
    name: string;
    subject: string;
    html_template?: string | null;
    css_styles?: string | null;
    is_default?: boolean;
}

const DESIGN_MARKER_PREFIX = '<!-- unlayer-design:';
const DESIGN_MARKER_SUFFIX = ' -->';

const props = defineProps<{
    open: boolean;
    title: string;
    sendUrl: string | null;
    templates: EmailTemplate[];
    previewContext?: Record<string, unknown>;
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'sent'): void;
}>();

const form = useForm({
    template_id: null as number | null,
    subject: '',
    body: '',
});

const unwrapDesignMarker = (input: string) => {
    if (!input?.startsWith(DESIGN_MARKER_PREFIX)) {
        return { html: input || '' };
    }

    const endIndex = input.indexOf(DESIGN_MARKER_SUFFIX);
    if (endIndex === -1) {
        return { html: input || '' };
    }

    const html = input.slice(endIndex + DESIGN_MARKER_SUFFIX.length).replace(/^\n/, '');
    return { html };
};

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }
        form.reset();
        form.clearErrors();
        form.template_id = null;
        form.subject = '';
        form.body = '';
    },
);

watch(
    () => form.template_id,
    (templateId) => {
        if (!templateId) {
            return;
        }
        const template = props.templates.find((item) => item.id === templateId);
        if (!template) {
            return;
        }
        form.subject = template.subject || '';
        form.body = template.html_template || '';
    },
);

const close = () => {
    emit('close');
};

const submit = () => {
    if (!props.sendUrl) {
        return;
    }
    form.transform((data) => ({
        ...data,
        body: unwrapDesignMarker(data.body || '').html,
    })).post(props.sendUrl, {
        preserveScroll: true,
        onSuccess: () => {
            emit('sent');
            emit('close');
        },
    });
};
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="mx-4 max-h-[90vh] w-full max-w-7xl overflow-y-auto rounded-lg bg-white p-6">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close">Close</button>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Template (optional)</label>
                        <select
                            v-model="form.template_id"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                            <option :value="null">Manual (no template)</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Select a template to prefill subject/body, or compose manually.</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Subject</label>
                        <input
                            v-model="form.subject"
                            type="text"
                            class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Email subject"
                            required
                        />
                        <div v-if="form.errors.subject" class="mt-1 text-sm text-red-600">{{ form.errors.subject }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700">Body (Visual editor + HTML)</label>
                        <EmailTemplateUnlayerEditor
                            v-model="form.body"
                            image-upload-url="/administration/email-templates/upload-image"
                        />
                        <div v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</div>
                        <div v-if="form.errors.message" class="mt-1 text-sm text-red-600">{{ form.errors.message }}</div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        @click="close"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Sending...' : 'Send Email' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
