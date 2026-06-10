<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class DispatchCompanyResolver
{
    public static function companyIdForUser(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        $company = $user->getCurrentCompany();
        if ($company) {
            return (int) $company->id;
        }

        $membershipCompanyId = $user->companies()
            ->where('companies.is_active', true)
            ->value('companies.id');

        if ($membershipCompanyId) {
            return (int) $membershipCompanyId;
        }

        $defaultCompany = Company::getDefault();

        return $defaultCompany ? (int) $defaultCompany->id : 0;
    }
}
