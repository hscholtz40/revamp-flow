<?php

use App\Exceptions\JobQueryNotActionableException;
use App\Models\Query;
use App\Services\JobQueryService;

function makeJobQuery(int $companyId, string $externalQuoteId, string $response = Query::RESPONSE_PENDING): Query
{
    return Query::create([
        'company_id' => $companyId,
        'kind' => Query::KIND_JOB,
        'external_source' => 'revamp',
        'external_quote_id' => $externalQuoteId,
        'contractor_company_key' => 'KEY-'.$companyId,
        'name' => 'Test',
        'surname' => 'Client',
        'email' => 'client@example.com',
        'cell' => '+27123456789',
        'description' => 'Tile a bathroom',
        'status' => Query::STATUS_OPEN,
        'response' => $response,
        'job_location' => 'Somewhere',
        'job_latitude' => -26.2041,
        'job_longitude' => 28.0473,
    ]);
}

it('claims the job for the first accepter and expires the other contractors', function () {
    $a = coverageCreateCompany();
    $b = coverageCreateCompany();
    $c = coverageCreateCompany();

    $qa = makeJobQuery($a->id, 'QUO-1');
    $qb = makeJobQuery($b->id, 'QUO-1');
    $qc = makeJobQuery($c->id, 'QUO-1');

    $accepted = app(JobQueryService::class)->accept($qa);

    expect($accepted->response)->toBe(Query::RESPONSE_ACCEPTED)
        ->and($accepted->status)->toBe(Query::STATUS_CLOSED)
        ->and($qb->fresh()->response)->toBe(Query::RESPONSE_EXPIRED)
        ->and($qc->fresh()->response)->toBe(Query::RESPONSE_EXPIRED)
        ->and($qb->fresh()->status)->toBe(Query::STATUS_CLOSED);
});

it('rejects an accept once another contractor has claimed the job', function () {
    $a = coverageCreateCompany();
    $b = coverageCreateCompany();

    // Simulate a race: B already accepted while A's copy is still pending.
    $qa = makeJobQuery($a->id, 'QUO-2');
    makeJobQuery($b->id, 'QUO-2', Query::RESPONSE_ACCEPTED);

    app(JobQueryService::class)->accept($qa);
})->throws(JobQueryNotActionableException::class);

it('declines only the current contractor copy', function () {
    $a = coverageCreateCompany();
    $b = coverageCreateCompany();

    $qa = makeJobQuery($a->id, 'QUO-3');
    $qb = makeJobQuery($b->id, 'QUO-3');

    $declined = app(JobQueryService::class)->decline($qa);

    expect($declined->response)->toBe(Query::RESPONSE_DECLINED)
        ->and($qb->fresh()->response)->toBe(Query::RESPONSE_PENDING);
});

it('rejects acting on an already-responded job', function () {
    $a = coverageCreateCompany();
    $qa = makeJobQuery($a->id, 'QUO-4', Query::RESPONSE_DECLINED);

    app(JobQueryService::class)->accept($qa);
})->throws(JobQueryNotActionableException::class);
