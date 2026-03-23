<?php

namespace App\Policies;

use App\Models\CreditNote;
use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksTenantOwnership;

class CreditNotePolicy
{
    use ChecksTenantOwnership;

    public function viewAny(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $this->ownsCompanyResource($user, $creditNote);
    }

    public function create(User $user): bool
    {
        return $this->userHasTenantContext($user);
    }

    public function update(User $user, CreditNote $creditNote): bool
    {
        return $this->ownsCompanyResource($user, $creditNote);
    }

    public function delete(User $user, CreditNote $creditNote): bool
    {
        return $this->ownsCompanyResource($user, $creditNote);
    }

    /**
     * Remove a refund payment row scoped to this credit note (policy resolved via CreditNote).
     */
    public function detachRefundPayment(User $user, CreditNote $creditNote, Payment $payment): bool
    {
        return $this->ownsCompanyResource($user, $creditNote)
            && $this->ownsCompanyResource($user, $payment)
            && (int) $payment->credit_note_id === (int) $creditNote->id;
    }
}
