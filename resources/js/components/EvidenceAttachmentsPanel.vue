<script setup lang="ts">
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import ResolvedLocationDisplay from '@/components/ResolvedLocationDisplay.vue';
import { getCsrfToken } from '@/lib/csrf';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';

export interface EvidenceAttachment {
    id: number;
    url: string;
    type: string | null;
    original_name: string | null;
    description: string | null;
    latitude?: number | string | null;
    longitude?: number | string | null;
    location_accuracy?: number | string | null;
    has_location?: boolean;
    created_at?: string | null;
}

const props = withDefaults(
    defineProps<{
        attachments: EvidenceAttachment[];
        canEdit: boolean;
        storeUrl: string;
        deleteUrl: (attachmentId: number) => string;
        updateUrl: (attachmentId: number) => string;
        title?: string;
        subtitle?: string;
        emptyMessage?: string;
        reloadOnly?: string[];
    }>(),
    {
        title: 'Photos & Videos',
        subtitle: 'Photo and video evidence for this record',
        emptyMessage: 'No photos or videos attached yet.',
        reloadOnly: () => ['attachments'],
    },
);

const { formatDateTime } = useDateTimeFormat();
const attachmentInput = ref<HTMLInputElement | null>(null);
const uploadingAttachments = ref(false);
const attachmentUploadError = ref('');
const pendingDescription = ref('');
const draftDescriptions = ref<Record<number, string>>({});
const savingDescriptionId = ref<number | null>(null);

const attachments = computed(() => props.attachments ?? []);

function attachmentKind(type: string | null): 'video' | 'image' {
    if (!type) return 'image';
    if (type === 'video' || type.endsWith(':video') || type.startsWith('video/')) return 'video';
    return 'image';
}

function uploadedAtLabel(createdAt: string | null | undefined): string | null {
    if (!createdAt) {
        return null;
    }

    const formatted = formatDateTime(createdAt);
    return formatted ? `Uploaded ${formatted}` : null;
}

function hasLocation(attachment: EvidenceAttachment): boolean {
    if (attachment.has_location) {
        return true;
    }

    return attachment.latitude != null && attachment.longitude != null;
}

function descriptionDraft(attachment: EvidenceAttachment): string {
    if (Object.prototype.hasOwnProperty.call(draftDescriptions.value, attachment.id)) {
        return draftDescriptions.value[attachment.id] ?? '';
    }
    return attachment.description ?? '';
}

function setDescriptionDraft(attachmentId: number, value: string) {
    draftDescriptions.value = {
        ...draftDescriptions.value,
        [attachmentId]: value,
    };
}

async function readBrowserLocation(): Promise<{
    latitude: number;
    longitude: number;
    location_accuracy: number | null;
} | null> {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
        return null;
    }

    try {
        const position = await new Promise<GeolocationPosition>((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 8000,
                maximumAge: 60000,
            });
        });

        return {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            location_accuracy: Number.isFinite(position.coords.accuracy) ? position.coords.accuracy : null,
        };
    } catch {
        return null;
    }
}

async function onAttachmentFilesSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files || []);
    input.value = '';
    if (files.length === 0) return;

    uploadingAttachments.value = true;
    attachmentUploadError.value = '';
    try {
        const formData = new FormData();
        files.forEach((file) => formData.append('attachments[]', file));
        if (pendingDescription.value.trim() !== '') {
            formData.append('description', pendingDescription.value.trim());
        }

        const location = await readBrowserLocation();
        if (location) {
            formData.append('latitude', String(location.latitude));
            formData.append('longitude', String(location.longitude));
            if (location.location_accuracy != null) {
                formData.append('location_accuracy', String(location.location_accuracy));
            }
        }

        const response = await fetch(props.storeUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = (data as { message?: string; errors?: Record<string, string[]> }).message
                || Object.values((data as { errors?: Record<string, string[]> }).errors || {}).flat()[0]
                || 'Upload failed.';
            throw new Error(message);
        }

        pendingDescription.value = '';
        toast.success(location ? 'Attachments uploaded with location' : 'Attachments uploaded');
        router.reload({ only: props.reloadOnly });
    } catch (error) {
        attachmentUploadError.value = error instanceof Error ? error.message : 'Upload failed.';
        toast.error(attachmentUploadError.value);
    } finally {
        uploadingAttachments.value = false;
    }
}

function deleteAttachment(attachmentId: number) {
    if (!confirm('Remove this attachment?')) return;

    router.delete(props.deleteUrl(attachmentId), {
        preserveScroll: true,
        onSuccess: () => toast.success('Attachment removed'),
        onError: () => toast.error('Could not remove attachment'),
    });
}

async function saveDescription(attachment: EvidenceAttachment) {
    const description = descriptionDraft(attachment).trim();
    savingDescriptionId.value = attachment.id;
    attachmentUploadError.value = '';

    try {
        const response = await fetch(props.updateUrl(attachment.id), {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ description: description === '' ? null : description }),
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = (data as { message?: string; errors?: Record<string, string[]> }).message
                || Object.values((data as { errors?: Record<string, string[]> }).errors || {}).flat()[0]
                || 'Could not save description.';
            throw new Error(message);
        }

        toast.success('Description saved');
        router.reload({ only: props.reloadOnly });
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Could not save description.';
        attachmentUploadError.value = message;
        toast.error(message);
    } finally {
        savingDescriptionId.value = null;
    }
}
</script>

<template>
    <div class="rounded-lg bg-white border border-gray-200 shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ title }}</h2>
                    <p class="text-sm text-gray-600">{{ subtitle }}</p>
                </div>
                <div v-if="canEdit" class="flex items-center gap-2">
                    <input
                        ref="attachmentInput"
                        type="file"
                        class="hidden"
                        accept="image/*,video/mp4,video/quicktime,video/webm,video/x-msvideo,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm"
                        multiple
                        @change="onAttachmentFilesSelected"
                    />
                    <button
                        type="button"
                        class="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        :disabled="uploadingAttachments"
                        @click="attachmentInput?.click()"
                    >
                        {{ uploadingAttachments ? 'Uploading…' : 'Add photo/video' }}
                    </button>
                </div>
            </div>
        </div>
        <div class="p-6">
            <div v-if="canEdit" class="mb-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Description (optional)</label>
                <textarea
                    v-model="pendingDescription"
                    rows="2"
                    maxlength="1000"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    placeholder="Describe the evidence you are about to upload"
                />
                <p class="mt-1 text-xs text-gray-500">Applied to each file in the next upload. Location is captured from this device when permission is granted.</p>
            </div>

            <p v-if="attachmentUploadError" class="mb-3 text-sm text-red-600">{{ attachmentUploadError }}</p>

            <div v-if="attachments.length === 0" class="rounded-md border border-dashed border-gray-300 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                {{ emptyMessage }}
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="attachment in attachments" :key="attachment.id" class="space-y-2 rounded-md border border-gray-200 p-3">
                    <video
                        v-if="attachmentKind(attachment.type) === 'video'"
                        :src="attachment.url"
                        controls
                        class="max-h-[280px] w-full rounded-md bg-black"
                    />
                    <img
                        v-else
                        :src="attachment.url"
                        :alt="attachment.original_name || 'Evidence attachment'"
                        class="max-h-[280px] w-full rounded-md object-contain bg-gray-50"
                    />
                    <div class="flex items-center justify-between gap-2">
                        <a :href="attachment.url" target="_blank" class="truncate text-sm text-blue-600 hover:underline">
                            {{ attachment.original_name || 'Open' }}
                        </a>
                        <button
                            v-if="canEdit"
                            type="button"
                            class="shrink-0 text-xs font-medium text-red-600 hover:text-red-800"
                            @click="deleteAttachment(attachment.id)"
                        >
                            Remove
                        </button>
                    </div>
                    <p v-if="uploadedAtLabel(attachment.created_at)" class="text-xs text-gray-500">
                        {{ uploadedAtLabel(attachment.created_at) }}
                    </p>
                    <p class="text-xs text-gray-500">
                        <ResolvedLocationDisplay
                            v-if="hasLocation(attachment)"
                            prefix="Location: "
                            :latitude="attachment.latitude"
                            :longitude="attachment.longitude"
                            :accuracy="attachment.location_accuracy"
                            text-class="text-xs text-gray-500"
                        />
                        <span v-else>Location: Not recorded</span>
                    </p>
                    <div v-if="canEdit" class="space-y-2">
                        <textarea
                            :value="descriptionDraft(attachment)"
                            rows="2"
                            maxlength="1000"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Add a description"
                            @input="setDescriptionDraft(attachment.id, ($event.target as HTMLTextAreaElement).value)"
                        />
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                            :disabled="savingDescriptionId === attachment.id || descriptionDraft(attachment).trim() === (attachment.description ?? '').trim()"
                            @click="saveDescription(attachment)"
                        >
                            {{ savingDescriptionId === attachment.id ? 'Saving…' : 'Save description' }}
                        </button>
                    </div>
                    <p v-else-if="attachment.description" class="text-sm text-gray-600 whitespace-pre-wrap">
                        {{ attachment.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
