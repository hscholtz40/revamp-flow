<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { useNumberFormat } from '@/composables/useNumberFormat';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { useDateTimeFormat } from '@/composables/useDateTimeFormat';

const props = defineProps<{
    documentType: 'invoice' | 'quote' | 'jobcard';
    documentTitle: string;
    document: Record<string, any>;
    statusLabel: string;
    downloadPdfUrl: string;
    signUrl: string;
    documentSigningEnabled: boolean;
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const drawing = ref(false);
const hasDrawnSignature = ref(false);
const ctx = computed(() => canvasRef.value?.getContext('2d') ?? null);
const { formatDateTime } = useDateTimeFormat();
const { formatCurrency } = useNumberFormat();

const form = useForm({
    signer_name: '',
    signature_data: '',
});

function isRoundingLine(item: Record<string, any>): boolean {
    return String(item?.description ?? '')
        .trim()
        .toLowerCase() === 'rounding adjustment';
}

const lineItemsRaw = computed(() => props.document.line_items ?? props.document.lineItems ?? []);

const lineGroupsRaw = computed(() => props.document.line_groups ?? props.document.lineGroups ?? []);

interface ResolvedGroup {
    name: string | null;
    items: Record<string, any>[];
    subtotal: number;
}

/** Match PDF grouping: groups with items, then ungrouped under "Items". */
const resolvedLineGroups = computed((): ResolvedGroup[] => {
    const items = [...lineItemsRaw.value].filter((i) => !isRoundingLine(i));
    items.sort((a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0));

    const groups = [...lineGroupsRaw.value].sort(
        (a, b) => (Number(a.sort_order) || 0) - (Number(b.sort_order) || 0),
    );

    const renderedIds = new Set<number>();
    const result: ResolvedGroup[] = [];

    for (const g of groups) {
        const gid = Number(g.id);
        const groupItems = items.filter((i) => Number(i.line_group_id) === gid);
        if (groupItems.length === 0) {
            continue;
        }
        const subtotal = groupItems.reduce((s, i) => s + (Number(i.total) || 0), 0);
        result.push({ name: g.name ?? null, items: groupItems, subtotal });
        groupItems.forEach((i) => renderedIds.add(Number(i.id)));
    }

    const ungrouped = items.filter((i) => !renderedIds.has(Number(i.id)));

    if (result.length === 0 && items.length > 0) {
        const subtotal = items.reduce((s, i) => s + (Number(i.total) || 0), 0);
        result.push({ name: 'Items', items, subtotal });
    } else if (ungrouped.length > 0) {
        const subtotal = ungrouped.reduce((s, i) => s + (Number(i.total) || 0), 0);
        result.push({ name: 'Items', items: ungrouped, subtotal });
    }

    return result;
});

const roundingAdjustment = computed(() => {
    return lineItemsRaw.value.reduce((sum: number, item: any) => {
        if (!isRoundingLine(item)) {
            return sum;
        }
        return (
            sum +
            (Number(item.total) ||
                (Number(item.quantity) || 0) * (Number(item.unit_price) || 0))
        );
    }, 0);
});

const subtotalExcludingRounding = computed(() => {
    return lineItemsRaw.value.reduce((sum: number, item: any) => {
        if (isRoundingLine(item)) {
            return sum;
        }
        return sum + (Number(item.total) || 0);
    }, 0);
});

const taxAmount = computed(() => Number(props.document.tax_amount) || 0);
const discountAmount = computed(() => Number(props.document.discount_amount) || 0);
const discountPercentage = computed(() => Number(props.document.discount_percentage) || 0);
const documentTotal = computed(() => Number(props.document.total) || 0);
const taxRate = computed(() => Number(props.document.tax_rate) || 0);

const taxLabel = computed(() => (taxRate.value > 0 ? `Tax (${taxRate.value}%)` : 'Tax'));

/** Map pointer position to canvas bitmap coordinates (canvas may be CSS-scaled vs width/height attributes). */
function getPos(event: MouseEvent | TouchEvent): { x: number; y: number } {
    const canvas = canvasRef.value;
    if (!canvas) {
        return { x: 0, y: 0 };
    }
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;

    let clientX: number;
    let clientY: number;
    if ('touches' in event && event.touches.length > 0) {
        clientX = event.touches[0].clientX;
        clientY = event.touches[0].clientY;
    } else if ('clientX' in event) {
        clientX = event.clientX;
        clientY = event.clientY;
    } else {
        return { x: 0, y: 0 };
    }

    return {
        x: (clientX - rect.left) * scaleX,
        y: (clientY - rect.top) * scaleY,
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
    if (!drawing.value || !ctx.value || !canvasRef.value) return;
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const scale = (canvas.width / rect.width + canvas.height / rect.height) / 2;
    const p = getPos(event);
    const context = ctx.value;
    context.strokeStyle = '#111827';
    context.lineWidth = Math.max(2, 2 * scale);
    context.lineCap = 'round';
    context.lineJoin = 'round';
    context.lineTo(p.x, p.y);
    context.stroke();
    context.beginPath();
    context.moveTo(p.x, p.y);
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
                <div class="mb-4 text-sm text-gray-700">Company: {{ document.company?.name }}</div>

                <h2 class="mb-2 text-lg font-medium">Line items</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full border text-sm">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border p-2 text-left">Description</th>
                                <th class="border p-2 text-right">Qty</th>
                                <th class="border p-2 text-right">Unit</th>
                                <th class="border p-2 text-right">Line total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(group, gi) in resolvedLineGroups" :key="gi">
                                <tr v-if="group.name" class="bg-gray-100">
                                    <td class="border p-2 font-semibold text-gray-900" colspan="4">{{ group.name }}</td>
                                </tr>
                                <tr v-for="line in group.items" :key="line.id">
                                    <td class="border p-2">{{ line.description }}</td>
                                    <td class="border p-2 text-right">{{ line.quantity }}</td>
                                    <td class="border p-2 text-right">{{ formatCurrency(Number(line.unit_price) || 0) }}</td>
                                    <td class="border p-2 text-right">{{ formatCurrency(Number(line.total) || 0) }}</td>
                                </tr>
                                <tr v-if="resolvedLineGroups.length > 1 || (group.name && group.name !== 'Items')" class="bg-slate-50">
                                    <td class="border p-2 text-right font-medium text-gray-700" colspan="3">Subtotal ({{ group.name || 'Section' }})</td>
                                    <td class="border p-2 text-right font-medium text-gray-900">{{ formatCurrency(group.subtotal) }}</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 border-t border-gray-200 pt-4">
                    <h3 class="mb-3 text-base font-medium text-gray-900">Totals</h3>
                    <dl class="max-w-md space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Subtotal</dt>
                            <dd class="text-right font-medium text-gray-900">{{ formatCurrency(subtotalExcludingRounding) }}</dd>
                        </div>
                        <div v-if="discountAmount > 0" class="flex justify-between gap-4">
                            <dt class="text-gray-600">
                                Discount
                                <span v-if="discountPercentage > 0" class="text-gray-500"> ({{ discountPercentage }}%)</span>
                            </dt>
                            <dd class="text-right font-medium text-red-700">-{{ formatCurrency(discountAmount) }}</dd>
                        </div>
                        <div v-if="taxAmount > 0" class="flex justify-between gap-4">
                            <dt class="text-gray-600">{{ taxLabel }}</dt>
                            <dd class="text-right font-medium text-gray-900">{{ formatCurrency(taxAmount) }}</dd>
                        </div>
                        <div v-if="Math.abs(roundingAdjustment) > 0.0001" class="flex justify-between gap-4">
                            <dt class="text-gray-600">Rounding adjustment</dt>
                            <dd class="text-right font-medium text-gray-900">{{ formatCurrency(roundingAdjustment) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-gray-200 pt-2 text-base">
                            <dt class="font-semibold text-gray-900">Total</dt>
                            <dd class="text-right font-semibold text-gray-900">{{ formatCurrency(documentTotal) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="rounded border p-4">
                <h2 class="mb-3 text-lg font-medium">Signatures</h2>
                <div v-if="(document.signatures || []).length" class="mb-4 space-y-2 text-sm">
                    <div v-for="sig in document.signatures" :key="sig.id" class="rounded border p-2">
                        <div><strong>{{ sig.signer_name }}</strong> - {{ sig.signed_at ? formatDateTime(sig.signed_at) : '-' }}</div>
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
                            class="w-full touch-none border"
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
