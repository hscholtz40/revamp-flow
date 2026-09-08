<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\TimeEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function createTimesheetApiUser(): array
{
    $company = Company::create([
        'name' => 'Timesheet Location Co '.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
    ]);

    $group = Group::create([
        'name' => 'Timesheet Location Permissions '.uniqid(),
    ]);

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
    $user->companies()->attach($company->id);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Timesheet Location Customer',
        'email' => 'timesheet-location-'.uniqid().'@example.com',
        'account_code' => Customer::generateAccountCode('Timesheet Location Customer', $company->id),
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-LOC-'.uniqid(),
        'title' => 'Location Timesheet Jobcard',
        'status' => 'new',
        'subtotal' => 0,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ]);

    return [$company, $user, $jobcard];
}

test('api time entry store accepts location from mobile', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    Sanctum::actingAs($user, ['*']);

    $response = $this->postJson('/api/v1/time-entries', [
        'jobcard_id' => $jobcard->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '10:30',
        'description' => 'On-site work',
        'latitude' => -26.2041,
        'longitude' => 28.0473,
        'location_accuracy' => 12.5,
    ]);

    $response->assertCreated()
        ->assertJsonPath('latitude', '-26.2041000')
        ->assertJsonPath('longitude', '28.0473000')
        ->assertJsonPath('location_accuracy', '12.50')
        ->assertJsonPath('has_location', true)
        ->assertJsonPath('formatted_time_range', '08:00 – 10:30');

    $this->assertDatabaseHas('time_entries', [
        'jobcard_id' => $jobcard->id,
        'user_id' => $user->id,
        'latitude' => -26.2041,
        'longitude' => 28.0473,
    ]);
});

test('api time entry store works without location for web-compatible payloads', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/time-entries', [
        'jobcard_id' => $jobcard->id,
        'date' => now()->toDateString(),
        'start_time' => '09:00',
        'end_time' => '10:00',
    ])->assertCreated()
        ->assertJsonPath('latitude', null)
        ->assertJsonPath('longitude', null)
        ->assertJsonPath('has_location', false);
});

test('api start timer accepts location', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/time-entries/start-timer', [
        'jobcard_id' => $jobcard->id,
        'description' => 'Started on site',
        'latitude' => -33.9249,
        'longitude' => 18.4241,
        'location_accuracy' => 8,
    ])->assertCreated()
        ->assertJsonPath('status', 'running')
        ->assertJsonPath('has_location', true);

    $this->assertDatabaseHas('time_entries', [
        'jobcard_id' => $jobcard->id,
        'user_id' => $user->id,
        'status' => 'running',
        'latitude' => -33.9249,
        'longitude' => 18.4241,
    ]);
});

test('api stop timer can update location', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    $entry = TimeEntry::create([
        'company_id' => $jobcard->company_id,
        'jobcard_id' => $jobcard->id,
        'user_id' => $user->id,
        'date' => now()->toDateString(),
        'start_time' => now()->subHour()->format('H:i'),
        'started_at' => now()->subHour(),
        'status' => 'running',
        'duration_minutes' => 0,
    ]);

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/time-entries/stop-timer', [
        'latitude' => -26.1,
        'longitude' => 28.1,
        'location_accuracy' => 5,
    ])->assertOk()
        ->assertJsonPath('id', $entry->id)
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('has_location', true);

    expect($entry->fresh()->latitude)->not->toBeNull();
});

test('api rejects incomplete location coordinates', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/time-entries', [
        'jobcard_id' => $jobcard->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'latitude' => -26.2041,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

test('web time entry store does not require or persist location', function () {
    [, $user, $jobcard] = createTimesheetApiUser();

    $this->actingAs($user)->post(route('time-entries.store'), [
        'jobcard_id' => $jobcard->id,
        'date' => now()->toDateString(),
        'start_time' => '08:00',
        'end_time' => '09:00',
        'description' => 'Web capture',
        'is_billable' => true,
        // Even if a client sends these, web validation should ignore them.
        'latitude' => -26.2041,
        'longitude' => 28.0473,
    ])->assertRedirect();

    $entry = TimeEntry::query()->where('jobcard_id', $jobcard->id)->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->latitude)->toBeNull()
        ->and($entry->longitude)->toBeNull()
        ->and($entry->start_time?->format('H:i'))->toBe('08:00')
        ->and($entry->end_time?->format('H:i'))->toBe('09:00');
});
