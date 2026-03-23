<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PaymentPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->ownsCompanyResource($user, $payment);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $this->ownsCompanyResource($user, $payment);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $this->ownsCompanyResource($user, $payment);
    }
}
