<?php

namespace App\Policies;

use App\Models\Query;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class QueryPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Query $query): bool
    {
        return $this->ownsCompanyResource($user, $query);
    }

    public function update(User $user, Query $query): bool
    {
        return $this->ownsCompanyResource($user, $query);
    }

    public function delete(User $user, Query $query): bool
    {
        return $this->ownsCompanyResource($user, $query);
    }
}
