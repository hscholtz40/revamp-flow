<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class InvoicePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user)
            && $user->hasModulePermission('invoices', 'view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->ownsCompanyResource($user, $invoice)
            && $user->hasModulePermission('invoices', 'view');
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user)
            && $user->hasModulePermission('invoices', 'create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if (! $this->ownsCompanyResource($user, $invoice)
            || ! $user->hasModulePermission('invoices', 'edit')) {
            return false;
        }

        if ($invoice->status === 'paid'
            && ! $user->hasModulePermission('invoices', 'edit_completed')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if (! $this->ownsCompanyResource($user, $invoice)
            || ! $user->hasModulePermission('invoices', 'delete')) {
            return false;
        }

        if ($invoice->status === 'paid'
            && ! $user->hasModulePermission('invoices', 'edit_completed')) {
            return false;
        }

        return true;
    }
}
