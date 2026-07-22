<?php

use App\Models\Company;
use App\Support\CompanyMailer;

it('uses company smtp when host and port are configured without auth', function () {
    $company = new Company([
        'name' => 'Acme',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'noreply@example.com',
        'smtp_from_name' => 'Acme Mail',
    ]);

    $resolved = CompanyMailer::resolve($company);

    expect($resolved['mailer'])->toBe('company_smtp')
        ->and($resolved['from_address'])->toBe('noreply@example.com')
        ->and($resolved['from_name'])->toBe('Acme Mail')
        ->and(config('mail.mailers.company_smtp.host'))->toBe('smtp.example.com')
        ->and(config('mail.mailers.company_smtp.port'))->toBe(587);
});

it('falls back to env smtp when username is set without a stored password', function () {
    $company = new Company([
        'name' => 'Acme',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 587,
        'smtp_username' => 'user@example.com',
    ]);
    // Simulate no encrypted password stored in attributes.
    $company->setRawAttributes(array_merge($company->getAttributes(), [
        'smtp_password' => null,
    ]), true);

    config(['mail.mailers.smtp.host' => 'mailpit']);

    $resolved = CompanyMailer::resolve($company);

    expect($resolved['mailer'])->toBe('smtp')
        ->and(CompanyMailer::hasCompanySmtpOverride($company))->toBeFalse()
        ->and(CompanyMailer::hasPartialCompanySmtp($company))->toBeTrue();
});

it('uses company smtp when username and stored password are present', function () {
    $company = new Company([
        'name' => 'Acme',
        'smtp_host' => 'smtp.example.com',
        'smtp_port' => 465,
        'smtp_username' => 'user@example.com',
        'smtp_encryption' => 'ssl',
    ]);
    $company->smtp_password = 'secret-password';

    $resolved = CompanyMailer::resolve($company);

    expect($resolved['mailer'])->toBe('company_smtp')
        ->and(config('mail.mailers.company_smtp.username'))->toBe('user@example.com')
        ->and(config('mail.mailers.company_smtp.password'))->toBe('secret-password');
});

it('normalizes starttls encryption to tls', function () {
    expect(CompanyMailer::normalizeEncryption('starttls'))->toBe('tls')
        ->and(CompanyMailer::normalizeEncryption('none'))->toBeNull()
        ->and(CompanyMailer::normalizeEncryption('ssl'))->toBe('ssl');
});

it('disables tls peer verification when smtp_verify_peer is false', function () {
    $company = new Company([
        'name' => 'Acme',
        'smtp_host' => 'mail.si-casa.co.za',
        'smtp_port' => 587,
        'smtp_encryption' => 'starttls',
        'smtp_verify_peer' => false,
    ]);

    $resolved = CompanyMailer::resolve($company);

    expect($resolved['mailer'])->toBe('company_smtp')
        ->and(config('mail.mailers.company_smtp.encryption'))->toBe('tls')
        ->and(config('mail.mailers.company_smtp.verify_peer'))->toBeFalse()
        ->and(config('mail.mailers.company_smtp.stream.ssl.verify_peer'))->toBeFalse()
        ->and(config('mail.mailers.company_smtp.stream.ssl.verify_peer_name'))->toBeFalse();
});
