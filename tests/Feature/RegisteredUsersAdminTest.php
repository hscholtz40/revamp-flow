<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerUpdateRequest;
use App\Models\User;

function createRegisteredUsersApprover(): User
{
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $group = Group::query()->create([
        'name' => 'Approver '.uniqid(),
        'description' => '',
        'is_administrator' => false,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    GroupPermission::query()->updateOrCreate(
        ['group_id' => $group->id, 'module' => 'customer-update-requests'],
        [
            'can_list' => true,
            'can_view' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_edit_completed' => false,
            'can_edit_salesperson' => false,
            'can_approve' => true,
        ]
    );

    return $user;
}

test('user without client zone list permissions cannot access registered users hub', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('registered-users.index'))->assertForbidden();
});

test('administrator group can access registered users hub', function () {
    $user = User::factory()->create();
    $group = Group::query()->create([
        'name' => 'Admin Test '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    GroupPermission::query()->updateOrCreate(
        ['group_id' => $group->id, 'module' => 'registered-users'],
        [
            'can_list' => true,
            'can_view' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_edit_completed' => false,
            'can_edit_salesperson' => false,
            'can_approve' => true,
        ]
    );
    GroupPermission::query()->updateOrCreate(
        ['group_id' => $group->id, 'module' => 'customer-update-requests'],
        [
            'can_list' => true,
            'can_view' => true,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_edit_completed' => false,
            'can_edit_salesperson' => false,
            'can_approve' => true,
        ]
    );

    $this->actingAs($user);

    $this->get(route('registered-users.index'))->assertOk();
});

test('non-admin user with registered-users list permission can access hub', function () {
    $user = User::factory()->create();
    $group = Group::query()->create([
        'name' => 'Moderators '.uniqid(),
        'description' => '',
        'is_administrator' => false,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    GroupPermission::query()->create([
        'group_id' => $group->id,
        'module' => 'registered-users',
        'can_list' => true,
        'can_view' => false,
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false,
        'can_edit_completed' => false,
        'can_edit_salesperson' => false,
        'can_approve' => false,
    ]);

    $this->actingAs($user);

    $this->get(route('registered-users.index'))->assertOk();
});

test('approving an update request syncs the linked client user email', function () {
    $approver = createRegisteredUsersApprover();
    $company = Company::query()->create([
        'name' => 'Client Update Company',
        'is_active' => true,
    ]);
    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Portal Customer',
        'email' => 'before@example.com',
    ]);
    $clientUser = User::factory()->create([
        'email' => 'before@example.com',
        'email_verified_at' => now(),
        'user_type' => 'client',
        'approval_status' => 'approved',
        'customer_id' => $customer->id,
        'current_company_id' => $company->id,
    ]);
    $updateRequest = CustomerUpdateRequest::query()->create([
        'customer_id' => $customer->id,
        'user_id' => $clientUser->id,
        'requested_changes' => [
            'name' => 'Portal Customer Updated',
            'email' => 'after@example.com',
        ],
        'status' => 'pending',
    ]);

    $this->actingAs($approver)
        ->post(route('registered-users.update-requests.approve', $updateRequest))
        ->assertRedirect(route('registered-users.update-requests.show', $updateRequest));

    expect($customer->fresh()->email)->toBe('after@example.com');
    expect($clientUser->fresh()->email)->toBe('after@example.com');
    expect($clientUser->fresh()->email_verified_at)->toBeNull();
    expect($updateRequest->fresh()->status)->toBe('approved');
});

test('approving an update request fails cleanly when the new email is already used by another user', function () {
    $approver = createRegisteredUsersApprover();
    $company = Company::query()->create([
        'name' => 'Client Update Conflict Company',
        'is_active' => true,
    ]);
    $customer = Customer::query()->create([
        'company_id' => $company->id,
        'name' => 'Portal Customer',
        'email' => 'before-conflict@example.com',
    ]);
    $clientUser = User::factory()->create([
        'email' => 'before-conflict@example.com',
        'email_verified_at' => now(),
        'user_type' => 'client',
        'approval_status' => 'approved',
        'customer_id' => $customer->id,
        'current_company_id' => $company->id,
    ]);
    User::factory()->create([
        'email' => 'taken@example.com',
    ]);
    $updateRequest = CustomerUpdateRequest::query()->create([
        'customer_id' => $customer->id,
        'user_id' => $clientUser->id,
        'requested_changes' => [
            'email' => 'taken@example.com',
        ],
        'status' => 'pending',
    ]);

    $this->actingAs($approver)
        ->post(route('registered-users.update-requests.approve', $updateRequest))
        ->assertStatus(422);

    expect($customer->fresh()->email)->toBe('before-conflict@example.com');
    expect($clientUser->fresh()->email)->toBe('before-conflict@example.com');
    expect($updateRequest->fresh()->status)->toBe('pending');
});
