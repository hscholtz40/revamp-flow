<?php

namespace App\Services;

use App\Models\Contact;
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

            $customer = Customer::create($payload);
            $this->syncPrimaryContactFromCustomerFields($customer);

            return $customer;
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
            $this->syncPrimaryContactFromCustomerFields($customer->fresh());

            return $customer->fresh();
        });
    }

    public function quickCreateForCompany(array $validated, int $companyId): Customer
    {
        return DB::transaction(function () use ($validated, $companyId) {
            $companyTel = $validated['company_tel'] ?? null;

            $customer = Customer::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'registration_number' => $validated['registration_number'] ?? null,
                'email' => $validated['email'],
                'company_cell' => $validated['company_cell'] ?? null,
                'company_tel' => $companyTel,
                'phone' => $companyTel,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'country' => $validated['country'] ?? null,
                'contact_first_name' => $validated['contact_first_name'] ?? null,
                'contact_last_name' => $validated['contact_last_name'] ?? null,
                'contact_cell' => $validated['contact_cell'] ?? null,
                'contact_email' => $validated['contact_email'] ?? null,
                'vat_number' => $validated['vat_number'] ?? null,
                'terms' => $this->normalizeTerms($validated['terms'] ?? null),
                'account_code' => Customer::generateAccountCode($validated['name'], $companyId),
            ]);

            $this->syncPrimaryContactFromCustomerFields($customer);

            return $customer;
        });
    }

    private function normalizePayload(array $validated, int $companyId, ?Customer $customer = null): array
    {
        $payload = $validated;
        $payload['company_id'] = $companyId;
        $payload['terms'] = $this->normalizeTerms($validated['terms'] ?? null);

        // Keep legacy phone column in sync for documents/integrations that still read it.
        $payload['phone'] = $payload['company_tel'] ?? $payload['phone'] ?? $customer?->phone;

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

    private function syncPrimaryContactFromCustomerFields(Customer $customer): void
    {
        $firstName = trim((string) ($customer->contact_first_name ?? ''));
        $lastName = trim((string) ($customer->contact_last_name ?? ''));
        $cell = trim((string) ($customer->contact_cell ?? ''));
        $email = trim((string) ($customer->contact_email ?? ''));

        if ($firstName === '' && $lastName === '' && $cell === '' && $email === '') {
            return;
        }

        $name = $customer->contactPersonName() ?? 'Primary Contact';

        $contact = Contact::query()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->where('is_primary', true)
            ->first();

        if (! $contact) {
            $contact = Contact::query()
                ->where('company_id', $customer->company_id)
                ->where('customer_id', $customer->id)
                ->when(
                    $email !== '',
                    fn ($query) => $query->where('email', $email),
                    fn ($query) => $query->where('name', $name)
                )
                ->first();
        }

        if ($contact) {
            if (! $contact->is_primary) {
                $this->clearPrimaryContactsForCustomer($customer, $contact->id);
            }

            $contact->update([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $cell !== '' ? $cell : null,
                'position' => $contact->position ?: 'Contact person',
                'is_primary' => true,
            ]);

            return;
        }

        $this->clearPrimaryContactsForCustomer($customer);

        Contact::create([
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $cell !== '' ? $cell : null,
            'position' => 'Contact person',
            'is_primary' => true,
        ]);
    }

    private function clearPrimaryContactsForCustomer(Customer $customer, ?int $exceptContactId = null): void
    {
        Contact::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->where('is_primary', true)
            ->when($exceptContactId, fn ($query) => $query->where('id', '!=', $exceptContactId))
            ->update(['is_primary' => false]);
    }
}
