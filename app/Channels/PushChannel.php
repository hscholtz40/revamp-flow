<?php

namespace App\Channels;

use App\Models\Device;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushChannel
{
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
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $push['title'] ?? '',
                    'body' => $push['body'] ?? '',
                ],
                'badge' => 1,
                'sound' => 'default',
            ],
            'data' => $push['data'] ?? [],
        ];

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

        // Build JWT for APNs
        $jwt = $this->buildApnsJwt($apnsKeyId, $apnsTeamId, $apnsPrivateKey);

        Http::withToken($jwt, 'Bearer')
            ->withHeaders([
                'apns-topic' => $apnsAppBundleId,
                'apns-priority' => '10',
            ])
            ->post("https://api.push.apple.com/3/device/{$token}", $payload);
    }

    private function sendFcm(string $token, array $push): void
    {
        $serverKey = config('services.push.fcm_server_key');

        if (! $serverKey) {
            Log::warning('FCM server key not configured, skipping push', [
                'token' => substr($token, 0, 20).'...',
            ]);

            return;
        }

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $push['title'] ?? '',
                'body' => $push['body'] ?? '',
            ],
            'data' => $push['data'] ?? [],
        ];

        Http::withHeaders([
            'Authorization' => "key={$serverKey}",
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);
    }

    private function buildApnsJwt(string $keyId, string $teamId, string $privateKey): string
    {
        $header = base64_encode(json_encode(['alg' => 'ES256', 'kid' => $keyId]));
        $claims = base64_encode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
            'exp' => time() + 3600,
        ]));

        $signed = $this->signWithEs256($header.'.'.$claims, $privateKey);

        return $header.'.'.$claims.'.'.$signed;
    }

    private function signWithEs256(string $data, string $privateKey): string
    {
        $privateKey = "-----BEGIN PRIVATE KEY-----\n"
            .chunk_split($privateKey, 64, "\n")
            ."-----END PRIVATE KEY-----\n";

        $key = openssl_pkey_get_private($privateKey);
        $signature = '';
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($key);

        return rtrim(base64_encode($signature), '=');
    }
}
