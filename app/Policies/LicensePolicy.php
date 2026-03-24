<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class LicensePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, License $license): bool
    {
        return $this->ownsCompanyResource($user, $license);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, License $license): bool
    {
        return $this->ownsCompanyResource($user, $license);
    }

    public function delete(User $user, License $license): bool
    {
        return $this->ownsCompanyResource($user, $license);
    }
}
