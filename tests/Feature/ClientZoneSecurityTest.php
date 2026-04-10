<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createClientZoneCompany(string $name, array $attributes = []): Company
{
    return Company::create(array_merge([
        'name' => $name,
        'email' => strtolower(str_replace(' ', '-', $name)).'@example.com',
        'is_active' => true,
        'is_default' => false,
        'enable_document_signing' => false,
    ], $attributes));
}

function createClientZoneCustomer(Company $company, string $email, array $attributes = []): Customer
{
    return Customer::create(array_merge([
        'company_id' => $company->id,
        'name' => $company->name.' Customer',
        'email' => $email,
    ], $attributes));
}

function createApprovedClientUser(Customer $customer, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'name' => $customer->name.' Portal',
        'email' => $customer->email,
        'user_type' => 'client',
        'approval_status' => 'approved',
        'customer_id' => $customer->id,
        'current_company_id' => $customer->company_id,
    ], $attributes));
}

function createClientZoneInvoice(Company $company, Customer $customer, array $attributes = []): Invoice
{
    return Invoice::create(array_merge([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
        'title' => 'Security Test Invoice',
        'status' => 'draft',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(14)->toDateString(),
        'subtotal' => 100,
        'tax_rate' => 15,
        'tax_amount' => 15,
        'total' => 115,
    ], $attributes));
}

test('client users cannot change their sign-in email from profile settings', function () {
    $company = createClientZoneCompany('Profile Lock Company');
    $customer = createClientZoneCustomer($company, 'client-profile@example.com');
    $client = createApprovedClientUser($customer);

    $response = $this
        ->actingAs($client)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), [
            'name' => 'Updated Client Name',
            'email' => 'different-client@example.com',
        ]);

    $response
        ->assertSessionHasErrors('email')
        ->assertRedirect(route('profile.edit'));

    $client->refresh();

    expect($client->name)->toBe($customer->name.' Portal');
    expect($client->email)->toBe('client-profile@example.com');
});

test('client zone document access stays bound to the linked customer record', function () {
    $company = createClientZoneCompany('Document Scope Company');
    $customer = createClientZoneCustomer($company, 'shared-portal@example.com', [
        'name' => 'Primary Customer',
    ]);
    $otherCustomer = createClientZoneCustomer($company, 'shared-portal@example.com', [
        'name' => 'Secondary Customer',
    ]);
    $client = createApprovedClientUser($customer);
    $foreignInvoice = createClientZoneInvoice($company, $otherCustomer, [
        'invoice_number' => 'INV-SCOPE-0001',
    ]);

    $this->actingAs($client)
        ->get(route('client-zone.invoices.show', $foreignInvoice->id))
        ->assertForbidden();
});

test('client users only receive their linked company in shared props', function () {
    $primaryCompany = createClientZoneCompany('Primary Client Company', ['is_default' => true]);
    $otherCompany = createClientZoneCompany('Other Client Company');
    $customer = createClientZoneCustomer($primaryCompany, 'client-shared-props@example.com');
    $client = createApprovedClientUser($customer);

    $response = $this->actingAs($client)->get(route('client-zone.dashboard'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('auth.user.user_type', 'client')
        ->where('currentCompany.id', $primaryCompany->id)
        ->has('companies', 1)
        ->where('companies.0.id', $primaryCompany->id)
        ->where('companies.0.name', $primaryCompany->name)
    );

    expect($otherCompany->id)->not->toBe($primaryCompany->id);
});

test('non-client users are redirected away from client zone routes', function () {
    $company = createClientZoneCompany('Staff Company');
    $staffUser = User::factory()->create([
        'current_company_id' => $company->id,
        'email_verified_at' => now(),
    ]);
    $staffUser->companies()->attach($company->id);

    $this->actingAs($staffUser)
        ->get(route('client-zone.dashboard'))
        ->assertRedirect(route('dashboard'));
});

test('client signing is rejected when company document signing is disabled', function () {
    Storage::fake('public');

    $company = createClientZoneCompany('Signing Disabled Company', [
        'enable_document_signing' => false,
    ]);
    $customer = createClientZoneCustomer($company, 'signing-disabled@example.com');
    $client = createApprovedClientUser($customer);
    $invoice = createClientZoneInvoice($company, $customer, [
        'invoice_number' => 'INV-SIGN-0001',
    ]);

    $this->actingAs($client)
        ->post(route('client-zone.invoices.sign', $invoice->id), [
            'signer_name' => 'Portal Client',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5WZNoAAAAASUVORK5CYII=',
        ])
        ->assertForbidden();

    $this->assertDatabaseCount('document_signatures', 0);
});

test('client registration returns the same error for missing and existing accounts', function () {
    $company = createClientZoneCompany('Registration Company');
    $customer = createClientZoneCustomer($company, 'known-client@example.com');
    createApprovedClientUser($customer);

    $unknownResponse = $this
        ->from(route('client-zone.register'))
        ->post(route('client-zone.register.store'), [
            'name' => 'Unknown Client',
            'email' => 'missing-client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $knownResponse = $this
        ->from(route('client-zone.register'))
        ->post(route('client-zone.register.store'), [
            'name' => 'Known Client',
            'email' => 'known-client@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $unknownResponse
        ->assertSessionHasErrors([
            'email' => 'We could not complete registration with those details. Please contact the company if you need access.',
        ])
        ->assertRedirect(route('client-zone.register'));

    $knownResponse
        ->assertSessionHasErrors([
            'email' => 'We could not complete registration with those details. Please contact the company if you need access.',
        ])
        ->assertRedirect(route('client-zone.register'));
});
