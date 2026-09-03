<?php

use App\Models\Invoice;
use App\Models\Jobcard;
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

    $service = new PdfGenerationService;
    $pdf = $service->generatePdf('quote', compact('quote', 'company'), $company);
    $html = $pdf->getDomPDF()->outputHtml();
    $decoded = pdfDecodedText($pdf->output());

    expect($html)->toContain('Payment due within 14 days.')
        ->and($html)->toContain('@page')
        ->and($decoded)->toContain('Payment due within 14 days.');
});

it('renders configured invoice and jobcard footers in default blade pdf output', function () {
    $company = coverageCreateCompany([
        'invoice_footer' => 'Invoice banking details ABSA 123456789',
        'jobcard_footer' => 'Jobcard warranty 12 months',
    ]);
    $customer = coverageSeedCustomer($company);

    $invoice = Invoice::create([
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

    $jobcard = Jobcard::create([
        'company_id' => $company->id,
        'customer_id' => $customer->id,
        'job_number' => 'JC-FOOTER-'.uniqid(),
        'title' => 'Footer test jobcard',
        'status' => 'new',
        'subtotal' => 100,
        'discount_amount' => 0,
        'discount_percentage' => 0,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'total' => 100,
    ]);

    $service = new PdfGenerationService;

    $invoiceHtml = $service->generatePdf('invoice', compact('invoice', 'company'), $company)->getDomPDF()->outputHtml();
    $jobcardHtml = $service->generatePdf('jobcard', compact('jobcard', 'company'), $company)->getDomPDF()->outputHtml();

    expect($invoiceHtml)->toContain('Invoice banking details ABSA 123456789')
        ->and($jobcardHtml)->toContain('Jobcard warranty 12 months');
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
        'html_template' => '<div class="footer"><div data-gjs-type="default" id="iyy" class="footer-left">{{company.invoice_footer}}</div></div>',
        'css_styles' => '.footer { position: fixed; bottom: 20px; }',
        'is_active' => true,
        'is_default' => true,
    ]);

    $invoice = Invoice::create([
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

    expect($html)->toContain('Bank details: ABSA 123456789')
        ->and($html)->toContain('margin-bottom: 70px');
});

function pdfDecodedText(string $pdf): string
{
    $chunks = [$pdf];

    if (preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $matches)) {
        foreach ($matches[1] as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                $decoded = @gzinflate($stream);
            }
            if (is_string($decoded) && $decoded !== '') {
                $chunks[] = $decoded;
            }
        }
    }

    return implode("\n", $chunks);
}
