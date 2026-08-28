<?php

use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;

function createDashboardQuickActionsAdmin(): User
{
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);

    $group = Group::query()->create([
        'name' => 'Dashboard Quick Actions Admin '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    foreach (['users', 'quotes', 'invoices', 'jobcards', 'customers', 'suppliers', 'products'] as $module) {
        GroupPermission::query()->updateOrCreate(
            ['group_id' => $group->id, 'module' => $module],
            [
                'can_list' => true,
                'can_view' => true,
                'can_create' => true,
                'can_edit' => true,
                'can_delete' => true,
                'can_edit_completed' => false,
                'can_edit_salesperson' => false,
                'can_approve' => false,
            ]
        );
    }

    return $user;
}

test('shared auth user includes resolved dashboard quick actions', function () {
    $user = createDashboardQuickActionsAdmin();
    $user->dashboard_quick_actions = ['quote' => false];
    $user->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.dashboard_quick_actions.quote', false)
            ->where('auth.user.dashboard_quick_actions.invoice', true)
        );
});

test('administrators can update another users dashboard quick actions', function () {
    $admin = createDashboardQuickActionsAdmin();
    $target = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $admin->current_company_id,
    ]);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'user_type' => 'standard',
            'hourly_rate' => null,
            'groups' => [],
            'companies' => [],
            'dashboard_quick_actions' => [
                'quote' => false,
                'invoice' => true,
                'pos' => true,
                'jobcard' => true,
                'customer' => true,
                'supplier' => true,
                'category' => true,
                'product' => true,
            ],
        ])
        ->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->getResolvedDashboardQuickActions()['quote'])->toBeFalse()
        ->and($target->getResolvedDashboardQuickActions()['invoice'])->toBeTrue();
});

test('users can update their own dashboard quick actions from profile settings', function () {
    $user = createDashboardQuickActionsAdmin();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'dashboard_quick_actions' => [
                'pos' => false,
                'product' => false,
                'quote' => true,
                'invoice' => true,
                'jobcard' => true,
                'customer' => true,
                'supplier' => true,
                'category' => true,
            ],
        ])
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->isDashboardQuickActionEnabled('pos'))->toBeFalse()
        ->and($user->isDashboardQuickActionEnabled('product'))->toBeFalse()
        ->and($user->isDashboardQuickActionEnabled('invoice'))->toBeTrue();
});
