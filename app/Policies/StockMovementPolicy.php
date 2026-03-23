<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class StockMovementPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->ownsCompanyResource($user, $stockMovement);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, StockMovement $stockMovement): bool
    {
        return $this->ownsCompanyResource($user, $stockMovement);
    }

    public function delete(User $user, StockMovement $stockMovement): bool
    {
        return $this->ownsCompanyResource($user, $stockMovement);
    }
}
