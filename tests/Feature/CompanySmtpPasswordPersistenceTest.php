<?php

it('keeps the existing smtp password when the password field is blank', function () {
    $company = coverageCreateCompany([
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_encryption' => 'tls',
    ]);
    $company->smtp_password = 'keep-me-secret';
    $company->save();

    $user = coverageCreateUserWithPermissions($company, [
        'customers' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    \App\Models\Group::query()->whereIn('id', $user->groups()->pluck('groups.id'))->update(['is_administrator' => true]);

    $this->actingAs($user)->put(route('company-settings.update', $company), [
        'name' => $company->name,
        'email' => $company->email,
        'is_active' => true,
        'is_default' => (bool) $company->is_default,
        'enable_pos' => false,
        'enable_document_signing' => false,
        'enable_dispatch' => false,
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'smtp_verify_peer' => true,
    ])->assertRedirect();

    $company->refresh();

    expect($company->smtp_password)->toBe('keep-me-secret')
        ->and($company->smtp_host)->toBe('mail.example.com');
});

it('keeps the existing smtp password when the password field is omitted as null', function () {
    $company = coverageCreateCompany([
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_encryption' => 'tls',
    ]);
    $company->smtp_password = 'keep-me-secret';
    $company->save();

    $user = coverageCreateUserWithPermissions($company, [
        'customers' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    \App\Models\Group::query()->whereIn('id', $user->groups()->pluck('groups.id'))->update(['is_administrator' => true]);

    $this->actingAs($user)->put(route('company-settings.update', $company), [
        'name' => $company->name,
        'email' => $company->email,
        'is_active' => true,
        'is_default' => (bool) $company->is_default,
        'enable_pos' => false,
        'enable_document_signing' => false,
        'enable_dispatch' => false,
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_password' => null,
        'smtp_encryption' => 'tls',
        'smtp_verify_peer' => true,
    ])->assertRedirect();

    $company->refresh();

    expect($company->smtp_password)->toBe('keep-me-secret');
});

it('updates the smtp password when a new value is provided', function () {
    $company = coverageCreateCompany([
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_encryption' => 'tls',
    ]);
    $company->smtp_password = 'old-secret';
    $company->save();

    $user = coverageCreateUserWithPermissions($company, [
        'customers' => ['list', 'view', 'create', 'edit', 'delete'],
    ]);
    \App\Models\Group::query()->whereIn('id', $user->groups()->pluck('groups.id'))->update(['is_administrator' => true]);

    $this->actingAs($user)->put(route('company-settings.update', $company), [
        'name' => $company->name,
        'email' => $company->email,
        'is_active' => true,
        'is_default' => (bool) $company->is_default,
        'enable_pos' => false,
        'enable_document_signing' => false,
        'enable_dispatch' => false,
        'smtp_host' => 'mail.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
        'smtp_password' => 'new-secret',
        'smtp_encryption' => 'tls',
        'smtp_verify_peer' => true,
    ])->assertRedirect();

    $company->refresh();

    expect($company->smtp_password)->toBe('new-secret');
});
