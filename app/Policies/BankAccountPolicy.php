<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class BankAccountPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $this->ownsCompanyResource($user, $bankAccount);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $this->ownsCompanyResource($user, $bankAccount);
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->ownsCompanyResource($user, $bankAccount);
    }
}
