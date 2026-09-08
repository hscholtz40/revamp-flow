<script setup lang="ts">
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';
import ResolvedLocationDisplay from '@/components/ResolvedLocationDisplay.vue';
import { getCsrfToken } from '@/lib/csrf';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
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
const lightboxAttachment = ref<EvidenceAttachment | null>(null);

const attachments = computed(() => props.attachments ?? []);

function openLightbox(attachment: EvidenceAttachment) {
    lightboxAttachment.value = attachment;
}

function closeLightbox() {
    lightboxAttachment.value = null;
}

function onLightboxKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        closeLightbox();
    }
}

watch(lightboxAttachment, (attachment) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (attachment) {
        window.addEventListener('keydown', onLightboxKeydown);
        document.body.classList.add('overflow-hidden');
    } else {
        window.removeEventListener('keydown', onLightboxKeydown);
        document.body.classList.remove('overflow-hidden');
    }
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }
    window.removeEventListener('keydown', onLightboxKeydown);
    document.body.classList.remove('overflow-hidden');
});

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
            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <div v-for="attachment in attachments" :key="attachment.id" class="space-y-2 rounded-md border border-gray-200 p-2">
                    <button
                        type="button"
                        class="group relative block w-full overflow-hidden rounded-md bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :title="attachment.original_name || 'Open evidence'"
                        @click="openLightbox(attachment)"
                    >
                        <video
                            v-if="attachmentKind(attachment.type) === 'video'"
                            :src="attachment.url"
                            muted
                            preload="metadata"
                            class="h-28 w-full object-cover"
                        />
                        <img
                            v-else
                            :src="attachment.url"
                            :alt="attachment.original_name || 'Evidence attachment'"
                            class="h-28 w-full object-cover"
                        />
                        <span
                            class="absolute inset-0 flex items-center justify-center bg-black/0 text-xs font-medium text-white opacity-0 transition group-hover:bg-black/35 group-hover:opacity-100"
                        >
                            {{ attachmentKind(attachment.type) === 'video' ? 'Play' : 'Expand' }}
                        </span>
                        <span
                            v-if="attachmentKind(attachment.type) === 'video'"
                            class="absolute bottom-1 left-1 rounded bg-black/70 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white"
                        >
                            Video
                        </span>
                    </button>
                    <div class="flex items-start justify-between gap-2 px-0.5">
                        <button
                            type="button"
                            class="min-w-0 truncate text-left text-xs text-blue-600 hover:underline"
                            @click="openLightbox(attachment)"
                        >
                            {{ attachment.original_name || 'Open' }}
                        </button>
                        <button
                            v-if="canEdit"
                            type="button"
                            class="shrink-0 text-[11px] font-medium text-red-600 hover:text-red-800"
                            @click="deleteAttachment(attachment.id)"
                        >
                            Remove
                        </button>
                    </div>
                    <p v-if="uploadedAtLabel(attachment.created_at)" class="px-0.5 text-[11px] text-gray-500">
                        {{ uploadedAtLabel(attachment.created_at) }}
                    </p>
                    <p class="px-0.5 text-[11px] text-gray-500">
                        <ResolvedLocationDisplay
                            v-if="hasLocation(attachment)"
                            prefix="Location: "
                            :latitude="attachment.latitude"
                            :longitude="attachment.longitude"
                            :accuracy="attachment.location_accuracy"
                            text-class="text-[11px] text-gray-500"
                        />
                        <span v-else>Location: Not recorded</span>
                    </p>
                    <div v-if="canEdit" class="space-y-1.5 px-0.5">
                        <textarea
                            :value="descriptionDraft(attachment)"
                            rows="2"
                            maxlength="1000"
                            class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-xs shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Add a description"
                            @input="setDescriptionDraft(attachment.id, ($event.target as HTMLTextAreaElement).value)"
                        />
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-2 py-1 text-[11px] font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60"
                            :disabled="savingDescriptionId === attachment.id || descriptionDraft(attachment).trim() === (attachment.description ?? '').trim()"
                            @click="saveDescription(attachment)"
                        >
                            {{ savingDescriptionId === attachment.id ? 'Saving…' : 'Save description' }}
                        </button>
                    </div>
                    <p v-else-if="attachment.description" class="px-0.5 text-xs text-gray-600 whitespace-pre-wrap">
                        {{ attachment.description }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="lightboxAttachment"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-black/75 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeLightbox"
            @keydown.escape="closeLightbox"
        >
            <div class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-gray-900">
                            {{ lightboxAttachment.original_name || 'Evidence' }}
                        </p>
                        <p v-if="uploadedAtLabel(lightboxAttachment.created_at)" class="text-xs text-gray-500">
                            {{ uploadedAtLabel(lightboxAttachment.created_at) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a
                            :href="lightboxAttachment.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Open original
                        </a>
                        <button
                            type="button"
                            class="rounded-md bg-gray-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-gray-800"
                            @click="closeLightbox"
                        >
                            Close
                        </button>
                    </div>
                </div>
                <div class="flex min-h-0 flex-1 items-center justify-center bg-gray-950 p-3">
                    <video
                        v-if="attachmentKind(lightboxAttachment.type) === 'video'"
                        :src="lightboxAttachment.url"
                        controls
                        autoplay
                        class="max-h-[75vh] w-full object-contain"
                    />
                    <img
                        v-else
                        :src="lightboxAttachment.url"
                        :alt="lightboxAttachment.original_name || 'Evidence attachment'"
                        class="max-h-[75vh] w-full object-contain"
                    />
                </div>
                <div v-if="lightboxAttachment.description || hasLocation(lightboxAttachment)" class="space-y-1 border-t border-gray-200 px-4 py-3">
                    <p v-if="lightboxAttachment.description" class="text-sm text-gray-700 whitespace-pre-wrap">
                        {{ lightboxAttachment.description }}
                    </p>
                    <p v-if="hasLocation(lightboxAttachment)" class="text-xs text-gray-500">
                        <ResolvedLocationDisplay
                            prefix="Location: "
                            :latitude="lightboxAttachment.latitude"
                            :longitude="lightboxAttachment.longitude"
                            :accuracy="lightboxAttachment.location_accuracy"
                            text-class="text-xs text-gray-500"
                        />
                    </p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
