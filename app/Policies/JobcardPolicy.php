<?php

namespace App\Policies;

use App\Models\Jobcard;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class JobcardPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Jobcard $jobcard): bool
    {
        if (! $this->ownsCompanyResource($user, $jobcard)) {
            return false;
        }

        return $this->limitedUserMayAccessJobcard($user, $jobcard);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Jobcard $jobcard): bool
    {
        return $this->ownsCompanyResource($user, $jobcard);
    }

    public function delete(User $user, Jobcard $jobcard): bool
    {
        return $this->ownsCompanyResource($user, $jobcard);
    }

    public function updateStatus(User $user, Jobcard $jobcard): bool
    {
        if (! $this->ownsCompanyResource($user, $jobcard)) {
            return false;
        }

        return $this->limitedUserMayAccessJobcard($user, $jobcard);
    }

    public function convertToQuote(User $user, Jobcard $jobcard): bool
    {
        return $this->ownsCompanyResource($user, $jobcard)
            && $user->hasModulePermission('quotes', 'create');
    }

    public function convertToInvoice(User $user, Jobcard $jobcard): bool
    {
        return $this->ownsCompanyResource($user, $jobcard)
            && $user->hasModulePermission('invoices', 'create');
    }

    public function convertToDeliveryNote(User $user, Jobcard $jobcard): bool
    {
        return $this->ownsCompanyResource($user, $jobcard)
            && $user->hasModulePermission('delivery-notes', 'create');
    }

    protected function limitedUserMayAccessJobcard(User $user, Jobcard $jobcard): bool
    {
        if (! $user->isLimitedUser()) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id')->map(fn ($id) => (int) $id)->all();
        $isAssignedToUser = (int) $jobcard->assigned_to_user_id === (int) $user->id;
        $isAssignedToTeam = $jobcard->assigned_to_team_id
            && in_array((int) $jobcard->assigned_to_team_id, $teamIds, true);

        return $isAssignedToUser || $isAssignedToTeam;
    }
}
