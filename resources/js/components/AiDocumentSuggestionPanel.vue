<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getCsrfToken } from '@/lib/csrf';

const props = defineProps<{
    documentType: 'quote' | 'invoice' | 'jobcard';
    title?: string;
    description?: string;
    notes?: string;
}>();

const page = usePage();
const aiAllowed = computed(() => !!(page.props as { ai?: { allowed?: boolean } }).ai?.allowed);
const loading = ref(false);
const result = ref<null | {
    rationale?: string;
    pdf_template?: { name?: string; reason?: string } | null;
    email_template?: { name?: string; reason?: string } | null;
}>(null);

const suggest = async () => {
    loading.value = true;
    const response = await fetch('/ai/suggest-document', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({
            document_type: props.documentType,
            title: props.title || '',
            description: props.description || '',
            notes: props.notes || '',
        }),
    });
    loading.value = false;
    if (!response.ok) return;
    const payload = (await response.json()) as { suggestion?: typeof result.value };
    result.value = payload.suggestion || null;
};
</script>

<template>
    <div v-if="aiAllowed" class="rounded-md border border-violet-200 bg-violet-50/50 p-3">
        <div class="mb-2 flex items-center justify-between">
            <p class="text-xs font-semibold text-violet-900">AI Template Suggestions</p>
            <button
                type="button"
                class="rounded bg-violet-600 px-2 py-1 text-xs font-medium text-white hover:bg-violet-700 disabled:opacity-60"
                :disabled="loading"
                @click="suggest"
            >
                {{ loading ? 'Checking…' : 'Suggest' }}
            </button>
        </div>
        <p v-if="result?.rationale" class="text-xs text-violet-900">{{ result.rationale }}</p>
        <div v-if="result?.pdf_template" class="mt-1 text-xs text-violet-800">
            PDF: <span class="font-semibold">{{ result.pdf_template.name }}</span> — {{ result.pdf_template.reason }}
        </div>
        <div v-if="result?.email_template" class="mt-1 text-xs text-violet-800">
            Email: <span class="font-semibold">{{ result.email_template.name }}</span> — {{ result.email_template.reason }}
        </div>
    </div>
</template>
