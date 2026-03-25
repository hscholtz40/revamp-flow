<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\CreditNoteAllocation;
use App\Models\CreditNoteLineItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\TaxRate;
use App\Services\XeroService;
use App\Support\CompanyScopedRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CreditNotesController extends Controller
{
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = CreditNote::with(['customer', 'invoice'])
            ->where('company_id', $currentCompany->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $columnFilters = collect($request->query())
            ->filter(fn ($value, $key) => str_starts_with((string) $key, 'colf_'))
            ->mapWithKeys(function ($value, $key) {
                $trimmed = trim((string) $value);

                return [substr((string) $key, 5) => $trimmed];
            })
            ->filter(fn ($value) => $value !== '');

        foreach ($columnFilters as $filterKey => $filterValue) {
            switch ($filterKey) {
                case 'number':
                    $query->where('credit_note_number', 'like', "%{$filterValue}%");
                    break;
                case 'customer':
                    $query->whereHas('customer', function ($q) use ($filterValue) {
                        $q->where('name', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'invoice':
                    $query->whereHas('invoice', function ($q) use ($filterValue) {
                        $q->where('invoice_number', 'like', "%{$filterValue}%");
                    });
                    break;
                case 'date':
                    $query->where('credit_note_date', 'like', "%{$filterValue}%");
                    break;
                case 'status':
                    $query->where('status', 'like', "%{$filterValue}%");
                    break;
                case 'total':
                    $query->where('total', 'like', "%{$filterValue}%");
                    break;
                case 'remaining':
                    $query->where('remaining_credit', 'like', "%{$filterValue}%");
                    break;
                case 'reference':
                    $query->where('reference', 'like', "%{$filterValue}%");
                    break;
                case 'description':
                    $query->where('description', 'like', "%{$filterValue}%");
                    break;
                case 'subtotal':
                    $query->where('subtotal', 'like', "%{$filterValue}%");
                    break;
                case 'tax':
                    $query->where('tax_amount', 'like', "%{$filterValue}%");
                    break;
                case 'created':
                    $query->where('created_at', 'like', "%{$filterValue}%");
                    break;
                case 'updated':
                    $query->where('updated_at', 'like', "%{$filterValue}%");
                    break;
            }
        }

        $sortableFields = ['credit_note_number', 'customer_name', 'invoice_number', 'credit_note_date', 'status', 'total', 'remaining_credit', 'created_at'];
        if (! in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'created_at';
        }

        if ($sortBy === 'customer_name') {
            $query->orderBy(
                Customer::select('name')->whereColumn('customers.id', 'credit_notes.customer_id')->limit(1),
                $sortDir
            );
        } elseif ($sortBy === 'invoice_number') {
            $query->orderBy(
                Invoice::select('invoice_number')->whereColumn('invoices.id', 'credit_notes.invoice_id')->limit(1),
                $sortDir
            );
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $creditNotes = $query->paginate(15)->withQueryString();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('credit-notes/Index', [
            'creditNotes' => $creditNotes,
            'customers' => $customers,
            'currentCompany' => $currentCompany,
            'filters' => [
                ...$request->only(['status', 'customer_id', 'search', 'sort_by', 'sort_dir']),
                'column_filters' => $columnFilters->all(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $taxRates = TaxRate::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'is_default_sales']);

        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);

        $selectedInvoice = null;
        if ($request->filled('invoice_id')) {
            $selectedInvoice = Invoice::with('customer', 'lineItems.product', 'lineItems.taxRate')->find($request->invoice_id);
        }

        $selectedCustomer = null;
        if ($request->filled('customer_id')) {
            $selectedCustomer = Customer::find($request->customer_id);
        }

        return Inertia::render('credit-notes/Create', [
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'currentCompany' => $currentCompany,
            'selectedInvoice' => $selectedInvoice,
            'selectedCustomer' => $selectedCustomer,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $cid = $currentCompany->id;

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'invoice_id' => ['nullable', CompanyScopedRules::invoice($cid, true)],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', CompanyScopedRules::invoice($cid, true)],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'credit_note_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'line_groups' => 'nullable|array|min:1',
            'line_groups.*.id' => 'nullable|integer',
            'line_groups.*.name' => 'required_with:line_groups|string|max:255',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($cid)],
            'line_items.*.account_code' => 'nullable|string',
            'line_items.*.line_group_id' => 'nullable|integer',
        ]);

        $creditNote = CreditNote::create([
            'company_id' => $currentCompany->id,
            'customer_id' => $validated['customer_id'],
            'invoice_id' => $validated['invoice_id'] ?? null,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($currentCompany->id),
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'credit_note_date' => $validated['credit_note_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'draft',
        ]);

        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $creditNote->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        foreach ($validated['line_items'] as $index => $itemData) {
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            $discountAmount = $itemData['discount_amount'] ?? 0;
            $discountPercentage = $itemData['discount_percentage'] ?? 0;

            $subtotal = $quantity * $unitPrice;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = $subtotal - $discountAmount;

            $lineTaxAmount = 0;
            if (! empty($itemData['tax_rate_id'])) {
                $taxRateModel = TaxRate::find($itemData['tax_rate_id']);
                if ($taxRateModel) {
                    $lineTaxAmount = round($total * ($taxRateModel->rate / 100), 2);
                }
            }

            CreditNoteLineItem::create([
                'credit_note_id' => $creditNote->id,
                'line_group_id' => $groupMap[(string) ($itemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $itemData['product_id'] ?? null,
                'tax_rate_id' => $itemData['tax_rate_id'] ?? null,
                'account_id' => $itemData['account_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                'tax_amount' => $lineTaxAmount,
                'total' => $total,
                'account_code' => $itemData['account_code'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $creditNote->calculateTotals();
        $this->applyAllocationsFromRequest($creditNote, $validated['allocations'] ?? null, $currentCompany->id);
        $this->applyLegacyInvoiceAllocationIfPresent($creditNote, $validated['invoice_id'] ?? null, $currentCompany->id);
        $this->syncCreditNoteStatusFromBalance($creditNote);
        $this->syncInvoiceStatusesAfterCreditNoteChange($creditNote);

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', "Credit note {$creditNote->credit_note_number} created successfully.");
    }

    public function show(CreditNote $creditNote): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $creditNote->load([
            'customer',
            'invoice',
            'allocations.invoice',
            'lineItems.product',
            'lineItems.taxRate',
            'lineItems.lineGroup',
            'lineGroups',
            'company',
            'payments',
        ]);

        $creditNote->calculateTotals();
        $this->syncCreditNoteStatusFromBalance($creditNote);

        $creditNoteData = $creditNote->toArray();
        $creditNoteData['allocations'] = collect($creditNoteData['allocations'] ?? [])
            ->map(function ($alloc) use ($currentCompany) {
                $invoice = $alloc['invoice'] ?? null;
                if (! $invoice && ! empty($alloc['invoice_id'])) {
                    $resolved = Invoice::where('company_id', $currentCompany->id)
                        ->whereKey($alloc['invoice_id'])
                        ->first(['id', 'invoice_number', 'title']);
                    if ($resolved) {
                        $invoice = $resolved->toArray();
                    }
                }

                return [
                    'id' => $alloc['id'] ?? null,
                    'invoice_id' => $alloc['invoice_id'] ?? null,
                    'amount' => $alloc['amount'] ?? 0,
                    'invoice' => $invoice ? [
                        'id' => $invoice['id'] ?? null,
                        'invoice_number' => $invoice['invoice_number'] ?? null,
                        'title' => $invoice['title'] ?? null,
                    ] : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('credit-notes/Show', [
            'creditNote' => $creditNoteData,
        ]);
    }

    public function edit(CreditNote $creditNote): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $creditNote->load(['customer', 'invoice', 'allocations.invoice', 'lineItems.product', 'lineItems.taxRate', 'lineItems.lineGroup', 'lineGroups']);

        $taxRates = TaxRate::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'is_default_sales']);

        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);

        return Inertia::render('credit-notes/Edit', [
            'creditNote' => $creditNote,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'currentCompany' => $currentCompany,
        ]);
    }

    public function searchInvoices(Request $request): JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $search = trim((string) $request->string('q', ''));
        $customerId = $request->integer('customer_id');
        $invoiceId = $request->integer('invoice_id');

        $invoices = Invoice::query()
            ->where('company_id', $currentCompany->id)
            ->whereNotIn('status', ['cancelled'])
            ->when(
                $invoiceId,
                fn ($query) => $query->where('id', $invoiceId),
                function ($query) use ($customerId, $search) {
                    $query
                        ->when($customerId, fn ($subQuery) => $subQuery->where('customer_id', $customerId))
                        ->when($search !== '', function ($subQuery) use ($search) {
                            $subQuery->where(function ($q) use ($search) {
                                $q->where('invoice_number', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%");
                            });
                        });
                }
            )
            ->with('customer:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'invoice_number', 'title', 'customer_id', 'total']);

        return response()->json($invoices);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $search = trim((string) $request->string('q', ''));

        $products = Product::query()
            ->where('company_id', $currentCompany->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'sku', 'price']);

        return response()->json($products);
    }

    public function update(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $cid = $creditNote->company_id;

        $validated = $request->validate([
            'customer_id' => ['required', CompanyScopedRules::customer($cid)],
            'invoice_id' => ['nullable', CompanyScopedRules::invoice($cid, true)],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations', CompanyScopedRules::invoice($cid, true)],
            'allocations.*.amount' => ['required_with:allocations', 'numeric', 'min:0.01'],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'credit_note_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'line_groups' => 'nullable|array|min:1',
            'line_groups.*.id' => 'nullable|integer',
            'line_groups.*.name' => 'required_with:line_groups|string|max:255',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($cid)],
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($cid)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($cid)],
            'line_items.*.account_code' => 'nullable|string',
            'line_items.*.line_group_id' => 'nullable|integer',
        ]);

        $creditNote->update([
            'customer_id' => $validated['customer_id'],
            'invoice_id' => $validated['invoice_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'credit_note_date' => $validated['credit_note_date'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $creditNote->lineItems()->delete();
        $creditNote->lineGroups()->delete();
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];
        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $creditNote->lineGroups()->create([
                'name' => $groupData['name'] ?: 'Items',
                'sort_order' => $groupIndex,
            ]);
            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }
        $defaultGroupId = reset($groupMap);

        foreach ($validated['line_items'] as $index => $itemData) {
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            $discountAmount = $itemData['discount_amount'] ?? 0;
            $discountPercentage = $itemData['discount_percentage'] ?? 0;

            $subtotal = $quantity * $unitPrice;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = $subtotal - $discountAmount;

            $lineTaxAmount = 0;
            if (! empty($itemData['tax_rate_id'])) {
                $taxRateModel = TaxRate::find($itemData['tax_rate_id']);
                if ($taxRateModel) {
                    $lineTaxAmount = round($total * ($taxRateModel->rate / 100), 2);
                }
            }

            CreditNoteLineItem::create([
                'credit_note_id' => $creditNote->id,
                'line_group_id' => $groupMap[(string) ($itemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $itemData['product_id'] ?? null,
                'tax_rate_id' => $itemData['tax_rate_id'] ?? null,
                'account_id' => $itemData['account_id'] ?? null,
                'description' => $itemData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'discount_percentage' => $itemData['discount_percentage'] ?? 0,
                'tax_amount' => $lineTaxAmount,
                'total' => $total,
                'account_code' => $itemData['account_code'] ?? null,
                'sort_order' => $index,
            ]);
        }

        $creditNote->calculateTotals();
        $this->applyAllocationsFromRequest($creditNote, $validated['allocations'] ?? null, $creditNote->company_id);
        $this->applyLegacyInvoiceAllocationIfPresent($creditNote, $validated['invoice_id'] ?? null, $creditNote->company_id);
        $this->syncCreditNoteStatusFromBalance($creditNote);
        $this->syncInvoiceStatusesAfterCreditNoteChange($creditNote);

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', "Credit note {$creditNote->credit_note_number} updated successfully.");
    }

    public function destroy(CreditNote $creditNote): RedirectResponse
    {
        $number = $creditNote->credit_note_number;
        $creditNote->lineItems()->delete();
        $creditNote->delete();

        return redirect()->route('credit-notes.index')
            ->with('success', "Credit note {$number} deleted successfully.");
    }

    public function updateStatus(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,authorised,paid,voided',
        ]);

        $creditNote->update(['status' => $validated['status']]);

        return redirect()->back()
            ->with('success', "Credit note status updated to {$validated['status']}.");
    }

    public function storePayment(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('view', $creditNote);

        $currentCompany = auth()->user()->getCurrentCompany();

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,eft',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $creditNote->refresh();
        $remainingCredit = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
        if ((float) $validated['amount'] > $remainingCredit) {
            return redirect()->back()->with('error', 'Refund amount cannot exceed remaining credit of '.number_format($remainingCredit, 2));
        }

        $payment = Payment::create([
            'invoice_id' => null,
            'credit_note_id' => $creditNote->id,
            'company_id' => $currentCompany->id,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_date' => $validated['payment_date'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $creditNote->refresh();
        $creditNote->calculateTotals();
        $this->syncCreditNoteStatusFromBalance($creditNote);

        if ($creditNote->xero_credit_note_id) {
            try {
                $xeroService = new XeroService($currentCompany);
                if ($xeroService->isConfigured()) {
                    $xeroService->syncCreditNotePaymentsToXero($creditNote);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to sync credit note refund to Xero', [
                    'credit_note_id' => $creditNote->id,
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->back()->with('success', 'Refund payment added successfully.');
    }

    public function destroyPayment(CreditNote $creditNote, Payment $payment): RedirectResponse
    {
        $this->authorize('detachRefundPayment', [$creditNote, $payment]);

        $currentCompany = auth()->user()->getCurrentCompany();

        if ((int) $payment->credit_note_id !== (int) $creditNote->id) {
            return redirect()->back()->with('error', 'This payment does not belong to the selected credit note.');
        }

        if ($payment->xero_payment_id) {
            try {
                $xeroService = new XeroService($currentCompany);
                if ($xeroService->isConfigured()) {
                    $xeroService->deletePaymentInXero($payment);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to delete credit note refund in Xero during local deletion', [
                    'credit_note_id' => $creditNote->id,
                    'payment_id' => $payment->id,
                    'xero_payment_id' => $payment->xero_payment_id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->with('error', 'Failed to delete refund payment in Xero. Local refund was not removed.');
            }
        }

        $payment->delete();
        $creditNote->refresh();
        $creditNote->calculateTotals();
        $this->syncCreditNoteStatusFromBalance($creditNote);

        return redirect()->back()->with('success', 'Refund payment removed successfully.');
    }

    private function syncInvoiceStatusesAfterCreditNoteChange(CreditNote $creditNote): void
    {
        $invoiceIds = $creditNote->allocations()->pluck('invoice_id')->all();
        if ($invoiceIds === []) {
            $invoiceIds = $creditNote->invoice_id ? [$creditNote->invoice_id] : [];
        }

        foreach (array_unique(array_map('intval', $invoiceIds)) as $invoiceId) {
            if (! $invoiceId) {
                continue;
            }

            $invoice = Invoice::find($invoiceId);
            if (! $invoice) {
                continue;
            }

            $invoice->refresh();
            if ($invoice->isFullyPaid() && $invoice->status !== 'paid') {
                $invoice->update(['status' => 'paid']);
            }
        }
    }

    private function syncCreditNoteStatusFromBalance(CreditNote $creditNote): void
    {
        $creditNote->refresh();
        $allocated = (float) $creditNote->allocations()->sum('amount');
        $remaining = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount') - $allocated, 2));
        $creditNote->update(['remaining_credit' => $remaining]);

        if ($creditNote->status !== 'voided') {
            // Credit notes allocated to an invoice must be authorised for outbound Xero sync.
            if (($creditNote->invoice_id || $creditNote->allocations()->exists()) && in_array($creditNote->status, ['draft', 'submitted'], true)) {
                $creditNote->update(['status' => 'authorised']);
            }

            if ($remaining <= 0.01 && $creditNote->status !== 'paid') {
                $creditNote->update(['status' => 'paid']);
            }

            // Invoice-linked notes can legitimately stay paid when the credit has been allocated
            // to settle the invoice, even if no refund payments exist on the note itself.
            if (! $creditNote->invoice_id && ! $creditNote->allocations()->exists() && $remaining > 0.01 && $creditNote->status === 'paid') {
                $creditNote->update(['status' => 'authorised']);
            }
        }
    }

    /**
     * @param  array<int, array{invoice_id:int, amount:float|int|string}>|null  $allocations
     */
    private function applyAllocationsFromRequest(CreditNote $creditNote, ?array $allocations, int $companyId): void
    {
        if ($allocations === null) {
            return;
        }

        $creditNote->allocations()->delete();

        $rows = collect($allocations)
            ->map(function ($row) {
                return [
                    'invoice_id' => (int) ($row['invoice_id'] ?? 0),
                    'amount' => round((float) ($row['amount'] ?? 0), 2),
                ];
            })
            ->filter(fn ($row) => $row['invoice_id'] > 0 && $row['amount'] > 0)
            ->unique('invoice_id')
            ->values();

        $totalToAllocate = (float) $rows->sum('amount');
        $maxAllocatable = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
        if ($totalToAllocate - $maxAllocatable > 0.01) {
            throw ValidationException::withMessages([
                'allocations' => ['Total allocated amount cannot exceed the available credit on this credit note.'],
            ]);
        }

        foreach ($rows as $row) {
            $invoice = Invoice::where('company_id', $companyId)->whereKey($row['invoice_id'])->first();
            if (! $invoice) {
                continue;
            }
            if ((int) $invoice->customer_id !== (int) $creditNote->customer_id) {
                throw ValidationException::withMessages([
                    'allocations' => ['Allocated invoices must belong to the same customer as the credit note.'],
                ]);
            }
            if ($row['amount'] - (float) $invoice->remaining_balance > 0.01) {
                throw ValidationException::withMessages([
                    'allocations' => ["Allocation amount cannot exceed invoice remaining balance for invoice {$invoice->invoice_number}."],
                ]);
            }
            CreditNoteAllocation::create([
                'company_id' => $companyId,
                'credit_note_id' => $creditNote->id,
                'invoice_id' => $row['invoice_id'],
                'amount' => $row['amount'],
            ]);
        }

        // Keep legacy single invoice_id in sync for the common one-invoice case (e.g. lists/filters).
        $creditNote->refresh();
        $invoiceIds = $creditNote->allocations()->pluck('invoice_id')->all();
        $creditNote->update([
            'invoice_id' => count($invoiceIds) === 1 ? (int) $invoiceIds[0] : null,
        ]);
    }

    private function applyLegacyInvoiceAllocationIfPresent(CreditNote $creditNote, $invoiceIdRaw, int $companyId): void
    {
        // If the new allocations UI is being used, do not auto-create legacy allocations.
        if ($creditNote->allocations()->exists()) {
            return;
        }

        $invoiceId = (int) ($invoiceIdRaw ?? 0);
        if ($invoiceId <= 0) {
            return;
        }

        $invoice = Invoice::where('company_id', $companyId)->whereKey($invoiceId)->first();
        if (! $invoice) {
            return;
        }

        if ((int) $invoice->customer_id !== (int) $creditNote->customer_id) {
            throw ValidationException::withMessages([
                'invoice_id' => ['Selected invoice must belong to the same customer as the credit note.'],
            ]);
        }

        $maxAllocatable = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
        $amount = min((float) $invoice->remaining_balance, $maxAllocatable);
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return;
        }

        CreditNoteAllocation::create([
            'company_id' => $companyId,
            'credit_note_id' => $creditNote->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
        ]);

        $creditNote->update(['invoice_id' => $invoice->id]);
    }
}
