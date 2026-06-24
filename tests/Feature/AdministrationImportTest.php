<?php

use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Models\Customer;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function createAdminUserForImport(): array
{
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Admin Import '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    return [$company, $user];
}

test('administrator can view csv import page', function () {
    [, $user] = createAdminUserForImport();

    $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->get(route('administration.import'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/Import')
            ->has('entityDefinitions.customers')
            ->has('entityDefinitions.suppliers')
            ->has('entityDefinitions.products')
        );
});

test('administrator can upload csv and import customers', function () {
    Storage::fake('local');
    [$company, $user] = createAdminUserForImport();

    $csv = "Name,Email\nAcme Corp,acme@example.com\nBeta Ltd,beta@example.com\n";
    $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

    $uploadResponse = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->post(route('administration.import.upload'), [
            'entity' => 'customers',
            'file' => $file,
        ]);

    $uploadResponse->assertOk();
    $token = $uploadResponse->json('token');
    expect($token)->not->toBeEmpty();

    $runResponse = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->postJson(route('administration.import.run'), [
            'token' => $token,
            'entity' => 'customers',
            'mapping' => [
                'name' => '__idx:0',
                'email' => '__idx:1',
            ],
            'defaults' => [],
        ]);

    $runResponse->assertOk()
        ->assertJson([
            'created' => 2,
            'skipped' => 0,
        ]);

    expect(Customer::query()->where('company_id', $company->id)->count())->toBe(2);
    expect(Cache::get('admin-import:'.$token))->toBeNull();
});
