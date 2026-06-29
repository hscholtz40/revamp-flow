<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    title: string;
    sendUrl: string | null;
}>();

const emit = defineEmits<{
    (event: 'close'): void;
    (event: 'sent'): void;
}>();

const form = useForm({
    subject: '',
    body: '',
});
const formMessageError = () => (form.errors as Record<string, string | undefined>).message;

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }
        form.reset();
        form.clearErrors();
        form.subject = '';
        form.body = '';
    },
);

const close = () => {
    emit('close');
};

const submit = () => {
    if (!props.sendUrl) {
        return;
    }
    form.post(props.sendUrl, {
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
        <div class="mx-4 w-full max-w-lg rounded-lg bg-white p-6 shadow-lg">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700" @click="close">Close</button>
            </div>

            <form @submit.prevent="submit">
                <div class="mb-4">
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

                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Body</label>
                    <textarea
                        v-model="form.body"
                        rows="8"
                        class="w-full rounded border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Email message"
                        required
                    />
                    <div v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</div>
                    <div v-if="formMessageError()" class="mt-1 text-sm text-red-600">{{ formMessageError() }}</div>
                </div>

                <div class="flex items-center justify-end gap-3">
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
