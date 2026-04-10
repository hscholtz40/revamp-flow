<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createUserWithModuleFlags(string $module, array $flags): User
{
    $company = Company::create([
        'name' => 'Helper Route Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create(['name' => 'Helper Route Group '.uniqid()]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => $module,
        'can_view' => $flags['view'] ?? false,
        'can_list' => $flags['list'] ?? false,
        'can_create' => $flags['create'] ?? false,
        'can_edit' => $flags['edit'] ?? false,
        'can_delete' => $flags['delete'] ?? false,
    ]);

    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    return $user;
}

test('customer search requires list permission', function () {
    $user = createUserWithModuleFlags('customers', [
        'view' => true,
        'list' => false,
    ]);

    $this->actingAs($user)
        ->get(route('customers.search', ['q' => 'Acme']))
        ->assertForbidden();
});

test('customer quick create requires create permission', function () {
    $user = createUserWithModuleFlags('customers', [
        'view' => true,
        'list' => true,
        'create' => false,
    ]);

    $this->actingAs($user)
        ->postJson(route('customers.quickCreate'), [
            'name' => 'Blocked Customer',
            'email' => 'blocked-customer@example.com',
        ])
        ->assertForbidden();
});

test('contact search requires list permission', function () {
    $user = createUserWithModuleFlags('contacts', [
        'view' => true,
        'list' => false,
    ]);

    $this->actingAs($user)
        ->get(route('contacts.search'))
        ->assertForbidden();
});

test('contact quick create requires create permission', function () {
    $user = createUserWithModuleFlags('contacts', [
        'view' => true,
        'list' => true,
        'create' => false,
    ]);

    $companyId = (int) $user->current_company_id;
    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $companyId,
        'name' => 'Helper Route Customer',
        'email' => 'helper-route-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('contacts.quick-create'), [
            'customer_id' => $customerId,
            'name' => 'Blocked Contact',
        ])
        ->assertForbidden();
});
