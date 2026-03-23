<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Redacts secrets and trims PII-heavy payloads before writing integration / HTTP logs.
 */
final class SafeLog
{
    /**
     * Keys whose string values are replaced with a short excerpt (may contain customer or HTML content).
     *
     * @var list<string>
     */
    private const LONG_TEXT_KEYS = ['body', 'html', 'message', 'renderedhtml', 'custommessage', 'error_message'];

    /**
     * Keys whose values are masked as emails when scalar.
     *
     * @var list<string>
     */
    private const EMAIL_LIKE_KEYS = ['recipient_email', 'from', 'reply_to', 'bcc', 'cc', 'email'];

    /**
     * Keys whose values are masked as phone numbers when scalar.
     *
     * @var list<string>
     */
    private const PHONE_LIKE_KEYS = ['phone', 'mobile', 'to_phone'];

    /**
     * Keys that always become "[REDACTED]" (exact match, case-insensitive).
     *
     * @var list<string>
     */
    private const REDACT_EXACT_KEYS = [
        'authorization',
        'access_token',
        'refresh_token',
        'client_secret',
        'api_key',
        'api_secret',
        'smtp_password',
        'bulksms_password',
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
    ];

    public static function redactContext(array $context): array
    {
        return self::walk($context);
    }

    /**
     * @return array{http_status: int, body_excerpt?: string, body_keys?: list<string>, error_summary?: string}
     */
    public static function httpResponseContext(int $status, ?string $body, int $excerptMax = 240): array
    {
        $ctx = ['http_status' => $status];
        if ($body === null || trim($body) === '') {
            return $ctx;
        }

        $trim = trim($body);
        $decoded = json_decode($trim, true);
        if (is_array($decoded)) {
            $ctx['body_keys'] = array_keys($decoded);
            if (isset($decoded['error']) && is_string($decoded['error'])) {
                $ctx['error_summary'] = self::excerpt($decoded['error'], 120);
            }
            if (isset($decoded['error_description']) && is_string($decoded['error_description'])) {
                $ctx['error_description_summary'] = self::excerpt($decoded['error_description'], 120);
            }
        }

        $ctx['body_excerpt'] = self::excerpt($trim, $excerptMax);

        return $ctx;
    }

    public static function excerpt(?string $text, int $max = 200): string
    {
        if ($text === null) {
            return '';
        }

        return Str::limit(trim($text), $max, '…');
    }

    public static function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return $phone;
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 4) {
            return '****';
        }

        return '••••••'.substr($digits, -4);
    }

    public static function maskEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }
        if ($email === '') {
            return '';
        }
        if (! str_contains($email, '@')) {
            return '[redacted]';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localMasked = strlen($local) <= 1 ? '*' : $local[0].'***';

        return $localMasked.'@'.$domain;
    }

    /**
     * @param  array<int|string, mixed>  $emails
     * @return list<string|null>
     */
    public static function maskEmailList(array $emails): array
    {
        return array_values(array_map(function ($e) {
            return is_string($e) ? self::maskEmail($e) : null;
        }, $emails));
    }

    /**
     * Structured integration log line: channel prefix + redacted context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function integration(string $level, string $channel, string $message, array $context = []): void
    {
        $payload = self::redactContext($context);
        $line = "[{$channel}] {$message}";

        match ($level) {
            'debug' => Log::debug($line, $payload),
            'info' => Log::info($line, $payload),
            'notice' => Log::notice($line, $payload),
            'warning' => Log::warning($line, $payload),
            'error' => Log::error($line, $payload),
            default => Log::info($line, $payload),
        };
    }

    private static function walk(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $keyStr = is_string($k) || is_int($k) ? (string) $k : '';
            $lower = strtolower($keyStr);

            if ($lower !== '' && self::shouldRedactKey($lower)) {
                $out[$k] = '[REDACTED]';

                continue;
            }

            if ($lower === 'to') {
                if (is_array($v)) {
                    $out[$k] = self::maskEmailList($v);
                } elseif (is_string($v)) {
                    $out[$k] = str_contains($v, '@') ? self::maskEmail($v) : self::maskPhone($v);
                } else {
                    $out[$k] = self::walk($v);
                }

                continue;
            }

            if (in_array($lower, self::EMAIL_LIKE_KEYS, true) && is_string($v)) {
                $out[$k] = self::maskEmail($v);

                continue;
            }

            if (in_array($lower, self::PHONE_LIKE_KEYS, true) && is_string($v)) {
                $out[$k] = self::maskPhone($v);

                continue;
            }

            if (in_array($lower, self::LONG_TEXT_KEYS, true) && is_string($v) && strlen($v) > 160) {
                $out[$k] = self::excerpt($v, 160);

                continue;
            }

            $out[$k] = self::walk($v);
        }

        return $out;
    }

    private static function shouldRedactKey(string $lowerKey): bool
    {
        if (in_array($lowerKey, self::REDACT_EXACT_KEYS, true)) {
            return true;
        }

        if (str_contains($lowerKey, 'password')) {
            return true;
        }

        if (str_contains($lowerKey, 'secret')) {
            return true;
        }

        if (str_contains($lowerKey, 'authorization')) {
            return true;
        }

        if (str_contains($lowerKey, 'cookie')) {
            return true;
        }

        if (preg_match('/(^|_)(access|refresh|id)_token$/', $lowerKey) || $lowerKey === 'token') {
            return true;
        }

        return false;
    }
}
