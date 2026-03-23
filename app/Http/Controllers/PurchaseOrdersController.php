<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\TaxRate;
use App\Services\StockService;
use App\Support\CompanyScopedRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrdersController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * Display a listing of purchase orders.
     */
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = PurchaseOrder::where('company_id', $currentCompany->id)
            ->with(['supplier', 'items.product'])
            ->when($request->filled('supplier_id'), function ($query) use ($request) {
                $query->where('supplier_id', $request->integer('supplier_id'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where('po_number', 'like', "%{$search}%");
            });

        $columnFilters = collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'colf_'))
            ->mapWithKeys(function ($value, $key) {
                $trimmed = trim((string) $value);

                return [substr((string) $key, 5) => $trimmed];
            })
            ->filter(fn ($value) => $value !== '');

        foreach ($columnFilters as $filterKey => $filterValue) {
            switch ($filterKey) {
                case 'po_number':
                    $query->where('po_number', 'like', "%{$filterValue}%");
                    break;
                case 'supplier':
                    $query->whereHas('supplier', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'order_date':
                    $query->where('order_date', 'like', "%{$filterValue}%");
                    break;
                case 'expected_delivery':
                    $query->where('expected_delivery_date', 'like', "%{$filterValue}%");
                    break;
                case 'status':
                    $query->where('status', 'like', "%{$filterValue}%");
                    break;
                case 'total':
                    $query->where('total', 'like', "%{$filterValue}%");
                    break;
                case 'subtotal':
                    $query->where('subtotal', 'like', "%{$filterValue}%");
                    break;
                case 'tax':
                    $query->where('tax_amount', 'like', "%{$filterValue}%");
                    break;
                case 'notes':
                    $query->where('notes', 'like', "%{$filterValue}%");
                    break;
                case 'terms':
                    $query->where('terms', 'like', "%{$filterValue}%");
                    break;
                case 'created':
                    $query->where('created_at', 'like', "%{$filterValue}%");
                    break;
                case 'updated':
                    $query->where('updated_at', 'like', "%{$filterValue}%");
                    break;
            }
        }

        $sortableFields = ['po_number', 'supplier_name', 'order_date', 'expected_delivery_date', 'status', 'total', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        if ($sortBy === 'supplier_name') {
            $query->orderBy(
                Supplier::select('name')->whereColumn('suppliers.id', 'purchase_orders.supplier_id')->limit(1),
                $sortDir
            );
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $purchaseOrders = $query->paginate(15)->withQueryString();

        return Inertia::render('purchase-orders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => [
                'supplier_id' => $request->integer('supplier_id'),
                'status' => $request->string('status')->toString(),
                'search' => $request->string('search')->toString(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'column_filters' => $columnFilters->all(),
            ],
            'currentCompany' => $currentCompany,
            'suppliers' => Supplier::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new purchase order.
     */
    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_purchasing']);
        $defaultPurchasingTaxRate = TaxRate::getDefaultPurchasingForCompany($currentCompany->id);

        $initialSupplier = null;
        if ($request->filled('supplier_id')) {
            $row = Supplier::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->whereKey($request->integer('supplier_id'))
                ->first(['id', 'name', 'email', 'phone', 'vat_number']);
            if ($row) {
                $initialSupplier = [
                    'id' => $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'phone' => $row->phone,
                    'vat_number' => $row->vat_number,
                ];
            }
        }

        return Inertia::render('purchase-orders/Create', [
            'initialSupplier' => $initialSupplier,
            'taxRates' => $taxRates,
            'defaultPurchasingTaxRateId' => $defaultPurchasingTaxRate?->id,
            'products' => Product::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost', 'price', 'stock_quantity'])
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'cost_price' => $product->cost ?? 0,
                        'selling_price' => $product->price ?? 0,
                        'stock_quantity' => $product->stock_quantity ?? 0,
                    ];
                }),
        ]);
    }

    /**
     * Store a newly created purchase order.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'supplier_id' => ['required', CompanyScopedRules::supplier($cid)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', CompanyScopedRules::product($cid)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        $supplier = Supplier::findOrFail($validated['supplier_id']);

        $po = PurchaseOrder::create([
            'company_id' => $currentCompany->id,
            'supplier_id' => $validated['supplier_id'],
            'po_number' => PurchaseOrder::generatePONumber($currentCompany->id),
            'order_date' => $validated['order_date'],
            'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
            'status' => 'draft',
            'notes' => $validated['notes'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'user_id' => auth()->id(),
        ]);

        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $po->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        foreach ($validated['items'] as $item) {
            PurchaseOrderItem::create([
                'purchase_order_id' => $po->id,
                'line_group_id' => $groupMap[(string) ($item['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
                'description' => $item['description'] ?? null,
                'tax_rate_id' => $item['tax_rate_id'] ?? null,
            ]);
        }

        $po->calculateTotals();
        $po->save();

        return redirect()->route('purchase-orders.show', $po)
            ->with('success', 'Purchase order created successfully');
    }

    /**
     * Show the form for editing the specified purchase order.
     */
    public function edit(Request $request, PurchaseOrder $purchaseOrder): Response|RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status === 'received' || $purchaseOrder->items()->where('quantity_received', '>', 0)->exists()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['message' => 'This purchase order cannot be edited because stock has been received against it.']);
        }

        $currentCompany = auth()->user()->getCurrentCompany();

        $purchaseOrder->load(['lineGroups', 'items', 'supplier']);

        $sortedGroups = $purchaseOrder->lineGroups->sortBy('sort_order')->values();
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

        $itemsPayload = $purchaseOrder->items->map(function (PurchaseOrderItem $item) use ($lineGroupDbIdToFormId) {
            return [
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'description' => $item->description,
                'tax_rate_id' => $item->tax_rate_id,
                'line_group_id' => $lineGroupDbIdToFormId[$item->line_group_id] ?? 1,
            ];
        })->values()->all();

        $taxRates = TaxRate::where('company_id', $currentCompany->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'rate', 'is_default_purchasing']);
        $defaultPurchasingTaxRate = TaxRate::getDefaultPurchasingForCompany($currentCompany->id);

        $supplier = $purchaseOrder->supplier;
        $initialSupplier = $supplier ? [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'email' => $supplier->email,
            'phone' => $supplier->phone,
            'vat_number' => $supplier->vat_number,
            'is_active' => (bool) $supplier->is_active,
        ] : null;

        return Inertia::render('purchase-orders/Edit', [
            'purchaseOrder' => [
                'id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'supplier_id' => $purchaseOrder->supplier_id,
                'order_date' => $purchaseOrder->order_date->format('Y-m-d'),
                'expected_delivery_date' => $purchaseOrder->expected_delivery_date?->format('Y-m-d'),
                'notes' => $purchaseOrder->notes,
                'terms' => $purchaseOrder->terms,
                'line_groups' => $lineGroupsPayload,
                'items' => $itemsPayload,
            ],
            'initialSupplier' => $initialSupplier,
            'taxRates' => $taxRates,
            'defaultPurchasingTaxRateId' => $defaultPurchasingTaxRate?->id,
            'products' => Product::where('company_id', $currentCompany->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'cost', 'price', 'stock_quantity'])
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'cost_price' => $product->cost ?? 0,
                        'selling_price' => $product->price ?? 0,
                        'stock_quantity' => $product->stock_quantity ?? 0,
                    ];
                }),
        ]);
    }

    /**
     * Update the specified purchase order in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        if ($purchaseOrder->status === 'received' || $purchaseOrder->items()->where('quantity_received', '>', 0)->exists()) {
            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->withErrors(['message' => 'This purchase order cannot be edited because stock has been received against it.']);
        }

        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'supplier_id' => ['required', CompanyScopedRules::supplier($cid)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', CompanyScopedRules::product($cid)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric'],
            'items.*.description' => ['nullable', 'string'],
            'items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'items.*.line_group_id' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($purchaseOrder, $validated) {
            $purchaseOrder->items()->delete();
            $purchaseOrder->lineGroups()->delete();

            $purchaseOrder->update([
                'supplier_id' => $validated['supplier_id'],
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
            ]);

            $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
            $groupMap = [];
            foreach (array_values($groupPayload) as $groupIndex => $groupData) {
                $group = $purchaseOrder->lineGroups()->create([
                    'name' => $groupData['name'] ?: 'Items',
                    'sort_order' => $groupIndex,
                ]);
                $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
            }
            $defaultGroupId = reset($groupMap);

            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'line_group_id' => $groupMap[(string) ($item['line_group_id'] ?? '')] ?? $defaultGroupId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'description' => $item['description'] ?? null,
                    'tax_rate_id' => $item['tax_rate_id'] ?? null,
                ]);
            }

            $purchaseOrder->calculateTotals();
            $purchaseOrder->save();
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated successfully');
    }

    /**
     * Display the specified purchase order.
     */
    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $this->authorize('view', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        // Get available PDF templates for purchase orders
        $pdfTemplates = \App\Models\PdfTemplate::where('company_id', $currentCompany->id)
            ->where('module', 'purchase-order')
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $defaultTemplateId = $pdfTemplates->where('is_default', true)->first()?->id ?? null;

        // Load purchase order with all necessary relationships
        $purchaseOrder->load([
            'supplier',
            'items.product.batches',
            'items.product.serialNumbers',
            'items.taxRate',
            'items.lineGroup',
            'lineGroups',
            'user',
        ]);

        // Manually ensure serial numbers are included in the response
        // Convert to array format to ensure Inertia serializes it properly
        $purchaseOrderData = $purchaseOrder->toArray();

        // Ensure serial numbers are included for each product
        foreach ($purchaseOrderData['items'] as $key => $item) {
            if (isset($item['product']) && isset($item['product']['id'])) {
                $product = Product::with('serialNumbers')->find($item['product']['id']);
                if ($product) {
                    $purchaseOrderData['items'][$key]['product']['serialNumbers'] = $product->serialNumbers->toArray();
                }
            }
        }

        return Inertia::render('purchase-orders/Show', [
            'purchaseOrder' => $purchaseOrderData,
            'pdfTemplates' => $pdfTemplates,
            'defaultTemplateId' => $defaultTemplateId,
        ]);
    }

    /**
     * Update purchase order status.
     */
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,sent,received,cancelled'],
        ]);

        $purchaseOrder->update(['status' => $validated['status']]);

        // If status is 'received', add stock for all items
        if ($validated['status'] === 'received' && ! $purchaseOrder->received_date) {
            $purchaseOrder->update(['received_date' => now()]);

            foreach ($purchaseOrder->items as $item) {
                $product = $item->product;

                if ($product->track_stock) {
                    $this->stockService->addStock(
                        $product,
                        $item->quantity_received > 0 ? $item->quantity_received : $item->quantity,
                        $item->unit_cost,
                        "PO: {$purchaseOrder->po_number}",
                        'purchase_order',
                        $purchaseOrder->id,
                        'Received from purchase order',
                        $item->product_batch_id,
                        $item->serial_number_ids
                    );
                }
            }
        }

        return redirect()->back()->with('success', 'Purchase order status updated successfully');
    }

    /**
     * Receive items from purchase order.
     */
    public function receiveItems(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        $cid = $currentCompany->id;
        $poId = $purchaseOrder->id;

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array'],
            'items.*.id' => ['required', CompanyScopedRules::purchaseOrderItemForPurchaseOrder($poId)],
            'items.*.quantity_received' => ['required', 'integer', 'min:0'],
            'items.*.product_batch_id' => ['nullable', CompanyScopedRules::productBatchForCompany($cid)],
            'items.*.serial_number_ids' => ['nullable', 'array'],
            'items.*.serial_number_ids.*' => [CompanyScopedRules::productSerialNumberForCompany($cid)],
            'items.*.custom_serial_numbers' => ['nullable', 'array'],
            'items.*.custom_serial_numbers.*' => ['string', 'max:255'],
        ]);
        $validator->after(CompanyScopedRules::afterValidatePurchaseOrderItemBatches($cid));
        $validator->after(CompanyScopedRules::afterValidatePurchaseOrderItemSerials($cid));
        $validated = $validator->validate();

        foreach ($validated['items'] as $itemData) {
            $item = PurchaseOrderItem::findOrFail($itemData['id']);

            if ($item->purchase_order_id !== $purchaseOrder->id) {
                continue;
            }

            $quantityReceived = min($itemData['quantity_received'], $item->quantity - $item->quantity_received);

            if ($quantityReceived > 0) {
                $item->increment('quantity_received', $quantityReceived);

                // Load product first
                $product = $item->product;

                // Update batch if provided
                if (isset($itemData['product_batch_id'])) {
                    $item->update(['product_batch_id' => $itemData['product_batch_id']]);
                }

                // Create custom serial numbers if provided
                $allSerialNumberIds = $itemData['serial_number_ids'] ?? [];
                if (isset($itemData['custom_serial_numbers']) && is_array($itemData['custom_serial_numbers']) && count($itemData['custom_serial_numbers']) > 0) {
                    foreach ($itemData['custom_serial_numbers'] as $customSerial) {
                        $trimmedSerial = trim($customSerial);
                        if (empty($trimmedSerial)) {
                            continue;
                        }

                        // Check if serial number already exists
                        $existingSerial = \App\Models\ProductSerialNumber::where('serial_number', $trimmedSerial)
                            ->where('product_id', $product->id)
                            ->first();

                        if (! $existingSerial) {
                            // Create new serial number
                            $newSerial = \App\Models\ProductSerialNumber::create([
                                'company_id' => $currentCompany->id,
                                'product_id' => $product->id,
                                'serial_number' => $trimmedSerial,
                                'product_batch_id' => $itemData['product_batch_id'] ?? null,
                                'status' => 'available',
                                'purchase_date' => now(),
                            ]);
                            $allSerialNumberIds[] = $newSerial->id;
                        } else {
                            // Use existing serial number if it's available
                            if ($existingSerial->status === 'available' || $existingSerial->status === 'in_stock') {
                                if (! in_array($existingSerial->id, $allSerialNumberIds)) {
                                    $allSerialNumberIds[] = $existingSerial->id;
                                }
                            }
                        }
                    }
                }

                // Update serial number IDs by merging with existing serial numbers
                $existingSerialIds = $item->serial_number_ids ?? [];
                $mergedSerialIds = array_unique(array_merge($existingSerialIds, $allSerialNumberIds));
                if (count($mergedSerialIds) > 0) {
                    $item->update(['serial_number_ids' => $mergedSerialIds]);
                }

                if ($product->track_stock) {
                    $this->stockService->addStock(
                        $product,
                        $quantityReceived,
                        $item->unit_cost,
                        "PO: {$purchaseOrder->po_number}",
                        'purchase_order',
                        $purchaseOrder->id,
                        'Received from purchase order',
                        $itemData['product_batch_id'] ?? null,
                        count($allSerialNumberIds) > 0 ? array_unique($allSerialNumberIds) : null
                    );
                }
            }
        }

        // Update PO status if all items are received
        if ($purchaseOrder->isFullyReceived()) {
            $purchaseOrder->update([
                'status' => 'received',
                'received_date' => now(),
            ]);
        }

        return redirect()->back()->with('success', 'Items received and stock updated successfully');
    }

    /**
     * Remove the specified purchase order.
     */
    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('delete', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        if ($purchaseOrder->status === 'received') {
            return redirect()->back()
                ->withErrors(['message' => 'Cannot delete a received purchase order.']);
        }

        $purchaseOrder->delete();

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase order deleted successfully');
    }

    /**
     * Download PDF of the purchase order.
     */
    public function downloadPdf(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorize('view', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        $purchaseOrder->load(['supplier', 'items.product', 'items.taxRate', 'items.lineGroup', 'lineGroups', 'company']);
        $company = $purchaseOrder->company;

        $company = $purchaseOrder->company;
        $templateId = $request->get('template_id');

        $pdfService = new \App\Services\PdfGenerationService;
        $pdf = $pdfService->generatePdf('purchase-order', compact('purchaseOrder', 'company'), $company, $templateId);

        // Update status to sent if it was draft
        if ($purchaseOrder->status === 'draft') {
            $purchaseOrder->update(['status' => 'sent']);
        }

        return $pdf->download("purchase-order-{$purchaseOrder->po_number}.pdf");
    }

    /**
     * Email the purchase order.
     */
    public function email(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('view', $purchaseOrder);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'email' => 'required|email',
            'customMessage' => 'nullable|string',
        ]);

        $purchaseOrder->load(['supplier', 'items.product', 'items.taxRate', 'items.lineGroup', 'lineGroups', 'company']);
        $company = $purchaseOrder->company;

        try {
            // Generate PDF
            $templateId = $request->get('template_id');

            $pdfService = new \App\Services\PdfGenerationService;
            $pdf = $pdfService->generatePdf('purchase-order', compact('purchaseOrder', 'company'), $company, $templateId);
            $pdfContent = $pdf->output();

            // Send email
            \Illuminate\Support\Facades\Mail::mailer('smtp')->send('emails.purchase-order', [
                'purchaseOrder' => $purchaseOrder,
                'customMessage' => $validated['customMessage'],
            ], function ($message) use ($validated, $purchaseOrder, $pdfContent, $company) {
                $fromName = $company?->name ?: config('mail.from.name');
                $message->to($validated['email'])
                    ->subject("Purchase Order {$purchaseOrder->po_number} - {$purchaseOrder->supplier->name}")
                    ->from(config('mail.from.address'), $fromName)
                    ->attachData($pdfContent, "purchase-order-{$purchaseOrder->po_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);

                if (! empty($company->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
            });

            // Update status to sent if it was draft
            if ($purchaseOrder->status === 'draft') {
                $purchaseOrder->update(['status' => 'sent']);
            }

            return redirect()->back()->with('success', 'Purchase order sent successfully to '.$validated['email']);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['message' => 'Failed to send email: '.$e->getMessage()]);
        }
    }
}
