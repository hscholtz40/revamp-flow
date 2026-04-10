<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

class CustomerUpsertService
{
    public function createForCompany(array $validated, int $companyId): Customer
    {
        return DB::transaction(function () use ($validated, $companyId) {
            $payload = $this->normalizePayload($validated, $companyId);

            if ($payload['is_default_sales'] ?? false) {
                $this->clearDefaultSalesForCompany($companyId);
            }

            return Customer::create($payload);
        });
    }

    public function update(Customer $customer, array $validated): Customer
    {
        return DB::transaction(function () use ($customer, $validated) {
            $payload = $this->normalizePayload($validated, $customer->company_id, $customer);

            if ($payload['is_default_sales'] ?? false) {
                $this->clearDefaultSalesForCompany($customer->company_id, $customer->id);
            }

            $customer->update($payload);

            return $customer->fresh();
        });
    }

    public function quickCreateForCompany(array $validated, int $companyId): Customer
    {
        return Customer::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'terms' => $this->normalizeTerms($validated['terms'] ?? null),
            'account_code' => Customer::generateAccountCode($validated['name'], $companyId),
        ]);
    }

    private function normalizePayload(array $validated, int $companyId, ?Customer $customer = null): array
    {
        $payload = $validated;
        $payload['company_id'] = $companyId;
        $payload['terms'] = $this->normalizeTerms($validated['terms'] ?? null);

        if (empty($payload['account_code'])) {
            $payload['account_code'] = $customer?->account_code ?: Customer::generateAccountCode($payload['name'], $companyId);
        }

        return $payload;
    }

    private function normalizeTerms(?string $terms): string
    {
        $normalized = trim((string) $terms);

        return $normalized !== '' ? $normalized : 'COD';
    }

    private function clearDefaultSalesForCompany(int $companyId, ?int $exceptCustomerId = null): void
    {
        Customer::where('company_id', $companyId)
            ->where('is_default_sales', true)
            ->when($exceptCustomerId, fn ($query) => $query->where('id', '!=', $exceptCustomerId))
            ->update(['is_default_sales' => false]);
    }
}
