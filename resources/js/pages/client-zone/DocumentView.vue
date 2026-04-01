<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps<{
    documentType: 'invoice' | 'quote' | 'jobcard';
    documentTitle: string;
    document: any;
    statusLabel: string;
    downloadPdfUrl: string;
    signUrl: string;
    documentSigningEnabled: boolean;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const drawing = ref(false);
const hasDrawnSignature = ref(false);
const ctx = computed(() => canvasRef.value?.getContext('2d') ?? null);

const form = useForm({
    signer_name: '',
    signature_data: '',
});

function getPos(event: MouseEvent | TouchEvent) {
    const canvas = canvasRef.value!;
    const rect = canvas.getBoundingClientRect();
    if ('touches' in event) {
        return {
            x: event.touches[0].clientX - rect.left,
            y: event.touches[0].clientY - rect.top,
        };
    }
    return {
        x: event.clientX - rect.left,
        y: event.clientY - rect.top,
    };
}

function startDraw(event: MouseEvent | TouchEvent) {
    if (!ctx.value) return;
    drawing.value = true;
    const p = getPos(event);
    ctx.value.beginPath();
    ctx.value.moveTo(p.x, p.y);
}

function moveDraw(event: MouseEvent | TouchEvent) {
    if (!drawing.value || !ctx.value) return;
    const p = getPos(event);
    ctx.value.lineTo(p.x, p.y);
    ctx.value.strokeStyle = '#111827';
    ctx.value.lineWidth = 2;
    ctx.value.lineCap = 'round';
    ctx.value.stroke();
    hasDrawnSignature.value = true;
}

function endDraw() {
    drawing.value = false;
}

function clearSignature() {
    const canvas = canvasRef.value;
    const context = ctx.value;
    if (!canvas || !context) return;
    context.clearRect(0, 0, canvas.width, canvas.height);
    hasDrawnSignature.value = false;
    form.signature_data = '';
}

function submitSignature() {
    if (!canvasRef.value || !hasDrawnSignature.value) return;
    form.signature_data = canvasRef.value.toDataURL('image/png');
    form.post(props.signUrl, {
        preserveScroll: true,
        onSuccess: () => clearSignature(),
    });
}

onMounted(() => {
    const context = ctx.value;
    if (!context) return;
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, 800, 220);
});
</script>

<template>
    <Head :title="`${documentType.toUpperCase()} ${documentTitle}`" />
    <AppLayout :breadcrumbs="[{ title: 'Client Zone', href: '/client-zone' }, { title: documentTitle, href: '#' }]">
        <div class="space-y-6 p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold">{{ documentType.toUpperCase() }} {{ documentTitle }}</h1>
                <a :href="downloadPdfUrl" class="rounded bg-slate-700 px-4 py-2 text-sm text-white hover:bg-slate-800">Download PDF</a>
            </div>

            <div class="rounded border p-4">
                <div class="mb-2 text-sm text-gray-700">Customer: {{ document.customer?.name }}</div>
                <div class="mb-2 text-sm text-gray-700">Status: {{ statusLabel }}</div>
                <div class="mb-2 text-sm text-gray-700">Total: {{ document.total }}</div>
                <div class="mb-4 text-sm text-gray-700">Company: {{ document.company?.name }}</div>

                <h2 class="mb-2 text-lg font-medium">Line Items</h2>
                <table class="min-w-full border text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="border p-2 text-left">Description</th>
                            <th class="border p-2 text-right">Qty</th>
                            <th class="border p-2 text-right">Unit</th>
                            <th class="border p-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="line in (document.line_items || document.lineItems || [])" :key="line.id">
                            <td class="border p-2">{{ line.description }}</td>
                            <td class="border p-2 text-right">{{ line.quantity }}</td>
                            <td class="border p-2 text-right">{{ line.unit_price }}</td>
                            <td class="border p-2 text-right">{{ line.total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded border p-4">
                <h2 class="mb-3 text-lg font-medium">Signatures</h2>
                <div v-if="(document.signatures || []).length" class="mb-4 space-y-2 text-sm">
                    <div v-for="sig in document.signatures" :key="sig.id" class="rounded border p-2">
                        <div><strong>{{ sig.signer_name }}</strong> - {{ sig.signed_at }}</div>
                        <img v-if="sig.signature_url" :src="sig.signature_url" alt="Signature" class="mt-2 h-16" />
                    </div>
                </div>

                <div v-if="documentSigningEnabled" class="space-y-3">
                    <input v-model="form.signer_name" class="w-full rounded border px-3 py-2" placeholder="Your name" />
                    <div class="rounded border bg-white p-2">
                        <canvas
                            ref="canvasRef"
                            width="800"
                            height="220"
                            class="w-full border"
                            @mousedown="startDraw"
                            @mousemove="moveDraw"
                            @mouseup="endDraw"
                            @mouseleave="endDraw"
                            @touchstart.prevent="startDraw"
                            @touchmove.prevent="moveDraw"
                            @touchend.prevent="endDraw"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded border px-3 py-2" @click="clearSignature">Clear</button>
                        <button type="button" class="rounded bg-blue-600 px-4 py-2 text-white" :disabled="form.processing || !hasDrawnSignature || !form.signer_name" @click="submitSignature">
                            {{ form.processing ? 'Signing...' : 'Sign Document' }}
                        </button>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-600">Document signing is not enabled for this company.</p>
            </div>
        </div>
    </AppLayout>
</template>
