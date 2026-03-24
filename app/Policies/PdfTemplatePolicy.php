<?php

namespace App\Policies;

use App\Models\PdfTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class PdfTemplatePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, PdfTemplate $pdfTemplate): bool
    {
        return $this->ownsCompanyResource($user, $pdfTemplate);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, PdfTemplate $pdfTemplate): bool
    {
        return $this->ownsCompanyResource($user, $pdfTemplate);
    }

    public function delete(User $user, PdfTemplate $pdfTemplate): bool
    {
        return $this->ownsCompanyResource($user, $pdfTemplate);
    }
}
