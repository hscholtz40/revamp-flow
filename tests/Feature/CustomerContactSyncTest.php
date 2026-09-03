<?php

use App\Models\Contact;
use App\Services\CustomerUpsertService;

test('creating customer with contact details creates a primary contact', function () {
    $company = coverageCreateCompany();
    $service = app(CustomerUpsertService::class);

    $customer = $service->createForCompany([
        'name' => 'Acme Widgets',
        'email' => 'info@acme.example',
        'contact_first_name' => 'Jane',
        'contact_last_name' => 'Doe',
        'contact_cell' => '0821234567',
        'contact_email' => 'jane@acme.example',
    ], $company->id);

    $contact = Contact::where('customer_id', $customer->id)->first();

    expect($contact)->not->toBeNull()
        ->and($contact->company_id)->toBe($company->id)
        ->and($contact->name)->toBe('Jane Doe')
        ->and($contact->email)->toBe('jane@acme.example')
        ->and($contact->phone)->toBe('0821234567')
        ->and($contact->position)->toBe('Contact person')
        ->and($contact->is_primary)->toBeTrue();
});

test('creating customer without contact details does not create a contact', function () {
    $company = coverageCreateCompany();
    $service = app(CustomerUpsertService::class);

    $customer = $service->createForCompany([
        'name' => 'No Contact Co',
        'email' => 'info@nocontact.example',
    ], $company->id);

    expect(Contact::where('customer_id', $customer->id)->count())->toBe(0);
});

test('quick create customer with contact details creates a primary contact', function () {
    $company = coverageCreateCompany();
    $service = app(CustomerUpsertService::class);

    $customer = $service->quickCreateForCompany([
        'name' => 'Quick Add Co',
        'email' => 'info@quickadd.example',
        'contact_first_name' => 'Sam',
        'contact_last_name' => 'Lee',
        'contact_cell' => '0837654321',
        'contact_email' => 'sam@quickadd.example',
    ], $company->id);

    $contact = Contact::where('customer_id', $customer->id)->first();

    expect($contact)->not->toBeNull()
        ->and($contact->name)->toBe('Sam Lee')
        ->and($contact->email)->toBe('sam@quickadd.example')
        ->and($contact->is_primary)->toBeTrue();
});

test('updating customer contact details syncs the primary contact', function () {
    $company = coverageCreateCompany();
    $service = app(CustomerUpsertService::class);

    $customer = $service->createForCompany([
        'name' => 'Sync Update Co',
        'email' => 'info@syncupdate.example',
        'contact_first_name' => 'Alex',
        'contact_last_name' => 'Brown',
        'contact_email' => 'alex@syncupdate.example',
    ], $company->id);

    $contact = Contact::where('customer_id', $customer->id)->firstOrFail();

    $updatedCustomer = $service->update($customer, [
        'name' => 'Sync Update Co',
        'email' => 'info@syncupdate.example',
        'contact_first_name' => 'Alexandra',
        'contact_last_name' => 'Brown',
        'contact_cell' => '0841112222',
        'contact_email' => 'alexandra@syncupdate.example',
    ]);

    $contact->refresh();

    expect($updatedCustomer->contact_first_name)->toBe('Alexandra')
        ->and($contact->id)->toBe($contact->id)
        ->and($contact->name)->toBe('Alexandra Brown')
        ->and($contact->email)->toBe('alexandra@syncupdate.example')
        ->and($contact->phone)->toBe('0841112222')
        ->and($contact->is_primary)->toBeTrue();
});
