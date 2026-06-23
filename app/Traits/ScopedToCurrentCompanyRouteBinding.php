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

        $user = Request::user() ?? auth()->user();
        if ($user === null) {
            return null;
        }

        $companyId = request()->input('company_id');

        \Illuminate\Support\Facades\Log::info('resolveRouteBinding START', [
            'value' => $value,
            'field' => $field,
            'company_id' => $companyId,
            'user_id' => $user->id,
        ]);

        if (! $companyId) {
            $company = $user->getCurrentCompany();
            \Illuminate\Support\Facades\Log::info('getCurrentCompany result', [
                'company' => $company ? ['id' => $company->id, 'name' => $company->name] : null,
            ]);
            if ($company === null) {
                return null;
            }
            $companyId = $company->id;
        }

        $result = static::query()
            ->where($field, $value)
            ->where('company_id', $companyId)
            ->first();

        \Illuminate\Support\Facades\Log::info('resolveRouteBinding result', [
            'result_id' => $result?->id,
        ]);

        return $result;
    }
}
