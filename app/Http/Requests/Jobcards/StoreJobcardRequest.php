<?php

namespace App\Http\Requests\Jobcards;

use App\Models\Jobcard;
use App\Support\CompanyScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreJobcardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Jobcard::class) ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()?->getCurrentCompany()?->id;

        return [
            'customer_id' => ['required', CompanyScopedRules::customer($companyId)],
            'contact_id' => ['nullable', CompanyScopedRules::contactForRequest($companyId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'service_address' => ['nullable', 'string', 'max:500'],
            'source_type' => ['nullable', 'in:quote'],
            'source_id' => ['nullable', 'integer', 'required_with:source_type'],
            'assigned_to_user_id' => ['nullable', CompanyScopedRules::staffUser($companyId)],
            'assigned_to_team_id' => ['nullable', CompanyScopedRules::team($companyId)],
            'order_number' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:new,needs_scheduling,scheduled,dispatched,accepted,en_route,on_site,paused,waiting_for_parts,needs_follow_up,emergency,completed,cancelled'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'line_groups' => ['nullable', 'array', 'min:1'],
            'line_groups.*.id' => ['nullable', 'integer'],
            'line_groups.*.name' => ['required_with:line_groups', 'string', 'max:255'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.product_id' => ['nullable', CompanyScopedRules::product($companyId)],
            'line_items.*.supplier_id' => ['nullable', CompanyScopedRules::supplier($companyId)],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'numeric'],
            'line_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'line_items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'line_items.*.tax_rate_id' => ['nullable', CompanyScopedRules::taxRate($companyId)],
            'line_items.*.line_group_id' => ['nullable', 'integer'],
        ];
    }
}
