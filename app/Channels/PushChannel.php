<?php

namespace App\Channels;

use App\Services\FcmMessagingService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PushChannel
{
    public function __construct(
        private readonly FcmMessagingService $fcmMessagingService
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toPush')) {
            return;
        }

        $push = $notification->toPush($notifiable);
        $devices = $notifiable->devices()->get();

        foreach ($devices as $device) {
            try {
                if ($device->platform === 'ios') {
                    $this->sendApns($device->token, $push);
                } else {
                    $this->sendFcm($device->token, $push);
                }
            } catch (\Throwable $e) {
                Log::error('Push notification failed', [
                    'device_id' => $device->id,
                    'platform' => $device->platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendApns(string $token, array $push): void
    {
        if (! $this->curlSupportsHttp2()) {
            Log::error('APNs requires libcurl compiled with HTTP/2 (nghttp2). Run `curl --version` on the server to verify.');

            return;
        }

        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $push['title'] ?? '',
                    'body' => $push['body'] ?? '',
                ],
                'badge' => 1,
                'sound' => 'default',
            ],
        ];

        foreach ($push['data'] ?? [] as $key => $value) {
            $payload[$key] = $value;
        }

        $apnsKeyId = config('services.push.apns_key_id');
        $apnsTeamId = config('services.push.apns_team_id');
        $apnsAppBundleId = config('services.push.apns_app_bundle_id');
        $apnsPrivateKey = config('services.push.apns_private_key');

        if (! $apnsKeyId || ! $apnsTeamId || ! $apnsAppBundleId || ! $apnsPrivateKey) {
            Log::warning('APNs credentials not configured, skipping push', [
                'token' => substr($token, 0, 20).'...',
            ]);

            return;
        }

        $jwt = $this->buildApnsJwt($apnsKeyId, $apnsTeamId, $apnsPrivateKey);
        $host = config('services.push.apns_use_sandbox')
            ? 'api.sandbox.push.apple.com'
            : 'api.push.apple.com';

        $response = Http::withOptions($this->apnsHttpOptions())
            ->withToken($jwt, 'Bearer')
            ->withHeaders([
                'apns-topic' => $apnsAppBundleId,
                'apns-priority' => '10',
                'Content-Type' => 'application/json',
            ])
            ->post("https://{$host}/3/device/{$token}", $payload);

        if (! $response->successful()) {
            Log::warning('APNs rejected push notification', [
                'status' => $response->status(),
                'body' => $response->body(),
                'host' => $host,
                'token' => substr($token, 0, 20).'...',
            ]);
        }
    }

    private function sendFcm(string $token, array $push): void
    {
        $this->fcmMessagingService->sendToDevice($token, $push);
    }

    /**
     * @return array<string, mixed>
     */
    private function apnsHttpOptions(): array
    {
        if (! defined('CURL_HTTP_VERSION_2_0')) {
            define('CURL_HTTP_VERSION_2_0', 3);
        }

        return [
            'version' => 2.0,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
            ],
        ];
    }

    private function curlSupportsHttp2(): bool
    {
        if (! function_exists('curl_version')) {
            return false;
        }

        if (! defined('CURL_VERSION_HTTP2')) {
            define('CURL_VERSION_HTTP2', 65536);
        }

        $features = curl_version()['features'] ?? 0;

        return ($features & CURL_VERSION_HTTP2) !== 0;
    }

    private function buildApnsJwt(string $keyId, string $teamId, string $privateKey): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'ES256', 'kid' => $keyId], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));

        $signed = $this->signWithEs256($header.'.'.$claims, $privateKey);

        return $header.'.'.$claims.'.'.$signed;
    }

    private function signWithEs256(string $data, string $privateKey): string
    {
        $pem = "-----BEGIN PRIVATE KEY-----\n"
            .chunk_split($privateKey, 64, "\n")
            ."-----END PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid APNs private key.');
        }

        $signature = '';
        if (! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            openssl_pkey_free($key);

            throw new RuntimeException('Failed to sign APNs JWT.');
        }

        openssl_pkey_free($key);

        return $this->base64UrlEncode($this->derToConcatenatedSignature($signature));
    }

    private function derToConcatenatedSignature(string $der): string
    {
        $pos = 0;

        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x30) {
            throw new RuntimeException('Invalid APNs JWT signature encoding.');
        }

        $length = ord($der[$pos++]);
        if ($length & 0x80) {
            $byteCount = $length & 0x7F;
            $length = 0;
            for ($i = 0; $i < $byteCount; $i++) {
                $length = ($length << 8) | ord($der[$pos++]);
            }
        }

        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x02) {
            throw new RuntimeException('Invalid APNs JWT signature encoding.');
        }

        $rLength = ord($der[$pos++]);
        $r = substr($der, $pos, $rLength);
        $pos += $rLength;

        if (! isset($der[$pos]) || ord($der[$pos++]) !== 0x02) {
            throw new RuntimeException('Invalid APNs JWT signature encoding.');
        }

        $sLength = ord($der[$pos++]);
        $s = substr($der, $pos, $sLength);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT)
            .str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
