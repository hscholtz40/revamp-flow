<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->getCurrentCompany()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_cell' => ['nullable', 'string', 'max:50'],
            'company_tel' => ['nullable', 'string', 'max:50'],
            'contact_first_name' => ['nullable', 'string', 'max:100'],
            'contact_last_name' => ['nullable', 'string', 'max:100'],
            'contact_cell' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'account_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'account_code')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'notes' => ['nullable', 'string'],
            'is_default_sales' => ['boolean'],
        ];
    }
}
