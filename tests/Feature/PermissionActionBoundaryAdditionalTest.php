<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;

function pabaCreateViewOnlyUser(array $modules): User
{
    $user = User::factory()->create();
    $group = Group::create(['name' => 'View Only Additional '.uniqid()]);

    foreach ($modules as $module) {
        GroupPermission::create([
            'group_id' => $group->id,
            'module' => $module,
            'can_view' => true,
            'can_list' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
        ]);
    }

    $user->groups()->attach($group->id);

    return $user;
}

test('view-only user is blocked from customer update action', function () {
    $user = pabaCreateViewOnlyUser(['customers']);

    $this->actingAs($user)
        ->put(route('customers.update', 999999), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from contact update action', function () {
    $user = pabaCreateViewOnlyUser(['contacts']);

    $this->actingAs($user)
        ->put(route('contacts.update', 999999), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from product update action', function () {
    $user = pabaCreateViewOnlyUser(['products']);

    $this->actingAs($user)
        ->put(route('products.update', 999999), ['name' => 'Blocked'])
        ->assertForbidden();
});

test('view-only user is blocked from stock movement create action', function () {
    $user = pabaCreateViewOnlyUser(['stock-movements']);

    $this->actingAs($user)
        ->post(route('stock-movements.store'), [])
        ->assertForbidden();
});

test('view-only user is blocked from credit note status update action', function () {
    $user = pabaCreateViewOnlyUser(['credit-notes']);

    $this->actingAs($user)
        ->patch(route('credit-notes.update-status', 999999), ['status' => 'approved'])
        ->assertForbidden();
});

test('view-only user is blocked from report update action', function () {
    $user = pabaCreateViewOnlyUser(['reports']);

    $this->actingAs($user)
        ->put(route('reports.update', 999999), ['name' => 'Blocked'])
        ->assertForbidden();
});
