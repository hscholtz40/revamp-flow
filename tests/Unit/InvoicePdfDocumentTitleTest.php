<?php

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

class InvoicePdfDocumentTitleTest extends TestCase
{
    public function test_invoice_without_tax_uses_invoice_title(): void
    {
        $invoice = new Invoice(['tax_amount' => 0]);

        $this->assertSame('Invoice', $invoice->getPdfDocumentTitle());
    }

    public function test_invoice_with_tax_uses_tax_invoice_title(): void
    {
        $invoice = new Invoice(['tax_amount' => 12.34]);

        $this->assertSame('Tax Invoice', $invoice->getPdfDocumentTitle());
    }

    public function test_negligible_tax_treated_as_no_tax(): void
    {
        $invoice = new Invoice(['tax_amount' => 0.000001]);

        $this->assertSame('Invoice', $invoice->getPdfDocumentTitle());
    }
}
