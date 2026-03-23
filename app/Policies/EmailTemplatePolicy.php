<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class EmailTemplatePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, EmailTemplate $emailTemplate): bool
    {
        return $this->ownsCompanyResource($user, $emailTemplate);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $this->ownsCompanyResource($user, $emailTemplate);
    }

    public function delete(User $user, EmailTemplate $emailTemplate): bool
    {
        return $this->ownsCompanyResource($user, $emailTemplate);
    }
}
