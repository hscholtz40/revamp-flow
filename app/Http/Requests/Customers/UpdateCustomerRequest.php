<?php

namespace App\Http\Requests\Customers;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? ($this->user()?->can('update', $customer) ?? false)
            : false;
    }

    public function rules(): array
    {
        /** @var Customer|null $customer */
        $customer = $this->route('customer');
        $companyId = $customer?->company_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->ignore($customer?->id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'terms' => ['nullable', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'account_code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'account_code')
                    ->ignore($customer?->id)
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'notes' => ['nullable', 'string'],
            'is_default_sales' => ['boolean'],
        ];
    }
}
