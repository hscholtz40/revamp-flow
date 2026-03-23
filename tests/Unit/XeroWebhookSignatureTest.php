<?php

namespace Tests\Unit;

use App\Support\XeroWebhookSignature;
use PHPUnit\Framework\TestCase;

class XeroWebhookSignatureTest extends TestCase
{
    public function test_accepts_valid_signature(): void
    {
        $body = '{"events":[]}';
        $key = 'my-webhook-secret';
        $sig = base64_encode(hash_hmac('sha256', $body, $key, true));

        $this->assertTrue(XeroWebhookSignature::isValid($body, $sig, $key));
    }

    public function test_rejects_wrong_key_or_signature(): void
    {
        $body = '{"events":[]}';
        $key = 'my-webhook-secret';
        $sig = base64_encode(hash_hmac('sha256', $body, $key, true));

        $this->assertFalse(XeroWebhookSignature::isValid($body, $sig, 'other-key'));
        $this->assertFalse(XeroWebhookSignature::isValid($body, 'bogus', $key));
        $this->assertFalse(XeroWebhookSignature::isValid($body, $sig, ''));
        $this->assertFalse(XeroWebhookSignature::isValid($body, '', $key));
    }
}
