<?php

namespace Tests\Unit;

use App\Support\SafeLog;
use PHPUnit\Framework\TestCase;

class SafeLogTest extends TestCase
{
    public function test_redact_context_strips_secrets_and_masks_contact_fields(): void
    {
        $ctx = SafeLog::redactContext([
            'client_secret' => 's3cr3t',
            'to' => '+27123456789',
            'nested' => ['access_token' => 'tok'],
        ]);

        $this->assertSame('[REDACTED]', $ctx['client_secret']);
        $this->assertStringContainsString('6789', (string) $ctx['to']);
        $this->assertStringNotContainsString('27123', (string) $ctx['to']);
        $this->assertSame('[REDACTED]', $ctx['nested']['access_token']);
    }

    public function test_http_response_context_never_includes_full_body(): void
    {
        $long = str_repeat('a', 500);
        $ctx = SafeLog::httpResponseContext(400, json_encode(['error' => 'bad', 'x' => $long]));

        $this->assertSame(400, $ctx['http_status']);
        $this->assertArrayHasKey('body_excerpt', $ctx);
        $this->assertLessThanOrEqual(260, strlen((string) $ctx['body_excerpt']));
    }
}
