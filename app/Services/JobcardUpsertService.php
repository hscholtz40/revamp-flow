<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use Illuminate\Support\Facades\DB;

class JobcardUpsertService
{
    public function createForCompany(array $validated, int $companyId): Jobcard
    {
        return DB::transaction(function () use ($validated, $companyId) {
            $jobcard = Jobcard::create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'service_address' => $validated['service_address'] ?? null,
                'source_type' => $validated['source_type'] ?? null,
                'source_id' => $validated['source_id'] ?? null,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'assigned_to_team_id' => $validated['assigned_to_team_id'] ?? null,
                'job_number' => Jobcard::generateJobNumber($companyId),
                'order_number' => $validated['order_number'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'priority' => $validated['priority'] ?? 'normal',
                'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ]);

            $this->syncLineGroupsAndItems($jobcard, $validated);

            return $jobcard->fresh();
        });
    }

    public function update(Jobcard $jobcard, array $validated): Jobcard
    {
        return DB::transaction(function () use ($jobcard, $validated) {
            $jobcard->update([
                'customer_id' => $validated['customer_id'],
                'contact_id' => $validated['contact_id'] ?? null,
                'email' => $validated['email'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'service_address' => $validated['service_address'] ?? null,
                'assigned_to_user_id' => $validated['assigned_to_user_id'] ?? null,
                'assigned_to_team_id' => $validated['assigned_to_team_id'] ?? null,
                'order_number' => $validated['order_number'] ?? null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'status' => $validated['status'],
                'priority' => $validated['priority'] ?? 'normal',
                'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'due_date' => $validated['due_date'] ?? null,
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'discount_percentage' => $validated['discount_percentage'] ?? 0,
                'notes' => $validated['notes'] ?? null,
                'terms_conditions' => $validated['terms_conditions'] ?? null,
            ]);

            if (
                ! empty($validated['source_type'])
                && ! empty($validated['source_id'])
                && (! $jobcard->source_type || ! $jobcard->source_id)
            ) {
                $jobcard->update([
                    'source_type' => $validated['source_type'],
                    'source_id' => $validated['source_id'],
                ]);
            }

            $jobcard->lineItems()->delete();
            $jobcard->lineGroups()->delete();

            $this->syncLineGroupsAndItems($jobcard, $validated);

            return $jobcard->fresh();
        });
    }

    private function syncLineGroupsAndItems(Jobcard $jobcard, array $validated): void
    {
        $groupPayload = $validated['line_groups'] ?? [['name' => 'Items']];
        $groupMap = [];

        foreach (array_values($groupPayload) as $groupIndex => $groupData) {
            $group = $jobcard->lineGroups()->create([
                'name' => ($groupData['name'] ?? '') !== '' ? $groupData['name'] : 'Items',
                'sort_order' => $groupIndex,
            ]);

            $groupMap[(string) ($groupData['id'] ?? ($groupIndex + 1))] = $group->id;
        }

        $defaultGroupId = reset($groupMap) ?: null;
        $defaultSalesAccountId = ChartOfAccount::getDefaultSalesForCompany($jobcard->company_id)?->id;

        foreach ($validated['line_items'] as $index => $lineItemData) {
            $lineItem = new JobcardLineItem([
                'line_group_id' => $groupMap[(string) ($lineItemData['line_group_id'] ?? '')] ?? $defaultGroupId,
                'product_id' => $lineItemData['product_id'] ?? null,
                'supplier_id' => $lineItemData['supplier_id'] ?? null,
                'description' => $lineItemData['description'],
                'quantity' => $lineItemData['quantity'],
                'unit_price' => $lineItemData['unit_price'],
                'cost' => $lineItemData['cost'] ?? 0,
                'discount_amount' => $lineItemData['discount_amount'] ?? 0,
                'discount_percentage' => $lineItemData['discount_percentage'] ?? 0,
                'tax_rate_id' => $lineItemData['tax_rate_id'] ?? null,
                'account_id' => $lineItemData['account_id'] ?? $defaultSalesAccountId,
                'sort_order' => $index,
            ]);

            $lineItem->calculateTotal();
            $jobcard->lineItems()->save($lineItem);
        }

        $jobcard->calculateTotals();
    }
}
