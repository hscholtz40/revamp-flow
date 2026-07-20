<?php

namespace Tests\Unit;

use App\Services\PdfGenerationService;
use PHPUnit\Framework\TestCase;

class PdfGenerationServiceHandlebarsTest extends TestCase
{
    public function test_rendered_html_sanitizer_removes_scripts_and_inline_handlers(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function sanitize(string $html): string
            {
                return $this->sanitizeRenderedTemplateHtml($html);
            }
        };

        $out = $service->sanitize(
            '<div onclick="alert(1)">Ok</div><script>alert(2)</script><img src="javascript:alert(3)" onerror="alert(4)" />'
        );

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringNotContainsString('onclick=', $out);
        $this->assertStringNotContainsString('onerror=', $out);
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function test_double_brace_escapes_html_entities(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function expose(string $html, array $data): string
            {
                return $this->processHandlebarsTemplate($html, $data);
            }
        };

        $out = $service->expose(
            '<p>{{customer.name}}</p>',
            ['customer' => ['name' => '<script>alert(1)</script>']]
        );

        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringNotContainsString('<script>', $out);
    }

    public function test_triple_brace_allows_raw_html(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function expose(string $html, array $data): string
            {
                return $this->processHandlebarsTemplate($html, $data);
            }
        };

        $out = $service->expose(
            '<div>{{{invoice.notes}}}</div>',
            ['invoice' => ['notes' => '<p class="x">OK</p>']]
        );

        $this->assertStringContainsString('<p class="x">OK</p>', $out);
    }

    public function test_each_this_double_brace_is_escaped(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function expose(string $html, array $data): string
            {
                return $this->processHandlebarsTemplate($html, $data);
            }
        };

        $out = $service->expose(
            '{{#each items}}<span>{{this.label}}</span>{{/each}}',
            ['items' => [['label' => '<b>Hi</b>']]]
        );

        $this->assertStringContainsString('&lt;b&gt;', $out);
        $this->assertStringNotContainsString('<b>Hi</b>', $out);
    }

    public function test_inject_company_footer_replaces_footer_left_content(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function expose(string $html, string $module, \App\Models\Company $company): string
            {
                return $this->injectCompanyFooter($html, $module, $company);
            }
        };

        $company = new \App\Models\Company([
            'invoice_footer' => "Thank you for your business.\nPayment due in 30 days.",
        ]);

        $html = '<div class="footer"><div class="footer-left">JobCard Online (Registered to Acme)</div></div>';
        $out = $service->expose($html, 'invoice', $company);

        $this->assertStringContainsString('Thank you for your business.', $out);
        $this->assertStringContainsString('Payment due in 30 days.', $out);
        $this->assertStringContainsString('<br>', $out);
        $this->assertStringNotContainsString('JobCard Online', $out);
    }

    public function test_inject_company_footer_leaves_html_unchanged_when_footer_empty(): void
    {
        $service = new class extends PdfGenerationService
        {
            public function expose(string $html, string $module, \App\Models\Company $company): string
            {
                return $this->injectCompanyFooter($html, $module, $company);
            }
        };

        $company = new \App\Models\Company(['invoice_footer' => '']);
        $html = '<div class="footer"><div class="footer-left">Default footer</div></div>';
        $out = $service->expose($html, 'invoice', $company);

        $this->assertSame($html, $out);
    }
}
