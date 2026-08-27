<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\JobcardAttachment;
use App\Models\Note;
use App\Models\User;
use App\Notifications\JobcardNoteNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function createJobcardMediaAndNotesContext(): array
{
    $company = Company::create([
        'name' => 'Jobcard Media Co '.uniqid(),
        'is_active' => true,
    ]);

    $adminGroup = Group::create([
        'name' => 'Admins '.uniqid(),
        'is_administrator' => true,
    ]);
    $staffGroup = Group::create([
        'name' => 'Staff '.uniqid(),
        'is_administrator' => false,
    ]);

    GroupPermission::create([
        'group_id' => $staffGroup->id,
        'module' => 'jobcards',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
    ]);

    $admin = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'admin-'.uniqid().'@example.com',
    ]);
    $assignee = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'assignee-'.uniqid().'@example.com',
    ]);
    $officeUser = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'office-'.uniqid().'@example.com',
    ]);

    $admin->groups()->attach($adminGroup->id);
    $assignee->groups()->attach($staffGroup->id);
    $officeUser->groups()->attach($staffGroup->id);

    $admin->companies()->attach($company->id);
    $assignee->companies()->attach($company->id);
    $officeUser->companies()->attach($company->id);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Media Customer',
        'email' => 'media-customer@example.com',
        'account_code' => Customer::generateAccountCode('Media Customer', $company->id),
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-MEDIA-'.uniqid(),
        'title' => 'Media Test Jobcard',
        'status' => 'new',
        'assigned_to_user_id' => $assignee->id,
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    return compact('company', 'admin', 'assignee', 'officeUser', 'customer', 'jobcard');
}

test('jobcard accepts photo and video attachments', function () {
    Storage::fake('public');

    ['officeUser' => $officeUser, 'jobcard' => $jobcard] = createJobcardMediaAndNotesContext();

    $image = UploadedFile::fake()->image('site.jpg');
    $video = UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4');

    $this->actingAs($officeUser)
        ->post(route('jobcards.attachments.store', $jobcard), [
            'attachments' => [$image, $video],
        ])
        ->assertRedirect();

    expect(JobcardAttachment::query()->where('jobcard_id', $jobcard->id)->count())->toBe(2);

    $types = JobcardAttachment::query()->where('jobcard_id', $jobcard->id)->pluck('type')->sort()->values()->all();
    expect($types)->toBe(['image', 'video']);
});

test('assigned user note notifies admin', function () {
    Notification::fake();

    ['admin' => $admin, 'assignee' => $assignee, 'jobcard' => $jobcard] = createJobcardMediaAndNotesContext();

    $this->actingAs($assignee)
        ->postJson(route('notes.store'), [
            'module' => 'jobcards',
            'record_id' => $jobcard->id,
            'subject' => 'On site update',
            'description' => 'Arrived and started work.',
        ])
        ->assertCreated();

    Notification::assertSentTo($admin, JobcardNoteNotification::class);
    Notification::assertNotSentTo($assignee, JobcardNoteNotification::class);
});

test('web user note notifies assigned user', function () {
    Notification::fake();

    ['admin' => $admin, 'assignee' => $assignee, 'officeUser' => $officeUser, 'jobcard' => $jobcard] = createJobcardMediaAndNotesContext();

    $this->actingAs($officeUser)
        ->postJson(route('notes.store'), [
            'module' => 'jobcards',
            'record_id' => $jobcard->id,
            'subject' => 'Customer called',
            'description' => 'Please call the customer back.',
        ])
        ->assertCreated();

    Notification::assertSentTo($assignee, JobcardNoteNotification::class);
    Notification::assertNotSentTo($admin, JobcardNoteNotification::class);
    Notification::assertNotSentTo($officeUser, JobcardNoteNotification::class);

    expect(Note::query()->where('noteable_id', $jobcard->id)->count())->toBe(1);
});
