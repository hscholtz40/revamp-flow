<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;

function createViewOnlyUserForModules(array $modules): User
{
    $user = User::factory()->create();
    $group = Group::create(['name' => 'View Only '.uniqid()]);

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

test('view-only user is blocked from quote convert action', function () {
    $user = createViewOnlyUserForModules(['quotes']);

    $this->actingAs($user)
        ->post(route('quotes.convert-to-invoice', 999999))
        ->assertForbidden();
});

test('view-only user is blocked from invoice email action', function () {
    $user = createViewOnlyUserForModules(['invoices']);

    $this->actingAs($user)
        ->post(route('invoices.email', 999999))
        ->assertForbidden();
});

test('view-only user is blocked from jobcard status update action', function () {
    $user = createViewOnlyUserForModules(['jobcards']);

    $this->actingAs($user)
        ->patch(route('jobcards.update-status', 999999), ['status' => 'completed'])
        ->assertForbidden();
});

test('view-only user is blocked from purchase order receive items action', function () {
    $user = createViewOnlyUserForModules(['purchase-orders']);

    $this->actingAs($user)
        ->post(route('purchase-orders.receive-items', 999999), ['items' => []])
        ->assertForbidden();
});
