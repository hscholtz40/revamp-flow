<?php

use App\Models\Invoice;
use App\Models\License;
use App\Models\Product;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Query;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Models\ReminderLog;
use App\Services\LicenseBillingService;
use App\Services\ContractorLicenseProvisioningService;

test('accepting contractor query creates customer and contact', function () {
    config(['app.is_licensing_instance' => true]);

    $company = coverageCreateCompany();
    $user = coverageCreateUserWithPermissions($company, [
        'queries' => ['list', 'view', 'edit'],
    ]);

    $packageProduct = coverageSeedProduct($company, [
        'name' => 'Licensing Option 1',
        'sku' => 'LIC-OPT1-'.uniqid(),
        'price' => 550,
        'is_licensing_package' => true,
        'package_code' => Query::PACKAGE_OPTION_1,
        'license_standard_users' => 1,
        'license_limited_users' => 5,
        'monthly_credits' => 8,
    ]);

    $signupNow = Carbon::create(2026, 8, 1, 10, 30, 0);
    Carbon::setTestNow($signupNow);

    $query = Query::create([
        'company_id' => $company->id,
        'kind' => Query::KIND_CONTRACTOR,
        'name' => 'John',
        'surname' => 'Smith',
        'email' => 'john.smith@example.com',
        'cell' => '0123456789',
        'description' => 'Contractor onboarding submission',
        'company_name' => 'Smith Electrical CC',
        'company_registration_no' => '2020/123456/07',
        'company_address' => '1 Main Street',
        'company_email' => 'admin@smith-electric.example',
        'company_contact_number' => '0210000000',
        'company_website' => 'https://smith-electric.example',
        'selected_package' => Query::PACKAGE_OPTION_1,
        'selected_product_id' => $packageProduct->id,
        'status' => Query::STATUS_OPEN,
        'response' => Query::RESPONSE_PENDING,
    ]);

    $this->actingAs($user)
        ->post(route('queries.accept-contractor', $query))
        ->assertRedirect();

    $query->refresh();
    expect($query->response)->toBe(Query::RESPONSE_ACCEPTED)
        ->and($query->status)->toBe(Query::STATUS_CLOSED)
        ->and($query->accepted_customer_id)->not->toBeNull()
        ->and($query->accepted_contact_id)->not->toBeNull();

    $customer = Customer::findOrFail($query->accepted_customer_id);
    $contact = Contact::findOrFail($query->accepted_contact_id);

    expect($customer->company_id)->toBe($company->id)
        ->and($customer->name)->toBe('Smith Electrical CC')
        ->and($contact->customer_id)->toBe($customer->id)
        ->and($contact->name)->toBe('John Smith');

    $license = License::query()
        ->where('source_query_id', $query->id)
        ->first();

    expect($license)->not->toBeNull()
        ->and($license->deployed_at)->toBeNull()
        ->and($license->product_id)->toBe($packageProduct->id)
        ->and($license->monthly_credits)->toBe(8);

    $expectedFirstInvoiceDate = Carbon::parse($query->created_at)
        ->startOfDay()
        ->addDays(ContractorLicenseProvisioningService::TRIAL_DAYS)
        ->toDateString();

    // Ensure next_invoice_date uses "60 days after signup" and no invoice is created immediately.
    expect($license->next_invoice_date?->toDateString())->toBe($expectedFirstInvoiceDate)
        ->and(Invoice::query()->where('source_type', 'license')->where('source_id', $license->id)->count())->toBe(0);

    // When billing generates due invoices, the first invoice should appear on the due date.
    $license->update(['auto_email_invoice' => false]);

    Carbon::setTestNow(Carbon::parse($expectedFirstInvoiceDate)->addHours(10));
    app(LicenseBillingService::class)->generateDueInvoices();

    expect(Invoice::query()->where('source_type', 'license')->where('source_id', $license->id)->count())
        ->toBe(1);

    $license->refresh();
    $expectedSecondInvoiceDate = Carbon::parse($expectedFirstInvoiceDate)
        ->addMonth()
        ->toDateString();

    expect($license->next_invoice_date?->toDateString())->toBe($expectedSecondInvoiceDate);

    Carbon::setTestNow(Carbon::parse($expectedSecondInvoiceDate)->addHours(10));
    app(LicenseBillingService::class)->generateDueInvoices();

    expect(Invoice::query()->where('source_type', 'license')->where('source_id', $license->id)->count())
        ->toBe(2);

    Carbon::setTestNow();
});

test('overdue license invoices notify admins once per day', function () {
    config([
        'app.is_licensing_instance' => true,
        'mail.default' => 'array',
        'mail.mailers.smtp.transport' => 'array',
        'mail.from.address' => 'noreply@example.com',
        'mail.from.name' => 'JobCard Online',
    ]);

    Mail::fake();

    $company = coverageCreateCompany([
        'email' => 'admin@coverage.test',
    ]);
    $customer = coverageSeedCustomer($company, ['email' => 'billing-customer@example.com']);

    $invoice = Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-'.uniqid(),
        'title' => 'Overdue license invoice',
        'status' => 'sent',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'subtotal' => 100,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
        'source_type' => 'license',
        'source_id' => 999,
    ]);

    $this->artisan('licenses:generate-invoices')->assertSuccessful();
    $this->artisan('licenses:generate-invoices')->assertSuccessful();

    expect(ReminderLog::query()
        ->where('company_id', $company->id)
        ->where('reminder_type', 'overdue_license_invoice_admin')
        ->where('remindable_type', Invoice::class)
        ->where('remindable_id', $invoice->id)
        ->where('status', 'sent')
        ->count())->toBe(1);

    $reminder = ReminderLog::query()
        ->where('company_id', $company->id)
        ->where('reminder_type', 'overdue_license_invoice_admin')
        ->where('remindable_type', Invoice::class)
        ->where('remindable_id', $invoice->id)
        ->where('status', 'sent')
        ->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->recipient_email)->toBe($company->email);
});
