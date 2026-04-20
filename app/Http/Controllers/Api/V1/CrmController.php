<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    public function customers(Request $request)
    {
        return response()->json(
            Customer::query()
                ->where('company_id', $this->companyId($request))
                ->orderBy('name')
                ->paginate(25)
        );
    }

    public function showCustomer(Request $request, Customer $customer)
    {
        $this->assertCompanyRecord($request, (int) $customer->company_id);

        return response()->json($customer->load(['contacts']));
    }

    public function storeCustomer(Request $request)
    {
        $companyId = $this->companyId($request);
        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer = Customer::create([
            ...$payload,
            'company_id' => $companyId,
            'account_code' => Customer::generateAccountCode($payload['name'], $companyId),
        ]);

        return response()->json($customer, 201);
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $this->assertCompanyRecord($request, (int) $customer->company_id);
        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $customer->update($payload);

        return response()->json($customer->fresh());
    }

    public function destroyCustomer(Request $request, Customer $customer)
    {
        $this->assertCompanyRecord($request, (int) $customer->company_id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted']);
    }

    public function searchCustomers(Request $request)
    {
        $term = (string) $request->query('q', '');

        return response()->json(
            Customer::query()
                ->where('company_id', $this->companyId($request))
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->limit(20)
                ->get(['id', 'name', 'email', 'phone'])
        );
    }

    public function quickCreateCustomer(Request $request)
    {
        return $this->storeCustomer($request);
    }

    public function sendCustomerEmail(Request $request, Customer $customer)
    {
        $this->assertCompanyRecord($request, (int) $customer->company_id);
        $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        return response()->json(['message' => 'Email queued']);
    }

    public function sendCustomerSms(Request $request, Customer $customer)
    {
        $this->assertCompanyRecord($request, (int) $customer->company_id);
        $request->validate([
            'message' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json(['message' => 'SMS queued']);
    }

    public function contacts(Request $request)
    {
        return response()->json(
            Contact::query()
                ->where('company_id', $this->companyId($request))
                ->with(['customer:id,name', 'supplier:id,name'])
                ->orderBy('name')
                ->paginate(25)
        );
    }

    public function showContact(Request $request, Contact $contact)
    {
        $this->assertCompanyRecord($request, (int) $contact->company_id);

        return response()->json($contact->load(['customer:id,name', 'supplier:id,name']));
    }

    public function storeContact(Request $request)
    {
        $payload = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $contact = Contact::create([
            ...$payload,
            'company_id' => $this->companyId($request),
        ]);

        return response()->json($contact, 201);
    }

    public function updateContact(Request $request, Contact $contact)
    {
        $this->assertCompanyRecord($request, (int) $contact->company_id);
        $payload = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_primary' => ['nullable', 'boolean'],
        ]);
        $contact->update($payload);

        return response()->json($contact->fresh());
    }

    public function destroyContact(Request $request, Contact $contact)
    {
        $this->assertCompanyRecord($request, (int) $contact->company_id);
        $contact->delete();

        return response()->json(['message' => 'Contact deleted']);
    }

    public function searchContacts(Request $request)
    {
        $term = (string) $request->query('q', '');

        return response()->json(
            Contact::query()
                ->where('company_id', $this->companyId($request))
                ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
                ->limit(20)
                ->get(['id', 'name', 'email', 'phone'])
        );
    }

    public function quickCreateContact(Request $request)
    {
        return $this->storeContact($request);
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
