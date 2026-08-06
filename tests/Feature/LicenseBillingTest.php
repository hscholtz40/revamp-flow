<?php

use App\Models\Invoice;
use App\Models\License;
use App\Services\LicenseBillingService;
use App\Services\PdfGenerationService;

beforeEach(function () {
    config(['app.is_licensing_instance' => true]);
});

function createLicenseBillingContext(array $licenseOverrides = []): array
{
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'invoices' => ['list', 'view', 'create'],
        'customers' => ['list', 'view'],
    ]);
    $customer = coverageSeedCustomer($company, [
        'email' => 'billing-customer@example.com',
        'terms' => 'COD',
    ]);

    $license = License::create(array_merge([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'license_key' => License::generateLicenseKey(),
        'standard_users' => 2,
        'limited_users' => 5,
        'status' => 'active',
        'billing_cycle' => License::BILLING_CYCLE_MONTHLY,
        'pricing_model' => License::PRICING_MODEL_PER_USER,
        'price_standard_monthly' => 100,
        'price_limited_monthly' => 50,
        'auto_email_invoice' => false,
    ], $licenseOverrides));

    return compact('company', 'user', 'customer', 'license');
}

test('license billing creates per-user monthly invoice linked to license', function () {
    ['license' => $license] = createLicenseBillingContext();

    $result = app(LicenseBillingService::class)->createAndOptionallyEmail($license);

    expect($result['skipped'])->toBeFalse()
        ->and($result['invoice'])->not->toBeNull();

    $invoice = $result['invoice'];
    expect($invoice->source_type)->toBe('license')
        ->and($invoice->source_id)->toBe($license->id)
        ->and((float) $invoice->subtotal)->toBe(450.0) // 2*100 + 5*50
        ->and($invoice->lineItems)->toHaveCount(2);

    $license->refresh();
    expect($license->last_invoiced_at)->not->toBeNull()
        ->and($license->next_invoice_date)->not->toBeNull()
        ->and($license->next_invoice_date->gt(now()->startOfDay()))->toBeTrue();
});

test('license billing creates fixed annual invoice', function () {
    ['license' => $license] = createLicenseBillingContext([
        'billing_cycle' => License::BILLING_CYCLE_ANNUAL,
        'pricing_model' => License::PRICING_MODEL_FIXED,
        'price_standard_monthly' => null,
        'price_limited_monthly' => null,
        'fixed_amount_annual' => 12000,
    ]);

    $result = app(LicenseBillingService::class)->createAndOptionallyEmail($license);
    $invoice = $result['invoice'];

    expect($result['skipped'])->toBeFalse()
        ->and($invoice->lineItems)->toHaveCount(1)
        ->and((float) $invoice->lineItems->first()->total)->toBe(12000.0)
        ->and($invoice->lineItems->first()->description)->toContain('Annual');
});

test('license billing recalculates from current seats on next run', function () {
    ['license' => $license] = createLicenseBillingContext([
        'standard_users' => 1,
        'limited_users' => 0,
        'price_standard_monthly' => 200,
        'price_limited_monthly' => 0,
        'next_invoice_date' => now()->toDateString(),
        'last_invoiced_at' => now()->subMonth(),
    ]);

    $license->update(['standard_users' => 3]);

    $result = app(LicenseBillingService::class)->createAndOptionallyEmail($license->fresh());

    expect((float) $result['invoice']->subtotal)->toBe(600.0);
});

test('licenses generate invoices command processes due licenses', function () {
    ['license' => $license] = createLicenseBillingContext([
        'next_invoice_date' => now()->subDay()->toDateString(),
        'last_invoiced_at' => now()->subMonth(),
        'standard_users' => 1,
        'limited_users' => 0,
        'price_standard_monthly' => 99,
    ]);

    $this->artisan('licenses:generate-invoices')
        ->assertSuccessful();

    expect(Invoice::query()->where('source_type', 'license')->where('source_id', $license->id)->count())->toBe(1);

    $license->refresh();
    expect($license->next_invoice_date->gt(now()->startOfDay()))->toBeTrue();
});

test('creating a license via http creates first invoice when billing configured', function () {
    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'invoices' => ['list', 'view', 'create'],
        'customers' => ['list', 'view'],
    ]);
    $customer = coverageSeedCustomer($company, ['email' => 'c@example.com']);

    $this->actingAs($user)->post(route('licenses.store'), [
        'customer_id' => $customer->id,
        'limited_users' => 2,
        'standard_users' => 1,
        'status' => 'active',
        'billing_cycle' => 'monthly',
        'pricing_model' => 'per_user',
        'price_standard_monthly' => 100,
        'price_limited_monthly' => 40,
        'auto_email_invoice' => false,
    ])->assertRedirect();

    $license = License::query()->where('company_id', $company->id)->latest('id')->first();
    expect($license)->not->toBeNull()
        ->and($license->billing_cycle)->toBe('monthly');

    $invoice = Invoice::query()->where('source_type', 'license')->where('source_id', $license->id)->first();
    expect($invoice)->not->toBeNull()
        ->and((float) $invoice->subtotal)->toBe(180.0); // 1*100 + 2*40
});

test('license show includes linked invoices panel data', function () {
    ['company' => $company, 'user' => $user, 'license' => $license] = createLicenseBillingContext();
    app(LicenseBillingService::class)->createAndOptionallyEmail($license);

    $this->actingAs($user)
        ->get(route('licenses.show', $license))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('licenses/Show')
            ->has('linkedInvoices', 1)
            ->where('license.billing_cycle', 'monthly')
        );
});

test('license-sourced invoice show page loads without nested source error', function () {
    ['company' => $company, 'user' => $user, 'license' => $license] = createLicenseBillingContext();
    $result = app(LicenseBillingService::class)->createAndOptionallyEmail($license);
    $invoice = $result['invoice'];

    expect($invoice)->not->toBeNull();

    $this->actingAs($user)
        ->get(route('invoices.show', $invoice))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('invoices/Show')
            ->where('invoice.source_type', 'license')
            ->where('invoice.source_id', $license->id)
        );
});

test('license billing emails invoice when auto_email is enabled', function () {
    config([
        'mail.default' => 'array',
        'mail.mailers.smtp.transport' => 'array',
        'mail.from.address' => 'noreply@example.com',
        'mail.from.name' => 'JobCard Online',
    ]);

    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('output')->once()->andReturn('%PDF-fake');

    $this->mock(PdfGenerationService::class, function ($mock) use ($pdf) {
        $mock->shouldReceive('generatePdf')->once()->andReturn($pdf);
    });

    ['license' => $license] = createLicenseBillingContext([
        'auto_email_invoice' => true,
    ]);

    $result = app(LicenseBillingService::class)->createAndOptionallyEmail($license);

    expect($result['emailed'])->toBeTrue()
        ->and($result['invoice']->fresh()->status)->toBe('sent');

    expect(\App\Models\EmailActivity::query()
        ->where('related_type', 'invoice')
        ->where('related_id', $result['invoice']->id)
        ->where('status', 'sent')
        ->exists())->toBeTrue();
});
