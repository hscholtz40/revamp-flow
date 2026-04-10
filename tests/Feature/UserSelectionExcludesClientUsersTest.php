<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function createInvoicePermissionsUserForUserSelectionTests(array $flags = []): array
{
    $company = Company::create([
        'name' => 'User Selection Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create([
        'name' => 'Invoice Permissions '.uniqid(),
    ]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'invoices',
        'can_view' => $flags['view'] ?? true,
        'can_list' => $flags['list'] ?? true,
        'can_create' => $flags['create'] ?? true,
        'can_edit' => $flags['edit'] ?? true,
        'can_delete' => $flags['delete'] ?? true,
    ]);

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return [$company, $user];
}

test('invoice create excludes client users from salesperson options', function () {
    [$company, $user] = createInvoicePermissionsUserForUserSelectionTests();

    $staffUser = User::factory()->create([
        'current_company_id' => $company->id,
        'user_type' => 'standard',
    ]);
    $staffUser->companies()->attach($company->id);

    $clientUser = User::factory()->create([
        'current_company_id' => $company->id,
        'user_type' => 'client',
    ]);
    $clientUser->companies()->attach($company->id);

    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('invoices/Create')
            ->where('users', function ($users) use ($staffUser, $clientUser) {
                $userIds = collect($users)->pluck('id');

                return $userIds->contains($staffUser->id)
                    && ! $userIds->contains($clientUser->id);
            })
        );
});

test('invoice store rejects client salesperson users', function () {
    [$company, $user] = createInvoicePermissionsUserForUserSelectionTests();

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Selection Test Customer',
        'email' => 'selection-test-customer@example.com',
        'terms' => 'COD',
    ]);

    Product::create([
        'company_id' => $company->id,
        'name' => 'Selection Test Product',
        'price' => 100,
        'stock_quantity' => 10,
        'track_stock' => false,
        'is_active' => true,
    ]);

    $clientUser = User::factory()->create([
        'current_company_id' => $company->id,
        'user_type' => 'client',
    ]);
    $clientUser->companies()->attach($company->id);

    $invoiceDate = now()->toDateString();

    $this->actingAs($user)
        ->from(route('invoices.create'))
        ->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'salesperson_id' => $clientUser->id,
            'invoice_date' => $invoiceDate,
            'tax_rate' => 0,
            'line_items' => [[
                'product_id' => null,
                'description' => 'Validation line item',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'discount_percentage' => 0,
            ]],
        ])
        ->assertRedirect(route('invoices.create'))
        ->assertSessionHasErrors('salesperson_id');

    expect(Invoice::count())->toBe(0);
});
