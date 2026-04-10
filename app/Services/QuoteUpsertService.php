<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Illuminate\Support\Facades\DB;

class QuoteUpsertService
{
    public function createForCompany(array $validated, int $companyId, int $salespersonId): Quote
    {
        return DB::transaction(function () use ($validated, $companyId, $salespersonId) {
            $quote = Quote::create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'salesperson_id' => $salespersonId,
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'quote_number' => Quote::generateQuoteNumber($companyId),
                'order_number' => $validated['order_number'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ]);

            $this->syncLineGroupsAndItems($quote, $validated);

            return $quote->fresh();
        });
    }

    public function update(Quote $quote, array $validated): Quote
    {
        return DB::transaction(function () use ($quote, $validated) {
            $quote->update([
                'customer_id' => $validated['customer_id'],
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'order_number' => $validated['order_number'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'expiry_date' => $validated['expiry_date'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ]);

            $quote->lineItems()->delete();
            $quote->lineGroups()->delete();

            $this->syncLineGroupsAndItems($quote, $validated);

            return $quote->fresh();
        });
    }

    private function syncLineGroupsAndItems(Quote $quote, array $validated): void
    {
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];

        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $quote->lineGroups()->create([
                'name' => ($groupData['name'] ?? '') !== '' ? $groupData['name'] : 'Items',
                'sort_order' => $groupIndex,
            ]);

            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }

        $defaultGroupId = reset($groupMap) ?: null;
        $defaultSalesAccountId = ChartOfAccount::getDefaultSalesForCompany($quote->company_id)?->id;

        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new QuoteLineItem([
                'quote_id' => $quote->id,
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'account_id' => $lineItemData['account_id'] ?? $defaultSalesAccountId,
                'sort_order' => $index,
            ]);

            $lineItem->calculateTotal();
            $lineItem->save();
        }

        $quote->calculateTotals();
    }
}
