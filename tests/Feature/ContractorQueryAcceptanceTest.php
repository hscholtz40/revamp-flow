<?php

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Query;

test('accepting contractor query creates customer and contact', function () {
    config(['app.is_licensing_instance' => true]);

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'queries' => ['list', 'view', 'edit'],
    ]);

    $query = Query::create([
        'company_id' => $company->id,
        'kind' => Query::KIND_CONTRACTOR,
        'name' => 'John',
        'surname' => 'Smith',
        'email' => 'john.smith@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding submission',
        'company_name' => 'Smith Electrical CC',
        'company_registration_no' => '2020/123456/07',
        'company_address' => '1 Main Street',
        'company_email' => 'admin@smith-electric.example',
        'company_contact_number' => '0210000000',
        'company_website' => 'https://smith-electric.example',
        'status' => Query::STATUS_OPEN,
        'response' => Query::RESPONSE_PENDING,
    ]);

    $this->actingAs($user)
        ->post(route('queries.accept-contractor', $query))
        ->assertRedirect();

    $query->refresh();
    expect($query->response)->toBe(Query::RESPONSE_ACCEPTED)
        ->and($query->status)->toBe(Query::STATUS_CLOSED)
        ->and($query->accepted_customer_id)->not->toBeNull()
        ->and($query->accepted_contact_id)->not->toBeNull();

    $customer = Customer::findOrFail($query->accepted_customer_id);
    $contact = Contact::findOrFail($query->accepted_contact_id);

    expect($customer->company_id)->toBe($company->id)
        ->and($customer->name)->toBe('Smith Electrical CC')
        ->and($contact->customer_id)->toBe($customer->id)
        ->and($contact->name)->toBe('John Smith');
});
