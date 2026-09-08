<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Group;
use App\Models\GroupPermission;
use App\Models\Jobcard;
use App\Models\JobcardAttachment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAttachment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createEvidenceUploadContext(): array
{
    $company = Company::create([
        'name' => 'Evidence Upload Co '.uniqid(),
        'is_active' => true,
    ]);

    $group = Group::create([
        'name' => 'Evidence Staff '.uniqid(),
        'is_administrator' => false,
    ]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'jobcards',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
    ]);

    GroupPermission::create([
        'group_id' => $group->id,
        'module' => 'purchase-orders',
        'can_view' => true,
        'can_list' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
    ]);

    $user = User::factory()->create([
        'current_company_id' => $company->id,
        'email' => 'evidence-'.uniqid().'@example.com',
    ]);
    $user->groups()->attach($group->id);
    $user->companies()->attach($company->id);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Evidence Customer',
        'email' => 'evidence-customer-'.uniqid().'@example.com',
        'account_code' => Customer::generateAccountCode('Evidence Customer', $company->id),
    ]);

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-EVID-'.uniqid(),
        'title' => 'Evidence Jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $supplier = Supplier::create([
        'company_id' => $company->id,
        'name' => 'Evidence Supplier',
        'email' => 'evidence-supplier-'.uniqid().'@example.com',
    ]);

    $purchaseOrder = PurchaseOrder::create([
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-EVID-'.uniqid(),
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'user_id' => $user->id,
    ]);

    return compact('company', 'user', 'jobcard', 'purchaseOrder');
}

test('jobcard evidence upload accepts description', function () {
    Storage::fake('public');

    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    $image = UploadedFile::fake()->image('site.jpg');

    $this->actingAs($user)
        ->post(route('jobcards.attachments.store', $jobcard), [
            'attachments' => [$image],
            'description' => 'Front of site before work',
        ])
        ->assertRedirect();

    $attachment = JobcardAttachment::query()->where('jobcard_id', $jobcard->id)->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->description)->toBe('Front of site before work')
        ->and($attachment->type)->toBe('image');
});

test('jobcard evidence description can be updated', function () {
    Storage::fake('public');

    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    $attachment = JobcardAttachment::create([
        'jobcard_id' => $jobcard->id,
        'path' => 'jobcard-attachments/1/demo.jpg',
        'type' => 'image',
        'original_name' => 'demo.jpg',
        'description' => null,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patchJson(route('jobcards.attachments.update', [$jobcard, $attachment]), [
            'description' => 'Updated site photo note',
        ])
        ->assertOk()
        ->assertJsonPath('attachment.description', 'Updated site photo note');

    expect($attachment->fresh()->description)->toBe('Updated site photo note');
});

test('purchase order accepts photo and video evidence with description', function () {
    Storage::fake('public');

    ['user' => $user, 'purchaseOrder' => $purchaseOrder] = createEvidenceUploadContext();

    $image = UploadedFile::fake()->image('delivery.jpg');
    $video = UploadedFile::fake()->create('unload.mp4', 500, 'video/mp4');

    $this->actingAs($user)
        ->post(route('purchase-orders.attachments.store', $purchaseOrder), [
            'attachments' => [$image, $video],
            'description' => 'Goods received at warehouse',
        ])
        ->assertRedirect();

    $attachments = PurchaseOrderAttachment::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->orderBy('type')
        ->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments->pluck('type')->sort()->values()->all())->toBe(['image', 'video'])
        ->and($attachments->every(fn ($attachment) => $attachment->description === 'Goods received at warehouse'))->toBeTrue();
});

test('purchase order evidence description can be updated', function () {
    Storage::fake('public');

    ['user' => $user, 'purchaseOrder' => $purchaseOrder] = createEvidenceUploadContext();

    $attachment = PurchaseOrderAttachment::create([
        'purchase_order_id' => $purchaseOrder->id,
        'path' => 'purchase-order-attachments/1/demo.jpg',
        'type' => 'image',
        'original_name' => 'demo.jpg',
        'description' => null,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->patchJson(route('purchase-orders.attachments.update', [$purchaseOrder, $attachment]), [
            'description' => 'Packaging damage on arrival',
        ])
        ->assertOk()
        ->assertJsonPath('attachment.description', 'Packaging damage on arrival');

    expect($attachment->fresh()->description)->toBe('Packaging damage on arrival');
});

test('mobile jobcard show includes evidence attachments', function () {
    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    JobcardAttachment::create([
        'jobcard_id' => $jobcard->id,
        'path' => 'jobcard-attachments/1/site.jpg',
        'type' => 'image',
        'original_name' => 'site.jpg',
        'description' => 'Front elevation',
        'uploaded_by' => $user->id,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

    $this->getJson("/api/v1/jobcards/{$jobcard->id}")
        ->assertOk()
        ->assertJsonPath('attachments.0.original_name', 'site.jpg')
        ->assertJsonPath('attachments.0.type', 'image')
        ->assertJsonPath('attachments.0.description', 'Front elevation')
        ->assertJsonPath('attachments.0.path', 'jobcard-attachments/1/site.jpg')
        ->assertJsonFragment([
            'url' => url('/storage/jobcard-attachments/1/site.jpg'),
        ]);
});

test('mobile api can upload jobcard evidence with description', function () {
    Storage::fake('public');

    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

    $image = UploadedFile::fake()->image('mobile-site.jpg');
    $video = UploadedFile::fake()->create('mobile-clip.mp4', 400, 'video/mp4');

    $this->post("/api/v1/jobcards/{$jobcard->id}/attachments", [
        'attachments' => [$image, $video],
        'description' => 'Captured on site from mobile',
        'latitude' => -26.2041,
        'longitude' => 28.0473,
        'location_accuracy' => 9.5,
    ], [
        'Accept' => 'application/json',
    ])->assertCreated()
        ->assertJsonPath('attachments.0.description', 'Captured on site from mobile')
        ->assertJsonPath('attachments.1.description', 'Captured on site from mobile')
        ->assertJsonPath('attachments.0.has_location', true)
        ->assertJsonPath('attachments.0.latitude', '-26.2041000')
        ->assertJsonPath('attachments.0.longitude', '28.0473000')
        ->assertJsonStructure([
            'message',
            'attachments' => [
                ['id', 'url', 'path', 'type', 'original_name', 'description', 'latitude', 'longitude', 'location_accuracy', 'has_location', 'created_at'],
            ],
        ]);

    expect(JobcardAttachment::query()->where('jobcard_id', $jobcard->id)->count())->toBe(2);

    $types = JobcardAttachment::query()
        ->where('jobcard_id', $jobcard->id)
        ->pluck('type')
        ->sort()
        ->values()
        ->all();

    expect($types)->toBe(['image', 'video']);

    $this->assertDatabaseHas('jobcard_attachments', [
        'jobcard_id' => $jobcard->id,
        'latitude' => -26.2041,
        'longitude' => 28.0473,
    ]);
});

test('mobile api rejects incomplete evidence location', function () {
    Storage::fake('public');

    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

    $this->post("/api/v1/jobcards/{$jobcard->id}/attachments", [
        'attachments' => [UploadedFile::fake()->image('incomplete.jpg')],
        'latitude' => -26.2041,
    ], [
        'Accept' => 'application/json',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

test('mobile api can update and delete jobcard evidence', function () {
    Storage::fake('public');

    ['user' => $user, 'jobcard' => $jobcard] = createEvidenceUploadContext();

    $path = 'jobcard-attachments/'.$jobcard->company_id.'/demo.jpg';
    Storage::disk('public')->put($path, 'fake-image');

    $attachment = JobcardAttachment::create([
        'jobcard_id' => $jobcard->id,
        'path' => $path,
        'type' => 'image',
        'original_name' => 'demo.jpg',
        'description' => null,
        'uploaded_by' => $user->id,
    ]);

    \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);

    $this->patchJson("/api/v1/jobcards/{$jobcard->id}/attachments/{$attachment->id}", [
        'description' => 'Updated from mobile',
    ])->assertOk()
        ->assertJsonPath('attachment.description', 'Updated from mobile');

    $this->deleteJson("/api/v1/jobcards/{$jobcard->id}/attachments/{$attachment->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Attachment deleted.');

    expect(JobcardAttachment::query()->find($attachment->id))->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
