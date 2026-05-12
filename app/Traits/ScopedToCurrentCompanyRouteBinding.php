<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

trait ScopedToCurrentCompanyRouteBinding
{
    /**
     * Retrieve the model for route binding, limited to the authenticated user's current company.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        \Illuminate\Support\Facades\Log::info('resolveRouteBinding', [
            'value' => $value,
            'field' => $field,
            'company_id_input' => \Illuminate\Support\Facades\Request::input('company_id'),
            'user_id' => $user->id,
        ]);

        $companyId = \Illuminate\Support\Facades\Request::input('company_id');

        if (! $companyId) {
            $company = $user->getCurrentCompany();
            if ($company === null) {
                return null;
            }
            $companyId = $company->id;
        }

        \Illuminate\Support\Facades\Log::info('resolveRouteBinding query', [
            'company_id' => $companyId,
        ]);

        return static::query()
            ->where($field, $value)
            ->where('company_id', $companyId)
            ->first();
    }
}
