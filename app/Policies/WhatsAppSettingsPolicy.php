<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppSettings;
use App\Policies\Concerns\ChecksTenantOwnership;

class WhatsAppSettingsPolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, WhatsAppSettings $whatsAppSettings): bool
    {
        return $this->ownsCompanyResource($user, $whatsAppSettings);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, WhatsAppSettings $whatsAppSettings): bool
    {
        return $this->ownsCompanyResource($user, $whatsAppSettings);
    }

    public function delete(User $user, WhatsAppSettings $whatsAppSettings): bool
    {
        return $this->ownsCompanyResource($user, $whatsAppSettings);
    }
}
