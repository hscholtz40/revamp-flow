<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ChartOfAccountPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $this->ownsCompanyResource($user, $chartOfAccount);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $this->ownsCompanyResource($user, $chartOfAccount);
    }

    public function delete(User $user, ChartOfAccount $chartOfAccount): bool
    {
        return $this->ownsCompanyResource($user, $chartOfAccount);
    }
}
