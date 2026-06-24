<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('admin user seeder can require password reset on bootstrap', function () {
    config([
        'installer.bootstrap_admin_email' => 'deploy-admin@example.com',
        'installer.bootstrap_admin_password' => 'P@ssw0rd',
        'installer.bootstrap_admin_name' => 'Deploy Customer',
        'installer.bootstrap_admin_must_reset_password' => true,
    ]);

    $this->seed(AdminUserSeeder::class);

    $user = User::query()->where('email', 'deploy-admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Deploy Customer')
        ->and($user->must_reset_password)->toBeTrue()
        ->and(Hash::check('P@ssw0rd', $user->password))->toBeTrue();
});

test('users who must reset password are redirected to required password page', function () {
    $user = User::factory()->create([
        'must_reset_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.required'));
});

test('users who must reset password can update password and continue', function () {
    $user = User::factory()->create([
        'must_reset_password' => true,
        'password' => Hash::make('P@ssw0rd'),
    ]);

    $this->actingAs($user)
        ->put(route('password.required.update'), [
            'password' => 'New-Secure-Password-1!',
            'password_confirmation' => 'New-Secure-Password-1!',
        ])
        ->assertRedirect(route('dashboard'));

    $user->refresh();

    expect($user->must_reset_password)->toBeFalse()
        ->and(Hash::check('New-Secure-Password-1!', $user->password))->toBeTrue();
});

test('required password update rejects the default deploy password', function () {
    $user = User::factory()->create([
        'must_reset_password' => true,
        'password' => Hash::make('P@ssw0rd'),
    ]);

    $this->actingAs($user)
        ->from(route('password.required'))
        ->put(route('password.required.update'), [
            'password' => 'P@ssw0rd',
            'password_confirmation' => 'P@ssw0rd',
        ])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_reset_password)->toBeTrue();
});

test('ensure password is current middleware allows password required routes', function () {
    $user = User::factory()->create([
        'must_reset_password' => true,
    ]);

    $this->actingAs($user)
        ->get(route('password.required'))
        ->assertOk();
});
