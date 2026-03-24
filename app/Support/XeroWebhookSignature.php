<?php

namespace App\Support;

/**
 * Xero signs webhook bodies with HMAC-SHA256 using the app webhook key (base64 digest).
 *
 * @see https://developer.xero.com/documentation/guides/webhooks/overview/
 */
final class XeroWebhookSignature
{
    public static function isValid(string $rawBody, ?string $signatureHeader, ?string $webhookKey): bool
    {
        if ($webhookKey === null || $webhookKey === '') {
            return false;
        }

        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $webhookKey, true));

        return hash_equals($expected, $signatureHeader);
    }
}
