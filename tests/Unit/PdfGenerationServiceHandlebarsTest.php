<?php

namespace Tests\Unit;

use App\Services\PdfGenerationService;
use PHPUnit\Framework\TestCase;

class PdfGenerationServiceHandlebarsTest extends TestCase
{
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
}
