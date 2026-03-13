<?php

namespace App\Http\Controllers;

use App\Models\CreditNote;
use App\Models\CreditNoteLineItem;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ChartOfAccount;
use App\Models\TaxRate;
use App\Services\XeroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CreditNotesController extends Controller
{
    public function index(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();
        $sortBy = $request->input('sort_by', 'credit_note_number');
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

        $sortableFields = ['credit_note_number', 'customer_name', 'invoice_number', 'credit_note_date', 'status', 'total', 'remaining_credit', 'created_at'];
        if (!in_array($sortBy, $sortableFields, true)) {
            $sortBy = 'credit_note_number';
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
            'filters' => $request->only(['status', 'customer_id', 'search', 'sort_by', 'sort_dir']),
        ]);
    }

    public function create(Request $request): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => $p->price,
            ]);

        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'invoice_number', 'title', 'customer_id', 'total', 'status']);

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
            'customers' => $customers,
            'products' => $products,
            'invoices' => $invoices,
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

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'credit_note_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => 'nullable|exists:tax_rates,id',
            'line_items.*.account_id' => 'nullable|exists:chart_of_accounts,id',
            'line_items.*.account_code' => 'nullable|string',
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

        foreach ($validated['line_items'] as $index => $itemData) {
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            $discountAmount = $itemData['discount_amount'] ?? 0;
            $discountPercentage = $itemData['discount_percentage'] ?? 0;

            $subtotal = $quantity * $unitPrice;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = max(0, $subtotal - $discountAmount);

            $lineTaxAmount = 0;
            if (!empty($itemData['tax_rate_id'])) {
                $taxRateModel = TaxRate::find($itemData['tax_rate_id']);
                if ($taxRateModel) {
                    $lineTaxAmount = ceil(($total * ($taxRateModel->rate / 100)) * 100) / 100;
                }
            }

            CreditNoteLineItem::create([
                'credit_note_id' => $creditNote->id,
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
        $this->syncCreditNoteStatusFromBalance($creditNote);
        $this->syncInvoiceStatusAfterCreditNoteChange($creditNote);

        return redirect()->route('credit-notes.show', $creditNote)
            ->with('success', "Credit note {$creditNote->credit_note_number} created successfully.");
    }

    public function show(CreditNote $creditNote): Response
    {
        $creditNote->load([
            'customer',
            'invoice',
            'lineItems.product',
            'lineItems.taxRate',
            'company',
            'payments',
        ]);

        $creditNote->calculateTotals();
        $this->syncCreditNoteStatusFromBalance($creditNote);

        return Inertia::render('credit-notes/Show', [
            'creditNote' => $creditNote,
        ]);
    }

    public function edit(CreditNote $creditNote): Response
    {
        $currentCompany = auth()->user()->getCurrentCompany();

        $creditNote->load(['customer', 'invoice', 'lineItems.product', 'lineItems.taxRate']);

        $customers = Customer::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get();

        $products = Product::where('company_id', $currentCompany->id)
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => $p->price,
            ]);

        $invoices = Invoice::where('company_id', $currentCompany->id)
            ->whereNotIn('status', ['cancelled'])
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'invoice_number', 'title', 'customer_id', 'total', 'status']);

        $taxRates = TaxRate::where('company_id', $currentCompany->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'rate', 'is_default_sales']);

        $defaultSalesTaxRate = TaxRate::getDefaultSalesForCompany($currentCompany->id);
        $chartOfAccounts = ChartOfAccount::where('company_id', $currentCompany->id)->where('is_active', true)->ordered()->get(['id', 'account_code', 'account_name', 'account_type', 'is_default_sales']);
        $defaultSalesAccount = ChartOfAccount::getDefaultSalesForCompany($currentCompany->id);

        return Inertia::render('credit-notes/Edit', [
            'creditNote' => $creditNote,
            'customers' => $customers,
            'products' => $products,
            'invoices' => $invoices,
            'taxRates' => $taxRates,
            'defaultSalesTaxRateId' => $defaultSalesTaxRate?->id,
            'chartOfAccounts' => $chartOfAccounts,
            'defaultSalesAccountId' => $defaultSalesAccount?->id,
            'currentCompany' => $currentCompany,
        ]);
    }

    public function update(Request $request, CreditNote $creditNote): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'credit_note_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'line_items' => 'required|array|min:1',
            'line_items.*.product_id' => 'nullable|exists:products,id',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|integer|min:1',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.discount_amount' => 'nullable|numeric|min:0',
            'line_items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'line_items.*.tax_rate_id' => 'nullable|exists:tax_rates,id',
            'line_items.*.account_id' => 'nullable|exists:chart_of_accounts,id',
            'line_items.*.account_code' => 'nullable|string',
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

        foreach ($validated['line_items'] as $index => $itemData) {
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'];
            $discountAmount = $itemData['discount_amount'] ?? 0;
            $discountPercentage = $itemData['discount_percentage'] ?? 0;

            $subtotal = $quantity * $unitPrice;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = max(0, $subtotal - $discountAmount);

            $lineTaxAmount = 0;
            if (!empty($itemData['tax_rate_id'])) {
                $taxRateModel = TaxRate::find($itemData['tax_rate_id']);
                if ($taxRateModel) {
                    $lineTaxAmount = ceil(($total * ($taxRateModel->rate / 100)) * 100) / 100;
                }
            }

            CreditNoteLineItem::create([
                'credit_note_id' => $creditNote->id,
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
        $this->syncCreditNoteStatusFromBalance($creditNote);
        $this->syncInvoiceStatusAfterCreditNoteChange($creditNote);

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
        $currentCompany = auth()->user()->getCurrentCompany();
        if ($creditNote->company_id !== $currentCompany->id) {
            return redirect()->back()->with('error', 'You do not have access to this credit note.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,card,eft',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $creditNote->refresh();
        $remainingCredit = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
        if ((float) $validated['amount'] > $remainingCredit) {
            return redirect()->back()->with('error', 'Refund amount cannot exceed remaining credit of ' . number_format($remainingCredit, 2));
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
        $currentCompany = auth()->user()->getCurrentCompany();
        if ($creditNote->company_id !== $currentCompany->id || $payment->company_id !== $currentCompany->id) {
            return redirect()->back()->with('error', 'You do not have access to this refund payment.');
        }

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

    private function syncInvoiceStatusAfterCreditNoteChange(CreditNote $creditNote): void
    {
        if (!$creditNote->invoice_id) {
            return;
        }

        $invoice = Invoice::find($creditNote->invoice_id);
        if (!$invoice) {
            return;
        }

        $invoice->refresh();
        if ($invoice->isFullyPaid() && $invoice->status !== 'paid') {
            $invoice->update(['status' => 'paid']);
        }
    }

    private function syncCreditNoteStatusFromBalance(CreditNote $creditNote): void
    {
        $creditNote->refresh();
        $remaining = max(0, round((float) $creditNote->total - (float) $creditNote->payments()->sum('amount'), 2));
        $creditNote->update(['remaining_credit' => $remaining]);

        if ($creditNote->status !== 'voided') {
            // Credit notes allocated to an invoice must be authorised for outbound Xero sync.
            if ($creditNote->invoice_id && in_array($creditNote->status, ['draft', 'submitted'], true)) {
                $creditNote->update(['status' => 'authorised']);
            }

            if ($remaining <= 0.01 && $creditNote->status !== 'paid') {
                $creditNote->update(['status' => 'paid']);
            }

            if ($remaining > 0.01 && $creditNote->status === 'paid') {
                $creditNote->update(['status' => 'authorised']);
            }
        }
    }
}
