<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\User;
use App\Notifications\AssignmentNotification;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function createJobcardEditorForNotificationTests(): array
{
    $company = Company::create([
        'name' => 'Jobcard Notification Co '.uniqid(),
        'is_active' => true,
    ]);

    $editor = User::factory()->create([
        'current_company_id' => $company->id,
    ]);
    $assignee = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create([
        'name' => 'Jobcard Notification Permissions '.uniqid(),
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

    $editor->groups()->attach($group->id);
    $editor->companies()->attach($company->id);
    $assignee->companies()->attach($company->id);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Notification Customer',
        'email' => 'notification-customer@example.com',
        'account_code' => Customer::generateAccountCode('Notification Customer', $company->id),
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-NOTIFY-'.uniqid(),
        'title' => 'Notification Test Jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    return [$company, $editor, $assignee, $customer, $jobcard];
}

test('web jobcard update sends assignment notification to assigned user', function () {
    Notification::fake();

    [$company, $editor, $assignee, $customer, $jobcard] = createJobcardEditorForNotificationTests();

    $this->actingAs($editor)->put(route('jobcards.update', $jobcard), [
        'customer_id' => $customer->id,
        'title' => $jobcard->title,
        'status' => 'new',
        'assigned_to_user_id' => $assignee->id,
        'line_groups' => [['name' => 'Items']],
        'line_items' => [[
            'description' => 'Work item',
            'quantity' => 1,
            'unit_price' => 100,
        ]],
    ])->assertRedirect();

    Notification::assertSentTo($assignee, AssignmentNotification::class);
});

test('api jobcard update sends assignment notification to assigned user', function () {
    Notification::fake();

    [$company, $editor, $assignee, $customer, $jobcard] = createJobcardEditorForNotificationTests();

    Sanctum::actingAs($editor, ['*']);

    $response = $this->patchJson("/api/v1/jobcards/{$jobcard->id}", [
        'assigned_to_user_id' => $assignee->id,
    ]);
    $response->assertOk();

    Notification::assertSentTo($assignee, AssignmentNotification::class);
});

test('api jobcard create sends assignment notification when assignee is set', function () {
    Notification::fake();

    [$company, $editor, $assignee, $customer] = createJobcardEditorForNotificationTests();

    Sanctum::actingAs($editor, ['*']);

    $this->postJson('/api/v1/jobcards', [
        'customer_id' => $customer->id,
        'title' => 'API Assigned Jobcard',
        'assigned_to_user_id' => $assignee->id,
    ])->assertCreated();

    Notification::assertSentTo($assignee, AssignmentNotification::class);
});
