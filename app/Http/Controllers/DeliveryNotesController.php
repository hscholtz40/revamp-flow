<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use App\Models\DeliveryNoteLineItem;
use App\Models\Jobcard;
use App\Models\Note;
use App\Models\Product;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNotesController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $this->authorize('create', DeliveryNote::class);

        if (! $request->filled('jobcard_id')) {
            return redirect()->route('jobcards.index')
                ->withErrors(['message' => 'A jobcard is required to create a delivery note.']);
        }

        $currentCompany = auth()->user()->getCurrentCompany();
        $jobcard = Jobcard::where('company_id', $currentCompany->id)
            ->with(['customer', 'contact', 'lineItems', 'lineGroups'])
            ->find($request->integer('jobcard_id'));

        if (! $jobcard) {
            return redirect()->route('jobcards.index')
                ->withErrors(['message' => 'The selected jobcard could not be found.']);
        }

        $this->authorize('view', $jobcard);

        $groups = $jobcard->lineGroups->sortBy('sort_order')->values();
        $groupMap = [];
        foreach ($groups as $idx => $group) {
            $groupMap[$group->id] = $idx + 1;
        }

        $prefill = [
            'jobcard_id' => $jobcard->id,
            'delivery_address' => $jobcard->service_address ?? $jobcard->customer?->address,
            'line_groups' => ($groups->isNotEmpty()
                ? $groups->map(fn ($group, $idx) => ['name' => $group->name ?: 'Items', 'sort_order' => $idx])->values()->toArray()
                : [['name' => 'Items', 'sort_order' => 0]]
            ),
            'items' => $jobcard->lineItems->sortBy('sort_order')->values()->map(function ($item) use ($groupMap) {
                return [
                    'product_id' => $item->product_id,
                    'line_group_id' => $groupMap[$item->line_group_id] ?? 1,
                    'quantity' => (int) ($item->quantity ?? 1),
                    'description' => $item->description,
                ];
            })->toArray(),
        ];

        return Inertia::render('delivery-notes/Create', [
            'jobcard' => [
                'id' => $jobcard->id,
                'job_number' => $jobcard->job_number,
                'title' => $jobcard->title,
                'customer' => $jobcard->customer ? [
                    'id' => $jobcard->customer->id,
                    'name' => $jobcard->customer->name,
                ] : null,
            ],
            'prefill' => $prefill,
            'products' => Product::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku'])
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', DeliveryNote::class);

        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'jobcard_id' => ['required', CompanyScopedRules::jobcard($cid)],
            'delivery_date' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        $jobcard = Jobcard::where('company_id', $cid)->findOrFail($validated['jobcard_id']);
        $this->authorize('view', $jobcard);

        $deliveryNote = DeliveryNote::create([
            'company_id' => $cid,
            'jobcard_id' => $jobcard->id,
            'customer_id' => $jobcard->customer_id,
            'contact_id' => $jobcard->contact_id,
            'user_id' => auth()->id(),
            'delivery_note_number' => DeliveryNote::generateDeliveryNoteNumber($cid),
            'delivery_date' => $validated['delivery_date'] ?? now()->toDateString(),
            'delivery_address' => $validated['delivery_address'] ?? null,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
        ]);

        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $deliveryNote->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        foreach (array_values($validated['items']) as $sortOrder => $item) {
            DeliveryNoteLineItem::create([
                'delivery_note_id' => $deliveryNote->id,
                'line_group_id' => $groupMap[(string) ($item['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'sort_order' => $sortOrder,
            ]);
        }

        return redirect()->route('delivery-notes.show', $deliveryNote)
            ->with('success', 'Delivery note created successfully');
    }

    public function show(DeliveryNote $deliveryNote): Response
    {
        $this->authorize('view', $deliveryNote);

        $currentCompany = auth()->user()->getCurrentCompany();

        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->where('module', 'delivery-note')
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $defaultTemplateId = $pdfTemplates->where('is_default', true)->first()?->id ?? null;

        $deliveryNote->load([
            'jobcard',
            'customer',
            'contact',
            'lineItems.product',
            'lineItems.lineGroup',
            'lineGroups',
            'user',
        ]);

        return Inertia::render('delivery-notes/Show', [
            'deliveryNote' => $deliveryNote,
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
        ]);
    }

    public function edit(DeliveryNote $deliveryNote): Response
    {
        $this->authorize('update', $deliveryNote);

        $currentCompany = auth()->user()->getCurrentCompany();

        $deliveryNote->load(['lineGroups', 'lineItems', 'jobcard.customer']);

        $sortedGroups = $deliveryNote->lineGroups->sortBy('sort_order')->values();
        $lineGroupDbIdToFormId = [];
        foreach ($sortedGroups as $idx => $group) {
            $lineGroupDbIdToFormId[$group->id] = $idx + 1;
        }

        $lineGroupsPayload = $sortedGroups->isEmpty()
            ? [['name' => 'Items', 'sort_order' => 0]]
            : $sortedGroups->map(fn ($g, $i) => [
                'name' => $g->name,
                'sort_order' => $i,
            ])->values()->all();

        $itemsPayload = $deliveryNote->lineItems->map(function (DeliveryNoteLineItem $item) use ($lineGroupDbIdToFormId) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'description' => $item->description,
                'line_group_id' => $lineGroupDbIdToFormId[$item->line_group_id] ?? 1,
            ];
        })->values()->all();

        return Inertia::render('delivery-notes/Edit', [
            'deliveryNote' => [
                'id' => $deliveryNote->id,
                'delivery_note_number' => $deliveryNote->delivery_note_number,
                'jobcard_id' => $deliveryNote->jobcard_id,
                'delivery_date' => $deliveryNote->delivery_date?->format('Y-m-d'),
                'delivery_address' => $deliveryNote->delivery_address,
                'notes' => $deliveryNote->notes,
                'line_groups' => $lineGroupsPayload,
                'items' => $itemsPayload,
            ],
            'jobcard' => [
                'id' => $deliveryNote->jobcard->id,
                'job_number' => $deliveryNote->jobcard->job_number,
                'title' => $deliveryNote->jobcard->title,
                'customer' => $deliveryNote->jobcard->customer ? [
                    'id' => $deliveryNote->jobcard->customer->id,
                    'name' => $deliveryNote->jobcard->customer->name,
                ] : null,
            ],
            'products' => Product::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku'])
                ->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ]),
        ]);
    }

    public function update(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('update', $deliveryNote);

        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'delivery_date' => ['nullable', 'date'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($deliveryNote, $validated) {
            $deliveryNote->lineItems()->delete();
            $deliveryNote->lineGroups()->delete();

            $deliveryNote->update([
                'delivery_date' => $validated['delivery_date'] ?? null,
                'delivery_address' => $validated['delivery_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
            $groupMap = [];
            foreach (array_values($groupPayload) as $groupIndex => $groupData) {
                $group = $deliveryNote->lineGroups()->create([
                    'name' => $groupData['name'] ?: 'Items',
                    'sort_order' => $groupIndex,
                ]);
                $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
            }
            $defaultGroupId = reset($groupMap);

            foreach (array_values($validated['items']) as $sortOrder => $item) {
                DeliveryNoteLineItem::create([
                    'delivery_note_id' => $deliveryNote->id,
                    'line_group_id' => $groupMap[(string) ($item['line_group_id'] ?? '')] ?? $defaultGroupId,
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'sort_order' => $sortOrder,
                ]);
            }
        });

        return redirect()->route('delivery-notes.show', $deliveryNote)
            ->with('success', 'Delivery note updated successfully');
    }

    public function updateStatus(Request $request, DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('update', $deliveryNote);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,sent,delivered,cancelled'],
        ]);

        $deliveryNote->update(['status' => $validated['status']]);

        return redirect()->back()->with('success', 'Delivery note status updated successfully');
    }

    public function destroy(DeliveryNote $deliveryNote): RedirectResponse
    {
        $this->authorize('delete', $deliveryNote);

        $jobcardId = $deliveryNote->jobcard_id;
        $deliveryNote->delete();

        return redirect()->route('jobcards.show', $jobcardId)
            ->with('success', 'Delivery note deleted successfully');
    }

    public function downloadPdf(Request $request, DeliveryNote $deliveryNote)
    {
        $this->authorize('view', $deliveryNote);

        $deliveryNote->load(['jobcard', 'customer', 'contact', 'lineItems.product', 'lineItems.lineGroup', 'lineGroups', 'company']);
        $company = $deliveryNote->company;
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf('delivery-note', compact('deliveryNote', 'company'), $company, $templateId);
        $filename = "delivery-note-{$deliveryNote->delivery_note_number}.pdf";
        $pdfContent = $pdf->output();

        $this->storePrintedDocumentNote($deliveryNote, $filename, $pdfContent, "Delivery Note {$deliveryNote->delivery_note_number} printed");

        if ($deliveryNote->status === 'draft') {
            $deliveryNote->update(['status' => 'sent']);
        }

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function storePrintedDocumentNote(DeliveryNote $deliveryNote, string $filename, string $pdfContent, string $subject): void
    {
        $companyId = (int) $deliveryNote->company_id;
        $path = sprintf(
            'notes/%d/printed/%s-%s',
            $companyId,
            now()->format('YmdHis'),
            $filename
        );

        Storage::disk('public')->put($path, $pdfContent);

        $note = new Note([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'subject' => $subject,
            'description' => null,
            'attachment_path' => $path,
            'attachment_original_name' => $filename,
            'attachment_mime' => 'application/pdf',
            'attachment_size' => strlen($pdfContent),
        ]);

        $note->noteable()->associate($deliveryNote);
        $note->save();
    }
}
