<?php

namespace App\Http\Requests\Invoices;

use App\Models\Invoice;
use App\Support\CompanyScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->getCurrentCompany()?->id;

        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'customer_id' => ['required', CompanyScopedRules::customer($companyId)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($companyId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:255'],
            'salesperson_id' => ['nullable', CompanyScopedRules::staffUser()],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string', 'max:255'],
            'terms_conditions' => ['nullable', 'string'],
            'source_type' => ['nullable', 'in:quote,jobcard,license'],
            'source_id' => ['nullable', 'integer'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($companyId)],
            'line_items.*.description' => ['required', 'string'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($companyId)],
            'line_items.*.account_id' => ['nullable', CompanyScopedRules::chartOfAccount($companyId)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
            'line_items.*.serial_number_ids' => ['nullable', 'array'],
            'line_items.*.serial_number_ids.*' => ['nullable', CompanyScopedRules::productSerialNumberForCompany($companyId)],
        ];
    }

    public function after(): array
    {
        $companyId = $this->user()?->getCurrentCompany()?->id;

        return [
            CompanyScopedRules::afterValidateLineItemSerialsMatchProduct($companyId),
        ];
    }
}
