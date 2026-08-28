<?php

use App\Models\Jobcard;
use App\Models\Quote;
use App\Services\QuoteUpsertService;

it('stores a quote through the controller', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);
    $account = coverageSeedChartOfAccount($company);

    $response = $this->actingAs($user)->post(route('quotes.store'), [
        'customer_id' => $customer->id,
        'title' => 'Coverage Quote',
        'status' => 'draft',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Coverage line item',
                'quantity' => 2,
                'unit_price' => 125,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
                'line_group_id' => 1,
            ],
        ],
    ]);

    $quote = Quote::query()->where('title', 'Coverage Quote')->firstOrFail();

    $response->assertRedirect(route('quotes.show', $quote));

    expect($quote->customer_id)->toBe($customer->id)
        ->and((float) $quote->total)->toBeGreaterThan(0);
});

it('autosaves a new quote draft with incomplete line items', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $response = $this->actingAs($user)->postJson(route('quotes.autosave.store'), [
        'customer_id' => $customer->id,
        'title' => '',
        'status' => 'draft',
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
        ->assertJsonPath('quote.status', 'draft');

    $quote = Quote::query()->findOrFail($response->json('quote.id'));
    $quote->load('lineItems');

    expect($quote->customer_id)->toBe($customer->id)
        ->and($quote->title)->toBe('Untitled quote')
        ->and($quote->lineItems)->toHaveCount(1)
        ->and($quote->lineItems->first()->description)->toBe('');
});

it('autosaves updates to an existing quote draft', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Draft Quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Original line',
                'quantity' => 1,
                'unit_price' => 50,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->putJson(route('quotes.autosave.update', $quote), [
        'customer_id' => $customer->id,
        'title' => 'Autosaved Quote',
        'status' => 'draft',
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
        ->assertJsonPath('quote.id', $quote->id)
        ->assertJsonPath('quote.status', 'draft');

    $quote->refresh()->load('lineItems');

    expect($quote->title)->toBe('Autosaved Quote')
        ->and($quote->lineItems)->toHaveCount(1)
        ->and($quote->lineItems->first()->description)->toBe('Autosaved line')
        ->and((float) $quote->total)->toBe(150.0);
});

it('updates and changes quote status', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);
    $taxRate = coverageSeedTaxRate($company);
    $account = coverageSeedChartOfAccount($company);
    $service = app(QuoteUpsertService::class);

    $quote = $service->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Editable Quote',
        'status' => 'draft',
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Original line',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->put(route('quotes.update', $quote), [
        'customer_id' => $customer->id,
        'title' => 'Updated Quote Title',
        'status' => 'sent',
        'line_groups' => [
            ['id' => 1, 'name' => 'Updated Items'],
        ],
        'line_items' => [
            [
                'product_id' => $product->id,
                'description' => 'Updated line',
                'quantity' => 3,
                'unit_price' => 150,
                'tax_rate_id' => $taxRate->id,
                'account_id' => $account->id,
                'line_group_id' => 1,
            ],
        ],
    ])->assertRedirect(route('quotes.show', $quote));

    $this->from(route('quotes.show', $quote))
        ->actingAs($user)
        ->patch(route('quotes.update-status', $quote), [
            'status' => 'accepted',
        ])->assertRedirect(route('quotes.show', $quote));

    $quote->refresh();

    expect($quote->title)->toBe('Updated Quote Title')
        ->and($quote->status)->toBe('accepted');
});

it('deletes a quote', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);
    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Delete Me',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Delete line',
                'quantity' => 1,
                'unit_price' => 50,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->delete(route('quotes.destroy', $quote))
        ->assertRedirect(route('quotes.index'));

    expect(Quote::find($quote->id))->toBeNull();
});

it('shows linked jobcard warning on quote edit when quote was converted to a jobcard', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Converted quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $user->id);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'source_type' => 'quote',
        'source_id' => $quote->id,
        'job_number' => 'JC-LINK-'.uniqid(),
        'title' => 'Linked jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('quotes.edit', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Edit')
            ->where('linkedJobcard.id', $jobcard->id)
            ->where('linkedJobcard.job_number', $jobcard->job_number));
});

test('quote converted to jobcard via model stores quote source link', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['list', 'view', 'create', 'edit'],
        'jobcards' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Model converted quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $user->id);

    $jobcard = $quote->convertToJobcard();

    expect($jobcard->source_type)->toBe('quote')
        ->and($jobcard->source_id)->toBe($quote->id)
        ->and($quote->fresh()->status)->toBe('accepted')
        ->and($quote->fresh()->converted_jobcard_id)->toBe($jobcard->id);
});

it('shows linked jobcard warning on quote edit when quote has converted_jobcard_id', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Converted quote marker',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $user->id);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-MARK-'.uniqid(),
        'title' => 'Converted jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $quote->update(['converted_jobcard_id' => $jobcard->id]);

    $this->actingAs($user)
        ->get(route('quotes.edit', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Edit')
            ->where('linkedJobcard.id', $jobcard->id)
            ->where('linkedJobcard.job_number', $jobcard->job_number));
});

it('shows linked jobcard warning on quote edit when jobcard only has source_id set', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Loose link quote',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $user->id);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'source_id' => $quote->id,
        'job_number' => 'JC-LOOSE-'.uniqid(),
        'title' => 'Loose linked jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $this->actingAs($user)
        ->get(route('quotes.edit', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Edit')
            ->where('linkedJobcard.id', $jobcard->id));

    $quote->refresh();
    expect($quote->converted_jobcard_id)->toBe($jobcard->id)
        ->and($jobcard->fresh()->source_type)->toBe('quote');
});

it('links quote to jobcard when jobcard is stored from quote conversion', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
        'jobcards' => ['view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    coverageSeedChartOfAccount($company);

    $quote = app(QuoteUpsertService::class)->createForCompany([
        'customer_id' => $customer->id,
        'title' => 'Convert via store',
        'status' => 'draft',
        'line_items' => [
            [
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
            ],
        ],
    ], $company->id, $user->id);

    $this->actingAs($user)->post(route('jobcards.store'), [
        'customer_id' => $customer->id,
        'source_type' => 'quote',
        'source_id' => $quote->id,
        'title' => 'Converted jobcard',
        'status' => 'new',
        'line_groups' => [
            ['name' => 'Items'],
        ],
        'line_items' => [
            [
                'product_id' => null,
                'description' => 'Line item',
                'quantity' => 1,
                'unit_price' => 100,
                'line_group_id' => 1,
            ],
        ],
    ])->assertRedirect();

    $quote->refresh();

    expect($quote->converted_jobcard_id)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('quotes.edit', $quote))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('quotes/Edit')
            ->where('linkedJobcard.id', $quote->converted_jobcard_id));
});
