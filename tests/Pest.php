<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function coverageCreateCompany(array $overrides = []): \App\Models\Company
{
    return \App\Models\Company::create(array_merge([
        'name' => 'Coverage Co '.uniqid(),
        'is_active' => true,
    ], $overrides));
}

function coverageCreateUserWithPermissions(
    \App\Models\Company $company,
    array $permissionsByModule,
    array $userOverrides = [],
): \App\Models\User {
    $user = \App\Models\User::factory()->create(array_merge([
        'current_company_id' => $company->id,
    ], $userOverrides));

    $group = \App\Models\Group::create([
        'name' => 'Coverage Group '.uniqid(),
    ]);

    foreach ($permissionsByModule as $module => $abilities) {
        \App\Models\GroupPermission::create([
            'group_id' => $group->id,
            'module' => $module,
            'can_view' => in_array('view', $abilities, true),
            'can_list' => in_array('list', $abilities, true),
            'can_create' => in_array('create', $abilities, true),
            'can_edit' => in_array('edit', $abilities, true),
            'can_delete' => in_array('delete', $abilities, true),
            'can_edit_completed' => in_array('edit_completed', $abilities, true),
            'can_edit_salesperson' => in_array('edit_salesperson', $abilities, true),
            'can_approve' => in_array('approve', $abilities, true),
        ]);
    }

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return $user;
}

function coverageSeedCustomer(\App\Models\Company $company, array $overrides = []): \App\Models\Customer
{
    return \App\Models\Customer::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Coverage Customer '.uniqid(),
        'email' => 'customer-'.uniqid().'@example.com',
    ], $overrides));
}

function coverageSeedSupplier(\App\Models\Company $company, array $overrides = []): \App\Models\Supplier
{
    return \App\Models\Supplier::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Coverage Supplier '.uniqid(),
        'email' => 'supplier-'.uniqid().'@example.com',
    ], $overrides));
}

function coverageSeedProduct(\App\Models\Company $company, array $overrides = []): \App\Models\Product
{
    return \App\Models\Product::create(array_merge([
        'company_id' => $company->id,
        'name' => 'Coverage Product '.uniqid(),
        'sku' => 'SKU-'.uniqid(),
        'price' => 100,
        'cost' => 50,
        'is_active' => true,
        'track_stock' => false,
    ], $overrides));
}

function coverageSeedTaxRate(\App\Models\Company $company, array $overrides = []): \App\Models\TaxRate
{
    return \App\Models\TaxRate::create(array_merge([
        'company_id' => $company->id,
        'name' => 'VAT',
        'rate' => 15,
        'is_active' => true,
    ], $overrides));
}

function coverageSeedChartOfAccount(\App\Models\Company $company, array $overrides = []): \App\Models\ChartOfAccount
{
    return \App\Models\ChartOfAccount::create(array_merge([
        'company_id' => $company->id,
        'account_code' => '4000',
        'account_name' => 'Sales',
        'account_type' => 'Revenue',
        'is_active' => true,
        'is_default_sales' => true,
    ], $overrides));
}
