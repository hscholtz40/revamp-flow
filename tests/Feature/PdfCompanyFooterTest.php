<?php

use App\Models\PdfTemplate;
use App\Models\Quote;
use App\Services\PdfGenerationService;

it('renders configured quote footer in default blade pdf output', function () {
    $company = coverageCreateCompany([
        'quote_footer' => 'Payment due within 14 days.',
    ]);
    $customer = coverageSeedCustomer($company);
    $user = coverageCreateUserWithPermissions($company, [
        'quotes' => ['view', 'create', 'edit', 'delete'],
    ]);

    $quote = Quote::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'quote_number' => 'Q-FOOTER-'.uniqid(),
        'title' => 'Footer test quote',
        'status' => 'draft',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $response = $this->actingAs($user)->get(route('quotes.download-pdf', $quote));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('renders configured company footer in custom pdf templates', function () {
    $company = coverageCreateCompany([
        'invoice_footer' => 'Bank details: ABSA 123456789',
    ]);
    $customer = coverageSeedCustomer($company);

    PdfTemplate::create([
        'company_id' => $company->id,
        'module' => 'invoice',
        'name' => 'Footer Template',
        'html_template' => '<div class="footer footer-left">{{company.invoice_footer}}</div>',
        'css_styles' => '',
        'is_active' => true,
        'is_default' => true,
    ]);

    $invoice = \App\Models\Invoice::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-FOOTER-'.uniqid(),
        'title' => 'Footer test invoice',
        'status' => 'draft',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $service = new PdfGenerationService;
    $pdf = $service->generatePdf('invoice', compact('invoice', 'company'), $company);
    $html = $pdf->getDomPDF()->outputHtml();

    expect($html)->toContain('Bank details: ABSA 123456789');
});
