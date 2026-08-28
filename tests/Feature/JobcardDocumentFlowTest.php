<?php

use App\Models\Jobcard;
use App\Services\JobcardUpsertService;

it('autosaves a new jobcard draft with incomplete line items', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $response = $this->actingAs($user)->postJson(route('jobcards.autosave.store'), [
        'customer_id' => $customer->id,
        'title' => '',
        'status' => 'new',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'line_items' => [
            [
                'description' => '',
                'quantity' => 1,
                'unit_price' => 0,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('jobcard.status', 'new');

    $jobcard = Jobcard::query()->findOrFail($response->json('jobcard.id'));
    $jobcard->load('lineItems');

    expect($jobcard->customer_id)->toBe($customer->id)
        ->and($jobcard->title)->toBe('Untitled jobcard')
        ->and($jobcard->lineItems)->toHaveCount(1)
        ->and($jobcard->lineItems->first()->description)->toBe('');
});

it('autosaves updates to an existing new jobcard', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $jobcard = app(JobcardUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'New Jobcard',
        'status' => 'new',
        'line_items' => [
            [
                'description' => 'Original line',
                'quantity' => 1,
                'unit_price' => 50,
            ],
        ],
    ], $company->id);

    $this->actingAs($user)->putJson(route('jobcards.autosave.update', $jobcard), [
        'customer_id' => $customer->id,
        'title' => 'Autosaved Jobcard',
        'status' => 'new',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'line_items' => [
            [
                'description' => 'Autosaved line',
                'quantity' => 2,
                'unit_price' => 75,
                'line_group_id' => 1,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('jobcard.id', $jobcard->id)
        ->assertJsonPath('jobcard.status', 'new');

    $jobcard->refresh()->load('lineItems');

    expect($jobcard->title)->toBe('Autosaved Jobcard')
        ->and($jobcard->lineItems)->toHaveCount(1)
        ->and($jobcard->lineItems->first()->description)->toBe('Autosaved line')
        ->and((float) $jobcard->total)->toBe(150.0);
});

it('rejects autosave updates when jobcard status is not new', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $jobcard = app(JobcardUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Scheduled Jobcard',
        'status' => 'new',
        'line_items' => [
            [
                'description' => 'Line',
                'quantity' => 1,
                'unit_price' => 10,
            ],
        ],
    ], $company->id);

    $jobcard->update(['status' => 'scheduled']);

    $this->actingAs($user)->putJson(route('jobcards.autosave.update', $jobcard), [
        'customer_id' => $customer->id,
        'title' => 'Should not save',
        'status' => 'new',
        'line_items' => [],
    ])->assertStatus(422);
});
