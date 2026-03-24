<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PurchaseOrderPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->ownsCompanyResource($user, $purchaseOrder);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->ownsCompanyResource($user, $purchaseOrder);
    }

    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $this->ownsCompanyResource($user, $purchaseOrder);
    }
}
