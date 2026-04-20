<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use Illuminate\Http\Request;

class DocumentsController extends Controller
{
    public function purchaseOrders(Request $request)
    {
        return response()->json(
            PurchaseOrder::query()->where('company_id', $this->companyId($request))->with('supplier:id,name')->orderByDesc('id')->paginate(25)
        );
    }

    public function showPurchaseOrder(Request $request, PurchaseOrder $po)
    {
        $this->assertCompanyRecord($request, (int) $po->company_id);

        return response()->json($po->load(['supplier', 'items.product']));
    }

    public function storePurchaseOrder(Request $request)
    {
        $payload = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
        $companyId = $this->companyId($request);

        $po = PurchaseOrder::create([
            ...$payload,
            'company_id' => $companyId,
            'user_id' => $request->user()->id,
            'status' => 'draft',
            'po_number' => PurchaseOrder::generatePONumber($companyId),
        ]);

        return response()->json($po, 201);
    }

    public function updatePurchaseOrder(Request $request, PurchaseOrder $po)
    {
        $this->assertCompanyRecord($request, (int) $po->company_id);
        $payload = $request->validate([
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,sent,partial,received,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);
        $po->update($payload);

        return response()->json($po->fresh());
    }

    public function destroyPurchaseOrder(Request $request, PurchaseOrder $po)
    {
        $this->assertCompanyRecord($request, (int) $po->company_id);
        $po->delete();

        return response()->json(['message' => 'Purchase order deleted']);
    }

    public function receivePurchaseOrder(Request $request, PurchaseOrder $po)
    {
        $this->assertCompanyRecord($request, (int) $po->company_id);
        $payload = $request->validate([
            'item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'quantity_received' => ['required', 'integer', 'min:1'],
        ]);

        $item = PurchaseOrderItem::query()->where('purchase_order_id', $po->id)->whereKey($payload['item_id'])->firstOrFail();
        $item->quantity_received = min((int) $item->quantity, (int) $item->quantity_received + (int) $payload['quantity_received']);
        $item->save();

        if ($po->isFullyReceived()) {
            $po->update(['status' => 'received', 'received_date' => now()->toDateString()]);
        } else {
            $po->update(['status' => 'partial']);
        }

        return response()->json($po->fresh()->load('items'));
    }

    public function quotes(Request $request)
    {
        return response()->json(
            Quote::query()->where('company_id', $this->companyId($request))->with('customer:id,name')->orderByDesc('id')->paginate(25)
        );
    }

    public function showQuote(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);

        return response()->json($quote->load(['customer', 'lineItems']));
    }

    public function storeQuote(Request $request)
    {
        $companyId = $this->companyId($request);
        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expiry_date' => ['nullable', 'date'],
        ]);

        $quote = Quote::create([
            ...$payload,
            'company_id' => $companyId,
            'quote_number' => Quote::generateQuoteNumber($companyId),
            'status' => 'draft',
        ]);

        return response()->json($quote, 201);
    }

    public function updateQuote(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);
        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,sent,accepted,rejected,expired'],
        ]);
        $quote->update($payload);

        return response()->json($quote->fresh());
    }

    public function destroyQuote(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);
        $quote->delete();

        return response()->json(['message' => 'Quote deleted']);
    }

    public function updateQuoteStatus(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);
        $payload = $request->validate([
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
        ]);
        $quote->update(['status' => $payload['status']]);

        return response()->json($quote->fresh());
    }

    public function convertQuoteToInvoice(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);
        $invoice = $quote->convertToInvoice();

        return response()->json($invoice, 201);
    }

    public function convertQuoteToJobcard(Request $request, Quote $quote)
    {
        $this->assertCompanyRecord($request, (int) $quote->company_id);
        $jobcard = $quote->convertToJobcard();

        return response()->json($jobcard, 201);
    }

    public function invoices(Request $request)
    {
        return response()->json(
            Invoice::query()->where('company_id', $this->companyId($request))->with('customer:id,name')->orderByDesc('id')->paginate(25)
        );
    }

    public function showInvoice(Request $request, Invoice $invoice)
    {
        $this->assertCompanyRecord($request, (int) $invoice->company_id);

        return response()->json($invoice->load(['customer', 'lineItems', 'payments']));
    }

    public function storeInvoice(Request $request)
    {
        $companyId = $this->companyId($request);
        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
        ]);

        $invoice = Invoice::create([
            ...$payload,
            'company_id' => $companyId,
            'salesperson_id' => $request->user()->id,
            'invoice_number' => Invoice::generateInvoiceNumber($companyId),
            'status' => 'draft',
        ]);

        return response()->json($invoice, 201);
    }

    public function updateInvoice(Request $request, Invoice $invoice)
    {
        $this->assertCompanyRecord($request, (int) $invoice->company_id);
        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,sent,paid,overdue,cancelled'],
        ]);
        $invoice->update($payload);

        return response()->json($invoice->fresh());
    }

    public function destroyInvoice(Request $request, Invoice $invoice)
    {
        $this->assertCompanyRecord($request, (int) $invoice->company_id);
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted']);
    }

    public function updateInvoiceStatus(Request $request, Invoice $invoice)
    {
        $this->assertCompanyRecord($request, (int) $invoice->company_id);
        $payload = $request->validate([
            'status' => ['required', 'in:draft,sent,paid,overdue,cancelled'],
        ]);
        $invoice->update(['status' => $payload['status']]);

        return response()->json($invoice->fresh());
    }

    public function addInvoicePayment(Request $request, Invoice $invoice)
    {
        $this->assertCompanyRecord($request, (int) $invoice->company_id);
        $payload = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,eft'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = Payment::create([
            ...$payload,
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
        ]);

        return response()->json($payment, 201);
    }

    public function creditNotes(Request $request)
    {
        return response()->json(
            CreditNote::query()->where('company_id', $this->companyId($request))->with('customer:id,name')->orderByDesc('id')->paginate(25)
        );
    }

    public function showCreditNote(Request $request, CreditNote $creditNote)
    {
        $this->assertCompanyRecord($request, (int) $creditNote->company_id);

        return response()->json($creditNote->load(['customer', 'lineItems', 'payments']));
    }

    public function storeCreditNote(Request $request)
    {
        $companyId = $this->companyId($request);
        $payload = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credit_note_date' => ['nullable', 'date'],
        ]);

        $creditNote = CreditNote::create([
            ...$payload,
            'company_id' => $companyId,
            'credit_note_number' => CreditNote::generateCreditNoteNumber($companyId),
            'status' => 'draft',
        ]);

        return response()->json($creditNote, 201);
    }

    public function updateCreditNote(Request $request, CreditNote $creditNote)
    {
        $this->assertCompanyRecord($request, (int) $creditNote->company_id);
        $payload = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'credit_note_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,submitted,authorised,paid,voided'],
        ]);
        $creditNote->update($payload);

        return response()->json($creditNote->fresh());
    }

    public function destroyCreditNote(Request $request, CreditNote $creditNote)
    {
        $this->assertCompanyRecord($request, (int) $creditNote->company_id);
        $creditNote->delete();

        return response()->json(['message' => 'Credit note deleted']);
    }

    public function updateCreditNoteStatus(Request $request, CreditNote $creditNote)
    {
        $this->assertCompanyRecord($request, (int) $creditNote->company_id);
        $payload = $request->validate([
            'status' => ['required', 'in:draft,submitted,authorised,paid,voided'],
        ]);
        $creditNote->update(['status' => $payload['status']]);

        return response()->json($creditNote->fresh());
    }

    private function companyId(Request $request): int
    {
        return (int) ($request->user()->getCurrentCompany()?->id ?? 0);
    }

    private function assertCompanyRecord(Request $request, int $recordCompanyId): void
    {
        abort_unless($recordCompanyId === $this->companyId($request), 404);
    }
}
