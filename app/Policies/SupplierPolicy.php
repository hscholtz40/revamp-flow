<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class SupplierPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->ownsCompanyResource($user, $supplier);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $this->ownsCompanyResource($user, $supplier);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $this->ownsCompanyResource($user, $supplier);
    }
}
