<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class TimeEntryPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, TimeEntry $timeEntry): bool
    {
        if (! $this->ownsCompanyResource($user, $timeEntry)) {
            return false;
        }

        return $this->nonLimitedOrOwnsEntry($user, $timeEntry);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $this->view($user, $timeEntry);
    }

    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        if ($user->isLimitedUser()) {
            return false;
        }

        return $this->ownsCompanyResource($user, $timeEntry);
    }

    /**
     * Limited users may only act on their own time rows; others use company scope only.
     */
    protected function nonLimitedOrOwnsEntry(User $user, TimeEntry $timeEntry): bool
    {
        if (! $user->isLimitedUser()) {
            return true;
        }

        return (int) $timeEntry->user_id === (int) $user->id;
    }
}
