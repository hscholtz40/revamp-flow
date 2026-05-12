<?php

use App\Http\Middleware\EnsureUserIsAdministrator;
use App\Models\Group;
use App\Models\QuickBooksSettings;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

test('quickbooks callback rejects missing oauth state', function () {
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Admin QB '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    $response = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->get(route('quickbooks.callback', ['code' => 'test-code', 'realmId' => '123']));

    $response->assertRedirect('/administration/quickbooks-settings');
    $response->assertSessionHasErrors(['message']);
});

test('quickbooks callback rejects invalid oauth state', function () {
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Admin QB '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    $response = $this
        ->withoutMiddleware(EnsureUserIsAdministrator::class)
        ->actingAs($user)
        ->withSession(['quickbooks_oauth_state' => 'expected-state'])
        ->get(route('quickbooks.callback', [
            'code' => 'test-code',
            'state' => 'wrong-state',
            'realmId' => '123',
        ]));

    $response->assertRedirect('/administration/quickbooks-settings');
    $response->assertSessionHasErrors(['message']);
});

test('administrator can view quickbooks settings inertia page', function () {
    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Admin QB '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    $this->actingAs($user)
        ->get(route('administration.quickbooks-settings'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/QuickBooksSettings')
            ->has('settings')
            ->has('currentCompany')
            ->has('availableCompanies')
            ->where('quickbooksUseSandbox', true));
});

test('quickbooks disconnect calls intuit revoke then clears stored tokens', function () {
    Http::fake([
        QuickBooksService::REVOKE_URL => Http::response(['status' => 'ok'], 200),
    ]);

    $company = coverageCreateCompany();
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'current_company_id' => $company->id,
    ]);
    $group = Group::query()->create([
        'name' => 'Admin QB Revoke '.uniqid(),
        'description' => '',
        'is_administrator' => true,
        'payment_method_card' => true,
        'payment_method_cash' => true,
        'payment_method_eft' => true,
    ]);
    $user->groups()->attach($group->id);

    $settings = QuickBooksSettings::getForCompany($company->id);
    $settings->update([
        'client_id' => 'test-client-id',
        'client_secret' => 'test-client-secret',
        'refresh_token' => 'test-refresh-token',
        'access_token' => 'test-access-token',
        'token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDays(90),
        'realm_id' => '12345',
        'realm_name' => 'Test Co',
    ]);

    $this->actingAs($user)
        ->delete(route('quickbooks.disconnect'))
        ->assertRedirect();

    Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
        if ($request->url() !== QuickBooksService::REVOKE_URL) {
            return false;
        }
        $data = $request->data();

        return is_array($data) && ($data['token'] ?? null) === 'test-refresh-token';
    });

    $settings->refresh();
    expect($settings->refresh_token)->toBeNull();
    expect($settings->access_token)->toBeNull();
    expect($settings->realm_id)->toBeNull();
});
