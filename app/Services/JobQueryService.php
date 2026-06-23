<?php

namespace App\Services;

use App\Exceptions\JobQueryNotActionableException;
use App\Models\Query;
use Illuminate\Support\Facades\DB;

/**
 * Owns the accept/decline lifecycle for contractor job queries. A single job
 * (one external quote) is dispatched as several sibling Query rows — one per
 * matched contractor company. The first contractor to accept claims it; the
 * remaining pending copies are marked "expired" and locked out.
 *
 * This is the single source of truth shared by the web UI (QueriesController)
 * and the mobile API (Api\V1\JobQueryController).
 */
class JobQueryService
{
    /**
     * Atomically claim a job for the given query's contractor.
     *
     * Locks the whole sibling set for the external quote so concurrent accepts
     * are serialized — exactly one winner, the rest expire.
     *
     * @throws JobQueryNotActionableException when this copy is no longer pending
     *                                         or another contractor already won.
     */
    public function accept(Query $query): Query
    {
        return DB::transaction(function () use ($query) {
            // Lock every copy of this job (including this one) for the duration
            // of the transaction so two simultaneous accepts can't both win.
            $siblings = Query::jobs()
                ->where('external_quote_id', $query->external_quote_id)
                ->lockForUpdate()
                ->get();

            $self = $siblings->firstWhere('id', $query->id);

            if (! $self || $self->response !== Query::RESPONSE_PENDING) {
                throw JobQueryNotActionableException::responded();
            }

            if ($siblings->contains(fn (Query $sibling) => $sibling->response === Query::RESPONSE_ACCEPTED)) {
                throw JobQueryNotActionableException::taken();
            }

            $self->update([
                'response' => Query::RESPONSE_ACCEPTED,
                'responded_at' => now(),
                'status' => Query::STATUS_CLOSED,
            ]);

            // First-accept-wins: expire the other contractors' still-pending copies.
            Query::jobs()
                ->where('external_quote_id', $self->external_quote_id)
                ->where('id', '!=', $self->id)
                ->where('response', Query::RESPONSE_PENDING)
                ->update([
                    'response' => Query::RESPONSE_EXPIRED,
                    'responded_at' => now(),
                    'status' => Query::STATUS_CLOSED,
                ]);

            return $self->refresh();
        });
    }

    /**
     * Decline a job for this contractor only — siblings are unaffected.
     *
     * @throws JobQueryNotActionableException when this copy is no longer pending.
     */
    public function decline(Query $query): Query
    {
        return DB::transaction(function () use ($query) {
            $self = Query::jobs()->whereKey($query->id)->lockForUpdate()->first();

            if (! $self || $self->response !== Query::RESPONSE_PENDING) {
                throw JobQueryNotActionableException::responded();
            }

            $self->update([
                'response' => Query::RESPONSE_DECLINED,
                'responded_at' => now(),
                'status' => Query::STATUS_CLOSED,
            ]);

            return $self->refresh();
        });
    }
}
