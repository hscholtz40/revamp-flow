<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class CustomerPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->ownsCompanyResource($user, $customer);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->ownsCompanyResource($user, $customer);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->ownsCompanyResource($user, $customer);
    }
}
