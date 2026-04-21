<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getCsrfToken } from '@/lib/csrf';

const props = defineProps<{
    modelValue: string;
    context?: string;
    label?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const page = usePage();
const aiAllowed = computed(() => !!(page.props as { ai?: { allowed?: boolean } }).ai?.allowed);
const prompt = ref('');
const loading = ref(false);
const lastDraft = ref('');
const mode = ref<'insert' | 'replace'>('insert');

const runDraft = async () => {
    if (!prompt.value.trim()) return;
    loading.value = true;
    const response = await fetch('/ai/draft', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({
            prompt: prompt.value.trim(),
            context: props.context || '',
        }),
    });
    loading.value = false;
    if (!response.ok) return;
    const data = (await response.json()) as { text?: string };
    lastDraft.value = (data.text || '').trim();
    if (!lastDraft.value) return;
    emit('update:modelValue', mode.value === 'replace' ? lastDraft.value : `${props.modelValue || ''}${props.modelValue ? '\n' : ''}${lastDraft.value}`);
};
</script>

<template>
    <div v-if="aiAllowed" class="rounded-md border border-indigo-200 bg-indigo-50/60 p-2">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs font-semibold text-indigo-900">{{ label || 'AI Draft Helper' }}</p>
            <select v-model="mode" class="rounded border border-indigo-200 bg-white px-2 py-1 text-[11px]">
                <option value="insert">Insert</option>
                <option value="replace">Replace</option>
            </select>
        </div>
        <div class="flex gap-2">
            <input
                v-model="prompt"
                type="text"
                class="flex-1 rounded border border-indigo-200 bg-white px-2 py-1.5 text-xs"
                placeholder="e.g. Draft a concise professional update"
            />
            <button
                type="button"
                class="rounded bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                :disabled="loading || !prompt.trim()"
                @click="runDraft"
            >
                {{ loading ? 'Drafting…' : 'Draft' }}
            </button>
        </div>
    </div>
</template>
