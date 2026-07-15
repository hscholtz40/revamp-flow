<?php

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
