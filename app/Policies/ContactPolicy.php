<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ContactPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, Contact $contact): bool
    {
        return $this->ownsCompanyResource($user, $contact);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, Contact $contact): bool
    {
        return $this->ownsCompanyResource($user, $contact);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $this->ownsCompanyResource($user, $contact);
    }
}
