<?php

namespace App\Services;

use App\Models\Company;
use App\Models\QuickBooksSettings;
use App\Support\SafeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * QuickBooks Online integration aligned with Intuit OAuth 2.0 and API conventions:
 *
 * @see https://developer.intuit.com/app/developer/qbo/docs/develop/authentication-and-authorization/oauth-2-0
 *
 * When implementing create/update calls, send an Idempotency-Key header (UUID) on mutating POSTs per QBO API guidance.
 */
class QuickBooksService
{
    public const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens';

    /** Official revoke endpoint (matches intuit/oauth-jsclient). */
    public const REVOKE_URL = 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';

    private QuickBooksSettings $settings;

    public function __construct(?Company $company = null)
    {
        $company ??= auth()->user()?->getCurrentCompany();
        if (! $company) {
            throw new \Exception('No company context for QuickBooks service');
        }
        $this->settings = QuickBooksSettings::getForCompany($company->id);
    }

    public static function accountingApiBaseUrl(): string
    {
        return config('services.quickbooks.use_sandbox', true)
            ? 'https://sandbox-quickbooks.api.intuit.com'
            : 'https://quickbooks.api.intuit.com';
    }

    public static function accountingMinorVersion(): int
    {
        return max(1, min(75, (int) config('services.quickbooks.minor_version', 75)));
    }

    /**
     * @return array<string, string>
     */
    public static function defaultIntuitApiHeaders(): array
    {
        $ua = (string) config('services.quickbooks.user_agent', 'JobCardOnline Laravel QuickBooks Client');

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => $ua,
        ];
    }

    /**
     * Revoke the refresh token (preferred) or access token at Intuit before discarding local copies.
     * Best-effort: failures are logged; callers should still clear local credentials.
     */
    public static function revokeIntuitTokens(QuickBooksSettings $settings): void
    {
        $token = $settings->getAttribute('refresh_token') ?: $settings->getAttribute('access_token');
        if (! is_string($token) || $token === '') {
            return;
        }

        $clientId = $settings->client_id;
        $clientSecret = $settings->getAttribute('client_secret');
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            SafeLog::integration('warning', 'quickbooks', 'Token revoke skipped (missing client credentials)', [
                'company_id' => $settings->company_id,
            ]);

            return;
        }

        $response = Http::timeout(20)
            ->withBasicAuth($clientId, $clientSecret)
            ->withHeaders(self::defaultIntuitApiHeaders())
            ->post(self::REVOKE_URL, [
                'token' => $token,
            ]);

        if (! $response->successful()) {
            SafeLog::integration(
                'warning',
                'quickbooks',
                'Token revoke request was not successful',
                SafeLog::httpResponseContext($response->status(), $response->body())
            );

            return;
        }

        SafeLog::integration('info', 'quickbooks', 'Tokens revoked at Intuit', [
            'company_id' => $settings->company_id,
            'used_refresh_token' => (bool) $settings->getAttribute('refresh_token'),
        ]);
    }

    /**
     * @return array{skipped: true, message: string}
     */
    public function placeholderSync(string $label): array
    {
        return [
            'skipped' => true,
            'message' => "QuickBooks {$label} sync is not implemented yet. OAuth and settings are ready for a future release.",
        ];
    }

    public function refreshTokenIfNeeded(): bool
    {
        if (! $this->settings->refresh_token) {
            return false;
        }

        if (! $this->settings->isTokenExpired()) {
            return true;
        }

        $response = Http::timeout(30)
            ->withBasicAuth($this->settings->client_id, $this->settings->client_secret)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => (string) config('services.quickbooks.user_agent', 'JobCardOnline Laravel QuickBooks Client'),
            ])
            ->asForm()
            ->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->settings->refresh_token,
            ]);

        if (! $response->successful()) {
            $json = $response->json();
            $isInvalidGrant = $response->status() === 400
                && is_array($json)
                && ($json['error'] ?? null) === 'invalid_grant';

            SafeLog::integration(
                'error',
                'quickbooks',
                'Token refresh failed',
                SafeLog::httpResponseContext($response->status(), $response->body())
            );

            if ($isInvalidGrant) {
                $this->clearInvalidOAuthTokens();
            }

            return false;
        }

        $tokens = $response->json();
        if (! is_array($tokens) || empty($tokens['access_token'])) {
            Log::error('QuickBooks token refresh returned unexpected payload');

            return false;
        }

        $update = [
            'access_token' => $tokens['access_token'],
            'token_expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600)),
        ];

        if (! empty($tokens['refresh_token'])) {
            $update['refresh_token'] = $tokens['refresh_token'];
        }

        if (isset($tokens['x_refresh_token_expires_in']) && is_numeric($tokens['x_refresh_token_expires_in'])) {
            $update['refresh_token_expires_at'] = now()->addSeconds((int) $tokens['x_refresh_token_expires_in']);
        }

        $this->settings->update($update);
        $this->settings->refresh();

        SafeLog::integration('info', 'quickbooks', 'Access token refreshed', [
            'company_id' => $this->settings->company_id,
        ]);

        return true;
    }

    public function fetchRealmDisplayName(): ?string
    {
        if (! $this->settings->realm_id || ! $this->settings->access_token) {
            return null;
        }

        if ($this->settings->isTokenExpired() && ! $this->refreshTokenIfNeeded()) {
            return null;
        }

        $this->settings->refresh();

        $url = self::accountingApiBaseUrl().'/v3/company/'.rawurlencode($this->settings->realm_id)
            .'/companyinfo/'.rawurlencode($this->settings->realm_id);

        $response = Http::timeout(25)
            ->withToken($this->settings->access_token)
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => (string) config('services.quickbooks.user_agent', 'JobCardOnline Laravel QuickBooks Client'),
            ])
            ->get($url, ['minorversion' => self::accountingMinorVersion()]);

        if (! $response->successful()) {
            SafeLog::integration(
                'warning',
                'quickbooks',
                'CompanyInfo lookup failed',
                SafeLog::httpResponseContext($response->status(), $response->body())
            );

            return null;
        }

        $data = $response->json();
        $info = $data['QueryResponse']['CompanyInfo'][0] ?? null;

        return is_array($info) && isset($info['CompanyName']) && is_string($info['CompanyName'])
            ? $info['CompanyName']
            : null;
    }

    private function clearInvalidOAuthTokens(): void
    {
        SafeLog::integration('warning', 'quickbooks', 'Clearing invalid OAuth tokens after refresh failure', [
            'company_id' => $this->settings->company_id,
            'realm_id' => $this->settings->realm_id,
        ]);

        $this->settings->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'refresh_token_expires_at' => null,
        ]);
        $this->settings->refresh();
    }
}
