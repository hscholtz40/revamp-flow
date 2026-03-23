<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ReportPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Report $report): bool
    {
        return $this->ownsCompanyResource($user, $report);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->ownsCompanyResource($user, $report);
    }

    public function delete(User $user, Report $report): bool
    {
        return $this->ownsCompanyResource($user, $report);
    }

    public function export(User $user, Report $report): bool
    {
        return $this->ownsCompanyResource($user, $report);
    }
}
