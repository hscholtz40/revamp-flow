<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;

test('quote email preserves line breaks in description and custom message', function () {
    $company = Company::create([
        'name' => 'Email Line Break Co',
        'is_active' => true,
    ]);

    $customer = Customer::create([
        'company_id' => $company->id,
        'name' => 'Email Customer',
        'email' => 'customer@example.com',
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'quote_number' => 'Q-LINE-001',
        'title' => 'Multiline quote',
        'description' => "Line one\nLine two",
        'status' => 'draft',
        'subtotal' => 0,
        'tax_amount' => 0,
        'total' => 0,
    ]);

    $quote->load('company', 'customer', 'lineItems');

    $html = view('emails.quote', [
        'quote' => $quote,
        'customMessage' => "Hello\nWorld",
        'acceptUrl' => 'https://example.com/accept',
        'declineUrl' => 'https://example.com/decline',
    ])->render();

    expect($html)->toContain('Line one<br')
        ->and($html)->toContain('Line two')
        ->and($html)->toContain('Hello<br')
        ->and($html)->toContain('World');
});
