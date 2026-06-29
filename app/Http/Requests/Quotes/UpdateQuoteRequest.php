<?php

namespace App\Http\Requests\Quotes;

use App\Models\Quote;
use App\Support\CompanyScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            ? ($this->user()?->can('update', $quote) ?? false)
            : false;
    }

    public function rules(): array
    {
        /** @var Quote|null $quote */
        $quote = $this->route('quote');
        $companyId = $quote?->company_id;

        return [
            'customer_id' => ['required', CompanyScopedRules::customer($companyId)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($companyId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,sent,accepted,rejected,expired'],
            'expiry_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($companyId)],
            'line_items.*.supplier_id' => ['nullable', CompanyScopedRules::supplier($companyId)],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($companyId)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($companyId)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ];
    }
}
