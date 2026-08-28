<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\User;
use App\Support\JobcardStatuses;

test('jobcard fulfillment statuses are accepted when updating jobcard status', function () {
    $company = Company::create(['name' => 'Fulfillment Status Co', 'is_active' => true]);
    $user = User::factory()->create(['current_company_id' => $company->id]);
    $user->companies()->attach($company->id);

    $group = Group::create(['name' => 'Jobcard Editors '.uniqid()]);
    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'jobcards',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => false,
    ]);
    $user->groups()->attach($group->id);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Fulfillment Customer',
        'email' => 'fulfillment@example.com',
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-FULFILL-001',
        'title' => 'Fulfillment workflow job',
        'status' => 'dispatched',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ]);

    foreach ([
        'purchase_order_sent_on_dispatch',
        'order_received',
        'stock_checked',
        'delivery_scheduled',
        'delivered',
    ] as $status) {
        expect(in_array($status, JobcardStatuses::ALL, true))->toBeTrue();

        $this->actingAs($user)
            ->patch(route('jobcards.update-status', $jobcard), ['status' => $status])
            ->assertRedirect();

        expect($jobcard->fresh()->status)->toBe($status);
    }
});
