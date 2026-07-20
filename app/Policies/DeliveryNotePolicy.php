<?php

namespace App\Policies;

use App\Models\DeliveryNote;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class DeliveryNotePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, DeliveryNote $deliveryNote): bool
    {
        return $this->ownsCompanyResource($user, $deliveryNote);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, DeliveryNote $deliveryNote): bool
    {
        return $this->ownsCompanyResource($user, $deliveryNote);
    }

    public function delete(User $user, DeliveryNote $deliveryNote): bool
    {
        return $this->ownsCompanyResource($user, $deliveryNote);
    }
}
