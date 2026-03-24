<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class QuotePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user)
            && $user->hasModulePermission('quotes', 'view');
    }

    public function view(User $user, Quote $quote): bool
    {
        return $this->ownsCompanyResource($user, $quote)
            && $user->hasModulePermission('quotes', 'view');
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user)
            && $user->hasModulePermission('quotes', 'create');
    }

    public function update(User $user, Quote $quote): bool
    {
        if (! $this->ownsCompanyResource($user, $quote)
            || ! $user->hasModulePermission('quotes', 'edit')) {
            return false;
        }

        if ($quote->status === 'accepted'
            && ! $user->hasModulePermission('quotes', 'edit_completed')) {
            return false;
        }

        return true;
    }

    public function delete(User $user, Quote $quote): bool
    {
        if (! $this->ownsCompanyResource($user, $quote)
            || ! $user->hasModulePermission('quotes', 'delete')) {
            return false;
        }

        if ($quote->status === 'accepted'
            && ! $user->hasModulePermission('quotes', 'edit_completed')) {
            return false;
        }

        return true;
    }

    /**
     * Quote → jobcard conversion (tenant + quotes edit).
     */
    public function convertToJobcard(User $user, Quote $quote): bool
    {
        return $this->ownsCompanyResource($user, $quote)
            && $user->hasModulePermission('quotes', 'edit');
    }

    /**
     * Redirect to invoice create from quote (quotes edit + invoices create).
     */
    public function convertToInvoice(User $user, Quote $quote): bool
    {
        return $this->ownsCompanyResource($user, $quote)
            && $user->hasModulePermission('quotes', 'edit')
            && $user->hasModulePermission('invoices', 'create');
    }
}
