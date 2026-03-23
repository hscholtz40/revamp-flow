<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksTenantOwnership
{
    protected function userHasTenantContext(User $user): bool
    {
        return $user->getCurrentCompany() !== null;
    }

    /**
     * @param  object|null  $model  Model with company_id scoped to the tenant
     */
    protected function ownsCompanyResource(User $user, ?object $model): bool
    {
        if ($model === null) {
            return false;
        }

        $company = $user->getCurrentCompany();
        if ($company === null) {
            return false;
        }

        $resourceCompanyId = data_get($model, 'company_id');
        if ($resourceCompanyId === null || $resourceCompanyId === '') {
            return false;
        }

        $resourceCompanyId = (int) $resourceCompanyId;
        if ($resourceCompanyId !== (int) $company->id) {
            return false;
        }

        return $user->hasAccessToCompany($resourceCompanyId);
    }
}
