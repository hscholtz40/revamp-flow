<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import { getCsrfToken } from '@/lib/csrf';
import { Mic, Square } from 'lucide-vue-next';
import { toast } from 'vue-sonner';

const page = usePage();
const aiAllowed = computed(() => !!(page.props as { ai?: { allowed?: boolean } }).ai?.allowed);
const query = ref('');
const loading = ref(false);
const summary = ref('');
const results = ref<Record<string, Array<Record<string, unknown>>>>({});
const recording = ref(false);
const transcribing = ref(false);
const micAccessHelp = ref<string[] | null>(null);
const voiceSupported = computed(() => typeof window !== 'undefined' && !!(navigator.mediaDevices && window.MediaRecorder));

let mediaRecorder: MediaRecorder | null = null;
let mediaStream: MediaStream | null = null;
const recordedChunks: BlobPart[] = [];

const groupLabel: Record<string, string> = {
    jobcards: 'Jobcards',
    quotes: 'Quotes',
    invoices: 'Invoices',
    tasks: 'Tasks',
    customers: 'Customers',
    contacts: 'Contacts',
};

const recordHref = (group: string, row: Record<string, unknown>) => {
    const id = Number(row.id ?? 0);
    if (!id) return '#';
    if (group === 'jobcards') return `/jobcards/${id}`;
    if (group === 'quotes') return `/quotes/${id}`;
    if (group === 'invoices') return `/invoices/${id}`;
    if (group === 'tasks') return `/tasks/${id}/edit`;
    if (group === 'customers') return `/customers/${id}`;
    if (group === 'contacts') return `/contacts/${id}`;
    return '#';
};

const recordTitle = (group: string, row: Record<string, unknown>) => {
    if (group === 'jobcards') return String(row.job_number || row.title || `Jobcard #${row.id}`);
    if (group === 'quotes') return String(row.quote_number || `Quote #${row.id}`);
    if (group === 'invoices') return String(row.invoice_number || `Invoice #${row.id}`);
    if (group === 'tasks') return String(row.title || `Task #${row.id}`);
    if (group === 'customers' || group === 'contacts') return String(row.name || `Record #${row.id}`);
    return `Record #${row.id ?? '?'}`;
};

const recordMeta = (group: string, row: Record<string, unknown>) => {
    if (group === 'jobcards' || group === 'quotes' || group === 'invoices' || group === 'tasks') {
        return String(row.status || 'No status');
    }
    if (group === 'customers' || group === 'contacts') {
        return String(row.email || 'No email');
    }
    return '';
};

const search = async () => {
    if (!query.value.trim() || !aiAllowed.value) return;
    loading.value = true;
    const response = await fetch('/ai/assistant', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify({ query: query.value.trim() }),
    });
    loading.value = false;
    if (!response.ok) return;
    const data = (await response.json()) as { summary?: string; results?: Record<string, Array<Record<string, unknown>>> };
    summary.value = data.summary || '';
    results.value = data.results || {};
};

function resolveRecorderMimeType(): string {
    const candidates = [
        'audio/webm;codecs=opus',
        'audio/webm',
        'audio/ogg;codecs=opus',
        'audio/mp4',
    ];

    return candidates.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
}

function extensionForMimeType(mimeType: string): string {
    if (mimeType.includes('mp4')) return 'm4a';
    if (mimeType.includes('ogg')) return 'ogg';
    return 'webm';
}

function stopMediaTracks() {
    mediaStream?.getTracks().forEach((track) => track.stop());
    mediaStream = null;
}

function detectBrowser(): 'chrome' | 'edge' | 'firefox' | 'safari' | 'other' {
    const ua = navigator.userAgent;
    if (/Edg\//.test(ua)) return 'edge';
    if (/Firefox\//.test(ua)) return 'firefox';
    if (/Safari\//.test(ua) && !/Chrome\//.test(ua)) return 'safari';
    if (/Chrome\//.test(ua)) return 'chrome';
    return 'other';
}

function isPermissionsPolicyBlock(error: unknown): boolean {
    if (!(error instanceof DOMException)) {
        return false;
    }

    const message = error.message.toLowerCase();

    return message.includes('permissions policy')
        || message.includes('permission policy')
        || message.includes('feature policy');
}

function microphoneEnableSteps(error?: unknown): string[] {
    if (isPermissionsPolicyBlock(error)) {
        return [
            'This site is not currently allowed to use the microphone (browser security policy).',
            'Ask your administrator to allow microphone access in the site security headers.',
            'After that is updated, refresh this page and click Voice note again.',
        ];
    }

    if (error instanceof DOMException && error.name === 'NotAllowedError') {
        const browser = detectBrowser();
        if (browser === 'edge') {
            return [
                'Microphone access was blocked for this site.',
                'Click the lock icon in the address bar (left of the site URL).',
                'Open Site permissions or Permissions for this site.',
                'Set Microphone to Allow.',
                'Click Voice note again. Refresh the page if the option does not appear.',
            ];
        }
        if (browser === 'chrome') {
            return [
                'Microphone access was blocked for this site.',
                'Click the tune or lock icon in the address bar (left of the site URL).',
                'Set Microphone to Allow.',
                'Click Voice note again. Refresh the page if the option does not appear.',
            ];
        }
        if (browser === 'firefox') {
            return [
                'Microphone access was blocked for this site.',
                'Click the microphone icon in the address bar and choose Allow.',
                'If you do not see it, open the site menu (lock icon) → More information → Permissions → Microphone → Allow.',
                'Click Voice note again.',
            ];
        }
        if (browser === 'safari') {
            return [
                'Microphone access was blocked for this site.',
                'In the menu bar, open Safari → Settings for This Website (or Website Settings).',
                'Set Microphone to Allow.',
                'Click Voice note again.',
            ];
        }

        return [
            'Microphone access was blocked for this site.',
            'Open your browser settings for this website and allow microphone access.',
            'Click Voice note again.',
        ];
    }

    if (error instanceof DOMException && error.name === 'NotFoundError') {
        return [
            'No microphone was detected on this device.',
            'Connect a microphone or check that your headset is plugged in.',
            'If you use Bluetooth audio, make sure it is paired and selected as the input device.',
            'Then click Voice note again.',
        ];
    }

    if (error instanceof DOMException && error.name === 'NotReadableError') {
        return [
            'Your microphone could not be opened because another app may be using it.',
            'Close other apps that might be using the microphone (Teams, Zoom, etc.).',
            'Then click Voice note again.',
        ];
    }

    if (error instanceof DOMException && error.name === 'SecurityError') {
        return [
            'Voice notes require a secure connection (HTTPS) or localhost.',
            'Open this app using HTTPS, then click Voice note again.',
        ];
    }

    const browser = detectBrowser();
    if (browser === 'edge') {
        return [
            'Could not start the microphone.',
            'Click the lock icon in the address bar and confirm Microphone is set to Allow.',
            'Click Voice note again. Refresh the page if needed.',
        ];
    }
    if (browser === 'chrome') {
        return [
            'Could not start the microphone.',
            'Click the tune or lock icon in the address bar and confirm Microphone is set to Allow.',
            'Click Voice note again. Refresh the page if needed.',
        ];
    }
    if (browser === 'firefox') {
        return [
            'Could not start the microphone.',
            'Open site permissions and confirm Microphone is set to Allow.',
            'Click Voice note again.',
        ];
    }
    if (browser === 'safari') {
        return [
            'Could not start the microphone.',
            'Open Safari → Settings for This Website and confirm Microphone is set to Allow.',
            'Click Voice note again.',
        ];
    }

    return [
        'Could not start the microphone.',
        'Check that your browser allows microphone access for this site.',
        'Click Voice note again.',
    ];
}

function showMicrophoneAccessHelp(error?: unknown) {
    micAccessHelp.value = microphoneEnableSteps(error);
    const title = isPermissionsPolicyBlock(error)
        ? 'Microphone is blocked by site policy'
        : error instanceof DOMException && error.name === 'NotAllowedError'
          ? 'Microphone access was blocked'
          : 'Could not start voice note';
    toast.error(title);
}

async function startVoiceNote() {
    if (!voiceSupported.value || recording.value || transcribing.value || !aiAllowed.value) return;

    if (!window.isSecureContext) {
        showMicrophoneAccessHelp(new DOMException('Insecure context', 'SecurityError'));
        return;
    }

    micAccessHelp.value = null;

    try {
        mediaStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
                channelCount: 1,
            },
        });
        recordedChunks.length = 0;
        const mimeType = resolveRecorderMimeType();
        const recorderOptions: MediaRecorderOptions = mimeType
            ? { mimeType, audioBitsPerSecond: 128000 }
            : { audioBitsPerSecond: 128000 };
        mediaRecorder = new MediaRecorder(mediaStream, recorderOptions);
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                recordedChunks.push(event.data);
            }
        };
        mediaRecorder.onstop = () => {
            void handleRecordedVoice();
        };
        mediaRecorder.start(250);
        recording.value = true;
        micAccessHelp.value = null;
    } catch (error) {
        stopMediaTracks();
        mediaRecorder = null;
        console.error('Voice note microphone error:', error);
        showMicrophoneAccessHelp(error);
    }
}

function stopVoiceNote() {
    if (!mediaRecorder || mediaRecorder.state === 'inactive') {
        recording.value = false;
        stopMediaTracks();
        return;
    }
    if (mediaRecorder.state === 'recording') {
        mediaRecorder.requestData();
    }
    mediaRecorder.stop();
    recording.value = false;
}

async function handleRecordedVoice() {
    const mimeType = mediaRecorder?.mimeType || resolveRecorderMimeType() || 'audio/webm';
    const blob = new Blob(recordedChunks, { type: mimeType });
    stopMediaTracks();
    mediaRecorder = null;
    recordedChunks.length = 0;

    if (blob.size < 1000) {
        toast.error('Voice note was too short. Please try again.');
        return;
    }

    transcribing.value = true;
    try {
        const formData = new FormData();
        formData.append('audio', blob, `voice-note.${extensionForMimeType(mimeType)}`);

        const response = await fetch('/ai/transcribe', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        const data = (await response.json().catch(() => ({}))) as {
            text?: string;
            message?: string;
        };
        if (!response.ok) {
            throw new Error(data.message || 'Could not transcribe the voice note.');
        }

        const transcript = (data.text || '').trim();
        if (!transcript) {
            toast.error('No speech was detected.');
            return;
        }

        query.value = transcript;
        toast.success('Voice note transcribed — review the text, then click Search');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'Could not transcribe the voice note.');
    } finally {
        transcribing.value = false;
    }
}

onBeforeUnmount(() => {
    if (recording.value) {
        stopVoiceNote();
    }
    stopMediaTracks();
});
</script>

<template>
    <Head title="AI Assistant" />
    <AppLayout :breadcrumbs="[{ title: 'AI Assistant', href: '/ai-assistant' }]">
        <div class="space-y-4 p-4">
            <h1 class="text-2xl font-bold text-gray-900">AI Assistant</h1>
            <p class="text-sm text-gray-600">Read-only assistant over company data (jobcards, quotes, invoices, tasks, customers, contacts).</p>

            <div v-if="!aiAllowed" class="rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                AI access is currently unavailable for this user or instance.
            </div>

            <div v-else class="rounded-lg border bg-white p-4">
                <div class="flex flex-wrap gap-2">
                    <input
                        v-model="query"
                        type="text"
                        class="min-w-[16rem] flex-1 rounded border px-3 py-2 text-sm"
                        placeholder="Ask about records, references, statuses, customers..."
                        :disabled="recording || transcribing"
                        @keydown.enter.prevent="search"
                    />
                    <button
                        v-if="voiceSupported"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded border px-3 py-2 text-sm font-medium disabled:opacity-60"
                        :class="recording ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
                        :disabled="loading || transcribing"
                        :title="recording ? 'Stop voice note' : 'Ask with a voice note'"
                        @click="recording ? stopVoiceNote() : startVoiceNote()"
                    >
                        <Square v-if="recording" class="h-4 w-4" />
                        <Mic v-else class="h-4 w-4" />
                        {{ recording ? 'Stop' : transcribing ? 'Transcribing…' : 'Voice note' }}
                    </button>
                    <button
                        type="button"
                        class="rounded bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="loading || recording || transcribing || !query.trim()"
                        @click="search"
                    >
                        {{ loading ? 'Searching…' : 'Search' }}
                    </button>
                </div>
                <p v-if="recording" class="mt-2 text-xs text-red-600">Recording… speak clearly, then click Stop when finished.</p>
                <p v-else-if="transcribing" class="mt-2 text-xs text-gray-500">Transcribing your voice note…</p>
                <p v-else-if="!voiceSupported" class="mt-2 text-xs text-gray-500">Voice notes require a browser with microphone support.</p>
                <div
                    v-else-if="micAccessHelp"
                    class="mt-3 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
                    role="alert"
                >
                    <p class="font-medium">Microphone access is required for voice notes</p>
                    <p class="mt-1 text-xs text-amber-900">Enable the microphone for this site, then click Voice note again:</p>
                    <ol class="mt-2 list-decimal space-y-1 pl-5 text-xs text-amber-900">
                        <li v-for="(step, index) in micAccessHelp" :key="index">{{ step }}</li>
                    </ol>
                </div>
                <p v-if="summary" class="mt-3 text-sm text-gray-700">{{ summary }}</p>

                <div v-if="Object.keys(results).length" class="mt-4 grid gap-3 md:grid-cols-2">
                    <div v-for="(rows, group) in results" :key="group" class="rounded border border-gray-200 p-3">
                        <h2 class="mb-2 text-sm font-semibold text-gray-900">{{ groupLabel[group] || group }}</h2>
                        <ul class="space-y-2 text-xs text-gray-700">
                            <li v-for="(row, idx) in rows" :key="`${group}-${idx}`" class="rounded border border-gray-200 bg-gray-50 px-2 py-2">
                                <a
                                    :href="recordHref(group, row)"
                                    class="font-semibold text-indigo-700 hover:underline"
                                >
                                    {{ recordTitle(group, row) }}
                                </a>
                                <p class="mt-0.5 text-gray-600">{{ recordMeta(group, row) }}</p>
                            </li>
                            <li v-if="rows.length === 0" class="text-gray-500">No matches</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
