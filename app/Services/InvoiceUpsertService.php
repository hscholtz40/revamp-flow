<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Jobcard;
use App\Models\ProductSerialNumber;
use App\Models\TaxRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceUpsertService
{
    public function createForCompany(
        array $validated,
        int $companyId,
        string $invoiceNumber,
        string $dueDate,
        int $salespersonId,
        ?int $defaultAccountId,
        ?Jobcard $sourceJobcard,
        callable $syncStockAdjustments,
        callable $ensureRoundingLine
    ): Invoice {
        return DB::transaction(function () use (
            $validated,
            $companyId,
            $invoiceNumber,
            $dueDate,
            $salespersonId,
            $defaultAccountId,
            $sourceJobcard,
            $syncStockAdjustments,
            $ensureRoundingLine
        ) {
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'order_number' => $validated['order_number'] ?? null,
                'title' => ! empty(trim((string) ($validated['title'] ?? ''))) ? trim((string) $validated['title']) : $invoiceNumber,
                'description' => $validated['description'] ?? null,
                'customer_id' => $validated['customer_id'],
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'salesperson_id' => $salespersonId,
                'company_id' => $companyId,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $dueDate,
                'tax_rate' => $validated['tax_rate'],
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
            ]);

            $taxRates = $this->loadTaxRatesForLineItems($companyId, $validated['line_items']);
            $this->replaceLineGroupsAndItems($invoice, $validated, $defaultAccountId, $taxRates, true);

            $syncStockAdjustments(
                $companyId,
                $sourceJobcard,
                null,
                $validated['line_items'],
                "Invoice: {$invoiceNumber}",
                "Stock synced for invoice {$invoiceNumber}",
                $invoice->id
            );

            $ensureRoundingLine($invoice, $defaultAccountId);

            $invoice->calculateTotals();
            $invoice->refresh();
            $invoice->load('customer', 'company');

            return $invoice;
        });
    }

    public function update(
        Invoice $invoice,
        array $validated,
        bool $canEditSalesperson,
        ?int $defaultAccountId,
        ?Jobcard $sourceJobcard,
        callable $syncStockAdjustments,
        callable $ensureRoundingLine
    ): Invoice {
        return DB::transaction(function () use (
            $invoice,
            $validated,
            $canEditSalesperson,
            $defaultAccountId,
            $sourceJobcard,
            $syncStockAdjustments,
            $ensureRoundingLine
        ) {
            $updateData = [
                'title' => ! empty(trim((string) ($validated['title'] ?? ''))) ? trim((string) $validated['title']) : $invoice->invoice_number,
                'description' => $validated['description'] ?? null,
                'customer_id' => $validated['customer_id'],
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'order_number' => $validated['order_number'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'tax_rate' => $validated['tax_rate'],
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ];

            if ($canEditSalesperson && array_key_exists('salesperson_id', $validated)) {
                $updateData['salesperson_id'] = $validated['salesperson_id'];
            }

            $wasCancelled = $invoice->status === 'cancelled';
            $oldLineItems = $invoice->lineItems()->with('product')->get();

            $invoice->update($updateData);

            if (! $wasCancelled) {
                $this->restoreSerialNumbers($oldLineItems, $invoice->id);
            }

            $taxRates = $this->loadTaxRatesForLineItems($invoice->company_id, $validated['line_items']);
            $this->replaceLineGroupsAndItems($invoice, $validated, $defaultAccountId, $taxRates, ! $wasCancelled);

            $syncStockAdjustments(
                $invoice->company_id,
                $sourceJobcard,
                $wasCancelled ? null : $oldLineItems,
                $wasCancelled ? null : $validated['line_items'],
                "Invoice Updated: {$invoice->invoice_number}",
                'Stock synced for invoice line item update',
                $invoice->id
            );

            $ensureRoundingLine($invoice, $defaultAccountId);

            $invoice->calculateTotals();

            return $invoice->fresh();
        });
    }

    private function loadTaxRatesForLineItems(int $companyId, array $lineItems): Collection
    {
        $taxRateIds = collect($lineItems)
            ->pluck('tax_rate_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($taxRateIds->isEmpty()) {
            return collect();
        }

        return TaxRate::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $taxRateIds)
            ->get(['id', 'rate'])
            ->keyBy('id');
    }

    private function replaceLineGroupsAndItems(
        Invoice $invoice,
        array $validated,
        ?int $defaultAccountId,
        Collection $taxRates,
        bool $markSerialsSold
    ): void {
        $invoice->lineItems()->delete();
        $invoice->lineGroups()->delete();

        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];

        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $invoice->lineGroups()->create([
                'name' => ($groupData['name'] ?? '') ?: 'Items',
                'sort_order' => $groupIndex,
            ]);

            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }

        $defaultGroupId = reset($groupMap) ?: null;

        foreach ($validated['line_items'] as $index => $lineItemData) {
            $quantity = (int) ($lineItemData['quantity'] ?? 0);
            $unitPrice = (float) ($lineItemData['unit_price'] ?? 0);
            $discountAmount = (float) ($lineItemData['discount_amount'] ?? 0);
            $discountPercentage = (float) ($lineItemData['discount_percentage'] ?? 0);
            $subtotal = $quantity * $unitPrice;

            if ($discountPercentage > 0) {
                $discountAmount = $subtotal * ($discountPercentage / 100);
            }

            $total = $subtotal - $discountAmount;
            $taxRateId = $lineItemData['tax_rate_id'] ?? null;
            $rate = (float) ($taxRates->get((int) $taxRateId)?->rate ?? 0);
            $lineTaxAmount = $rate > 0 ? round($total * ($rate / 100), 2) : 0.0;

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'total' => $total,
                'tax_rate_id' => $taxRateId,
                'tax_amount' => $lineTaxAmount,
                'account_id' => $lineItemData['account_id'] ?? $defaultAccountId,
                'sort_order' => $index,
                'serial_number_ids' => $lineItemData['serial_number_ids'] ?? null,
            ]);

            if ($markSerialsSold) {
                $this->markSerialNumbersAsSold($invoice->id, $lineItemData);
            }
        }
    }

    private function restoreSerialNumbers(iterable $oldLineItems, int $invoiceId): void
    {
        foreach ($oldLineItems as $oldLineItem) {
            if (empty($oldLineItem->serial_number_ids)) {
                continue;
            }

            try {
                ProductSerialNumber::whereIn('id', $oldLineItem->serial_number_ids)
                    ->where('invoice_id', $invoiceId)
                    ->update([
                        'status' => 'available',
                        'invoice_id' => null,
                        'sale_date' => null,
                    ]);
            } catch (\Exception $e) {
                Log::error('Failed to restore serial numbers for updated invoice line item', [
                    'invoice_id' => $invoiceId,
                    'line_item_id' => $oldLineItem->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function markSerialNumbersAsSold(int $invoiceId, array $lineItemData): void
    {
        if (empty($lineItemData['serial_number_ids'])) {
            return;
        }

        try {
            ProductSerialNumber::whereIn('id', $lineItemData['serial_number_ids'])
                ->update([
                    'status' => 'sold',
                    'invoice_id' => $invoiceId,
                    'sale_date' => now(),
                ]);
        } catch (\Exception $e) {
            Log::error('Failed to update serial numbers for invoice line item', [
                'invoice_id' => $invoiceId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'serial_number_ids' => $lineItemData['serial_number_ids'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}
