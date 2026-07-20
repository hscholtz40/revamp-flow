<?php

use App\Models\DeliveryNote;
use App\Models\Jobcard;

it('creates a delivery note from a jobcard with prefilled line items', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'delivery-notes' => ['list', 'view', 'create', 'edit', 'delete'],
        'jobcards' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);
    $product = coverageSeedProduct($company);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DN-'.uniqid(),
        'title' => 'Delivery note source jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $group = $jobcard->lineGroups()->create(['name' => 'Items', 'sort_order' => 0]);
    $jobcard->lineItems()->create([
        'line_group_id' => $group->id,
        'product_id' => $product->id,
        'description' => 'Widget delivery item',
        'quantity' => 2,
        'unit_price' => 50,
        'total' => 100,
        'sort_order' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('delivery-notes.store'), [
        'jobcard_id' => $jobcard->id,
        'delivery_date' => now()->toDateString(),
        'delivery_address' => '123 Delivery Street',
        'notes' => 'Handle with care',
        'line_groups' => [
            ['id' => 1, 'name' => 'Items'],
        ],
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'description' => 'Widget delivery item',
                'line_group_id' => 1,
            ],
        ],
    ]);

    $deliveryNote = DeliveryNote::query()->where('jobcard_id', $jobcard->id)->latest('id')->firstOrFail();

    $response->assertRedirect(route('delivery-notes.show', $deliveryNote));

    expect($deliveryNote->status)->toBe('draft')
        ->and($deliveryNote->delivery_address)->toBe('123 Delivery Street')
        ->and($deliveryNote->lineItems)->toHaveCount(1)
        ->and($deliveryNote->lineItems->first()->description)->toBe('Widget delivery item');
});

it('redirects jobcard convert action to delivery note create with jobcard prefill', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'delivery-notes' => ['list', 'view', 'create', 'edit', 'delete'],
        'jobcards' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DN-CONVERT-'.uniqid(),
        'title' => 'Convert to delivery note',
        'status' => 'new',
        'subtotal' => 50,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 50,
    ]);

    $this->actingAs($user)
        ->post(route('jobcards.convert-to-delivery-note', $jobcard))
        ->assertRedirect(route('delivery-notes.create', ['jobcard_id' => $jobcard->id]));
});

it('shows related delivery notes on the jobcard detail page', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'delivery-notes' => ['list', 'view', 'create', 'edit', 'delete'],
        'jobcards' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    $customer = coverageSeedCustomer($company);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DN-SHOW-'.uniqid(),
        'title' => 'Jobcard with delivery note',
        'status' => 'new',
        'subtotal' => 50,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 50,
    ]);

    $deliveryNote = DeliveryNote::create([
        'company_id' => $company->id,
        'jobcard_id' => $jobcard->id,
        'customer_id' => $customer->id,
        'user_id' => $user->id,
        'delivery_note_number' => 'DN-TEST-0001',
        'delivery_date' => now()->toDateString(),
        'status' => 'draft',
    ]);

    $this->actingAs($user)
        ->get(route('jobcards.show', $jobcard))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('jobcards/Show')
            ->has('relatedDeliveryNotes', 1)
            ->where('relatedDeliveryNotes.0.id', $deliveryNote->id)
            ->where('relatedDeliveryNotes.0.delivery_note_number', 'DN-TEST-0001')
        );
});
