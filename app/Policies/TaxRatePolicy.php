<?php

namespace App\Policies;

use App\Models\TaxRate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class TaxRatePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, TaxRate $taxRate): bool
    {
        return $this->ownsCompanyResource($user, $taxRate);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, TaxRate $taxRate): bool
    {
        return $this->ownsCompanyResource($user, $taxRate);
    }

    public function delete(User $user, TaxRate $taxRate): bool
    {
        return $this->ownsCompanyResource($user, $taxRate);
    }
}
