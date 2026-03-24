<?php

namespace App\Policies;

use App\Models\ReportTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class ReportTemplatePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    /**
     * Company-owned templates and global defaults (null company_id) readable in tenant context.
     */
    public function view(User $user, ReportTemplate $template): bool
    {
        if (! $this->userHasTenantContext($user)) {
            return false;
        }

        if ($template->company_id === null) {
            return true;
        }

        return $this->ownsCompanyResource($user, $template);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, ReportTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    public function delete(User $user, ReportTemplate $template): bool
    {
        if (! $this->userHasTenantContext($user)) {
            return false;
        }

        if ($template->company_id === null) {
            return true;
        }

        return $this->ownsCompanyResource($user, $template);
    }
}
