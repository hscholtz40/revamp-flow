<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FcmMessagingService
{
    private const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const MESSAGING_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    public function credentials(): ?array
    {
        $path = config('services.push.fcm_credentials_path');
        if (is_string($path) && $path !== '' && is_readable($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json)) {
                return $this->normalizeCredentials($json);
            }
        }

        $jsonEnv = config('services.push.fcm_credentials_json');
        if (is_string($jsonEnv) && $jsonEnv !== '') {
            $json = json_decode($jsonEnv, true);
            if (is_array($json)) {
                return $this->normalizeCredentials($json);
            }
        }

        $projectId = config('services.push.fcm_project_id');
        $clientEmail = config('services.push.fcm_client_email');
        $privateKey = config('services.push.fcm_private_key');

        if (is_string($projectId) && $projectId !== ''
            && is_string($clientEmail) && $clientEmail !== ''
            && is_string($privateKey) && $privateKey !== '') {
            return [
                'project_id' => $projectId,
                'client_email' => $clientEmail,
                'private_key' => $privateKey,
            ];
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /**
     * @param  array{title?: string, body?: string, data?: array<string, mixed>}  $push
     */
    public function sendToDevice(string $token, array $push): void
    {
        $credentials = $this->credentials();
        if ($credentials === null) {
            Log::warning('FCM credentials not configured, skipping push', [
                'token' => substr($token, 0, 20).'...',
            ]);

            return;
        }

        $accessToken = $this->accessToken($credentials);
        $message = [
            'token' => $token,
            'notification' => [
                'title' => (string) ($push['title'] ?? ''),
                'body' => (string) ($push['body'] ?? ''),
            ],
        ];

        $data = $this->stringifyData($push['data'] ?? []);
        if ($data !== []) {
            $message['data'] = $data;
        }

        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            rawurlencode($credentials['project_id'])
        );

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post($url, ['message' => $message]);

        if (! $response->successful()) {
            Log::warning('FCM rejected push notification', [
                'status' => $response->status(),
                'body' => $response->body(),
                'token' => substr($token, 0, 20).'...',
            ]);
        }
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $credentials
     */
    private function accessToken(array $credentials): string
    {
        $cacheKey = 'fcm:access_token:'.sha1($credentials['client_email']);

        $token = Cache::get($cacheKey);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $jwt = $this->buildServiceAccountJwt(
            $credentials['client_email'],
            $credentials['private_key']
        );

        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to obtain FCM access token: '.$response->body());
        }

        $accessToken = (string) $response->json('access_token', '');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($accessToken === '') {
            throw new RuntimeException('FCM access token response did not include access_token.');
        }

        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, $expiresIn - 60)));

        return $accessToken;
    }

    private function buildServiceAccountJwt(string $clientEmail, string $privateKeyMaterial): string
    {
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $clientEmail,
            'scope' => self::MESSAGING_SCOPE,
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $signature = $this->signWithRs256($header.'.'.$claims, $privateKeyMaterial);

        return $header.'.'.$claims.'.'.$signature;
    }

    private function signWithRs256(string $data, string $privateKeyMaterial): string
    {
        $pem = $this->normalizePrivateKeyPem($privateKeyMaterial);
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid FCM service account private key.');
        }

        $signature = '';
        if (! openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            openssl_pkey_free($key);

            throw new RuntimeException('Failed to sign FCM service account JWT.');
        }

        openssl_pkey_free($key);

        return $this->base64UrlEncode($signature);
    }

    private function normalizePrivateKeyPem(string $key): string
    {
        $key = str_replace('\\n', "\n", trim($key));

        if (str_contains($key, 'BEGIN PRIVATE KEY')) {
            return $key;
        }

        return "-----BEGIN PRIVATE KEY-----\n"
            .chunk_split($key, 64, "\n")
            ."-----END PRIVATE KEY-----\n";
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{project_id: string, client_email: string, private_key: string}|null
     */
    private function normalizeCredentials(array $json): ?array
    {
        $projectId = $json['project_id'] ?? null;
        $clientEmail = $json['client_email'] ?? null;
        $privateKey = $json['private_key'] ?? null;

        if (! is_string($projectId) || $projectId === ''
            || ! is_string($clientEmail) || $clientEmail === ''
            || ! is_string($privateKey) || $privateKey === '') {
            return null;
        }

        return [
            'project_id' => $projectId,
            'client_email' => $clientEmail,
            'private_key' => $privateKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyData(array $data): array
    {
        $stringified = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $stringified[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $stringified;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
