<?php

use App\Models\Category;
use App\Models\Group;
use App\Models\User;

function createAdminUserForDashboardQuickCreate(): User
{
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Dashboard Quick Create Admin '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    return $user;
}

test('administrators can quick create categories from dashboard endpoint', function () {
    $admin = createAdminUserForDashboardQuickCreate();

    $this->actingAs($admin)
        ->postJson('/administration/categories/quick-create', [
            'name' => 'Dashboard Category',
            'color' => '#3B82F6',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Dashboard Category');

    expect(Category::query()->where('name', 'Dashboard Category')->exists())->toBeTrue();
});

test('non-administrators cannot quick create categories from dashboard endpoint', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/administration/categories/quick-create', [
            'name' => 'Blocked Category',
            'color' => '#3B82F6',
        ])
        ->assertForbidden();
});

test('users with product create permission can quick create products via json store', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'products' => ['list', 'view', 'create', 'edit'],
    ], [
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson('/products', [
            'name' => 'Dashboard Product',
            'type' => 'product',
            'price' => 99.5,
            'unit' => 'piece',
            'track_stock' => true,
            'stock_quantity' => 0,
            'min_stock_level' => 0,
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('name', 'Dashboard Product');
});
