<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

function dispatchTestSetup(): array
{
    $company = Company::create([
        'name' => 'Dispatch Test Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);
    $user->companies()->attach($company->id);

    $group = Group::create(['name' => 'Dispatch Test Group '.uniqid()]);
    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'dispatch',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => false,
    ]);
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
        'name' => 'Dispatch Customer',
        'email' => 'dispatch-customer-'.uniqid().'@example.com',
    ]);

    $tech = User::factory()->create();
    $tech->companies()->attach($company->id);

    $day = '2026-06-15';
    $scheduled = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DISP-SCH-'.uniqid(),
        'title' => 'Scheduled dispatch job',
        'status' => 'scheduled',
        'priority' => 'normal',
        'subtotal' => 0,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'assigned_to_user_id' => $tech->id,
        'scheduled_start_at' => $day.' 09:00:00',
        'scheduled_end_at' => $day.' 10:00:00',
    ]);

    $unscheduled = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-DISP-UNS-'.uniqid(),
        'title' => 'Unscheduled dispatch job',
        'status' => 'needs_scheduling',
        'priority' => 'normal',
        'subtotal' => 0,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ]);

    return [$company, $user, $scheduled, $unscheduled, $tech, $day];
}

test('dispatch board-data returns scheduled and unscheduled jobcards for a date', function () {
    [, $user, $scheduled, $unscheduled, , $day] = dispatchTestSetup();
    $this->actingAs($user);

    $response = $this->getJson('/dispatch/board-data?date='.$day.'&tab=unscheduled');
    $response->assertOk();
    $response->assertJsonPath('date', $day);
    $ids = collect($response->json('scheduled_jobcards'))->pluck('id')->all();
    expect($ids)->toContain($scheduled->id);
    $queueIds = collect($response->json('unscheduled_jobcards'))->pluck('id')->all();
    expect($queueIds)->toContain($unscheduled->id);
});

test('dispatch jobcard assign clears schedule via web route', function () {
    Notification::fake();
    [, $user, $scheduled] = dispatchTestSetup();
    $this->actingAs($user);

    $response = $this->patchJson(route('dispatch.jobcards.assign', $scheduled), [
        'scheduled_start_at' => null,
        'scheduled_end_at' => null,
    ]);
    $response->assertOk();
    $scheduled->refresh();
    expect($scheduled->scheduled_start_at)->toBeNull();
    expect($scheduled->scheduled_end_at)->toBeNull();
    expect($scheduled->status)->toBe('needs_scheduling');
});

test('guest cannot access dispatch board-data', function () {
    $this->getJson('/dispatch/board-data?date=2026-06-15')->assertUnauthorized();
});
