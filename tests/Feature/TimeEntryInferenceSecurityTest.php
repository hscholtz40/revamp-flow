<?php

use App\Models\Company;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\DB;

function teCreateCompany(string $name): Company
{
    return Company::create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function teGrantTimesheetView(User $user): void
{
    $group = Group::create(['name' => 'Timesheet View '.uniqid()]);
    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'timesheet',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => false,
    ]);
    $user->groups()->attach($group->id);
}

test('limited user timesheet summary and list are scoped to own entries', function () {
    $company = teCreateCompany('Timesheet Scope Company');

    $limitedUser = User::factory()->create([
        'current_company_id' => $company->id,
        'user_type' => 'limited',
    ]);
    $otherUser = User::factory()->create([
        'current_company_id' => $company->id,
        'user_type' => 'standard',
    ]);

    $limitedUser->companies()->attach($company->id);
    $otherUser->companies()->attach($company->id);

    teGrantTimesheetView($limitedUser);
    teGrantTimesheetView($otherUser);

    $customerId = (int) DB::table('customers')->insertGetId([
        'company_id' => $company->id,
        'name' => 'Timesheet Customer',
        'email' => 'timesheet-customer@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $jobcardId = (int) DB::table('jobcards')->insertGetId([
        'company_id' => $company->id,
        'customer_id' => $customerId,
        'job_number' => 'JC-TS-0001',
        'title' => 'Timesheet Jobcard',
        'status' => 'draft',
        'subtotal' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('time_entries')->insert([
        [
            'company_id' => $company->id,
            'jobcard_id' => $jobcardId,
            'user_id' => $limitedUser->id,
            'date' => now()->toDateString(),
            'duration_minutes' => 60,
            'hourly_rate' => 100,
            'is_billable' => true,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'company_id' => $company->id,
            'jobcard_id' => $jobcardId,
            'user_id' => $otherUser->id,
            'date' => now()->toDateString(),
            'duration_minutes' => 180,
            'hourly_rate' => 200,
            'is_billable' => true,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $response = $this->actingAs($limitedUser)->get(route('time-entries.index', [
        'user_id' => $otherUser->id, // attempt to force broader aggregate
    ]));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('summary.total_hours', 1.0)
        ->where('summary.billable_hours', 1.0)
        ->where('summary.total_amount', 100.0)
        ->where('filters.user_id', $limitedUser->id)
        ->has('timeEntries.data', 1)
        ->where('timeEntries.data.0.user_id', $limitedUser->id)
    );
});
