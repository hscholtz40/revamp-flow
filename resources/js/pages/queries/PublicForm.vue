<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { CheckCircle2, AlertCircle, X, FileImage, FileVideo } from 'lucide-vue-next';

interface Props {
    company: {
        id: number;
        name: string;
        logo_path: string | null;
    };
}

const props = defineProps<Props>();
const page = usePage();

const successMessage = computed(() => (page.props.flash as { success?: string } | undefined)?.success ?? '');

const errorMessage = ref<string>('');
const MAX_FILES = 10;

const form = useForm<{
    company_id: number;
    name: string;
    surname: string;
    email: string;
    cell: string;
    description: string;
    attachments: File[];
}>({
    company_id: props.company.id,
    name: '',
    surname: '',
    email: '',
    cell: '',
    description: '',
    attachments: [],
});

// Aggregate any per-file validation errors (attachments, attachments.0, ...).
const attachmentError = computed(() => {
    const errors = form.errors as Record<string, string>;
    const key = Object.keys(errors).find((k) => k === 'attachments' || k.startsWith('attachments.'));
    return key ? errors[key] : '';
});

function formatSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (!target.files) return;

    const incoming = Array.from(target.files);
    for (const file of incoming) {
        if (form.attachments.length >= MAX_FILES) {
            errorMessage.value = `You can upload a maximum of ${MAX_FILES} files.`;
            break;
        }
        // Skip exact duplicates (same name + size).
        const isDuplicate = form.attachments.some((f) => f.name === file.name && f.size === file.size);
        if (!isDuplicate) {
            form.attachments.push(file);
        }
    }

    // Reset the input so selecting the same file again still fires change.
    target.value = '';
}

function removeFile(index: number) {
    form.attachments.splice(index, 1);
}

const submit = () => {
    errorMessage.value = '';
    form.post('/submit-query', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'surname', 'email', 'cell', 'description', 'attachments');
        },
        onError: (errors) => {
            errorMessage.value = Object.keys(errors).length
                ? 'Please correct the highlighted fields and try again.'
                : 'Something went wrong while submitting your query. Please try again.';
        },
    });
};
</script>

<template>
    <Head :title="`Submit a Query — ${company.name}`" />
    <AuthBase title="Submit a Query" :description="`Send your enquiry to ${company.name} and we'll get back to you.`">
        <div
            v-if="successMessage"
            class="mb-4 flex items-start gap-2 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800"
        >
            <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0" />
            <span>{{ successMessage }}</span>
        </div>

        <div
            v-if="errorMessage"
            class="mb-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"
        >
            <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <form class="space-y-4 text-slate-700" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-1">
                    <Label for="name">First name</Label>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="space-y-1">
                    <Label for="surname">Surname</Label>
                    <Input id="surname" v-model="form.surname" required />
                    <InputError :message="form.errors.surname" />
                </div>
            </div>

            <div class="space-y-1">
                <Label for="email">Email</Label>
                <Input id="email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>

            <div class="space-y-1">
                <Label for="cell">Cell number</Label>
                <Input id="cell" v-model="form.cell" type="tel" required />
                <InputError :message="form.errors.cell" />
            </div>

            <div class="space-y-1">
                <Label for="description">Description of your query</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="5"
                    required
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                    placeholder="Tell us how we can help..."
                ></textarea>
                <InputError :message="form.errors.description" />
            </div>

            <div class="space-y-1">
                <Label for="attachments">Attach pictures or videos (optional)</Label>
                <input
                    id="attachments"
                    type="file"
                    accept="image/*,video/*"
                    multiple
                    class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-700"
                    @change="onFileChange"
                />
                <p class="text-xs text-slate-500">You can add up to {{ MAX_FILES }} files (images or videos, max 50MB each).</p>

                <ul v-if="form.attachments.length" class="mt-2 space-y-1">
                    <li
                        v-for="(file, index) in form.attachments"
                        :key="`${file.name}-${index}`"
                        class="flex items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                    >
                        <span class="flex min-w-0 items-center gap-2">
                            <component :is="file.type.startsWith('video/') ? FileVideo : FileImage" class="h-4 w-4 shrink-0 text-slate-400" />
                            <span class="truncate">{{ file.name }}</span>
                            <span class="shrink-0 text-xs text-slate-400">({{ formatSize(file.size) }})</span>
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-700"
                            aria-label="Remove file"
                            @click="removeFile(index)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </li>
                </ul>

                <p v-if="form.progress" class="text-xs text-slate-500">
                    Uploading… {{ form.progress.percentage }}%
                </p>
                <InputError :message="attachmentError" />
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                {{ form.processing ? 'Submitting…' : 'Submit Query' }}
            </Button>
        </form>
    </AuthBase>
</template>
