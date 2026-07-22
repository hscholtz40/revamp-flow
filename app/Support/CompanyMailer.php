<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Log;

class CompanyMailer
{
    /**
     * Resolve the mailer name and sender identity for a company.
     *
     * @return array{mailer:string,from_address:string,from_name:string}
     */
    public static function resolve(?Company $company): array
    {
        $defaultFromAddress = (string) config('mail.from.address');
        $defaultFromName = (string) config('mail.from.name');

        if (! $company || ! self::hasCompanySmtpOverride($company)) {
            if ($company && self::hasPartialCompanySmtp($company)) {
                Log::warning('Company SMTP is incomplete; falling back to application mail settings', [
                    'company_id' => $company->id,
                    'has_host' => filled($company->smtp_host),
                    'has_port' => filled($company->smtp_port),
                    'has_username' => filled($company->smtp_username),
                    'has_password' => self::companyHasStoredSmtpPassword($company),
                    'fallback_mail_host' => config('mail.mailers.smtp.host'),
                ]);
            }

            return [
                'mailer' => 'smtp',
                'from_address' => $defaultFromAddress,
                'from_name' => $company?->name ?: $defaultFromName,
            ];
        }

        $encryption = self::normalizeEncryption($company->smtp_encryption);
        $verifyPeer = $company->smtp_verify_peer !== false;

        $mailerConfig = [
            'transport' => 'smtp',
            'host' => (string) $company->smtp_host,
            'port' => (int) $company->smtp_port,
            'encryption' => $encryption,
            'username' => filled($company->smtp_username) ? (string) $company->smtp_username : null,
            'password' => self::companyHasStoredSmtpPassword($company) ? (string) $company->smtp_password : null,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
            'verify_peer' => $verifyPeer,
        ];

        // Shared/hosted mail often presents a cert for a different hostname.
        // When peer verification is disabled, also relax stream SSL checks.
        if (! $verifyPeer) {
            $mailerConfig['stream'] = [
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ];
        }

        config([
            'mail.mailers.company_smtp' => $mailerConfig,
        ]);

        return [
            'mailer' => 'company_smtp',
            'from_address' => (string) ($company->smtp_from_email ?: $defaultFromAddress),
            'from_name' => (string) ($company->smtp_from_name ?: ($company->name ?: $defaultFromName)),
        ];
    }

    /**
     * Map UI encryption values to Symfony/Laravel SMTP encryption modes.
     * "starttls" is treated as STARTTLS ("tls"); "ssl" is implicit TLS (usually port 465).
     */
    public static function normalizeEncryption(mixed $encryption): ?string
    {
        $encryption = is_string($encryption) ? strtolower(trim($encryption)) : '';

        return match ($encryption) {
            '', 'none' => null,
            'starttls' => 'tls',
            'tls', 'ssl' => $encryption,
            default => $encryption !== '' ? $encryption : null,
        };
    }

    /**
     * Company SMTP override is active when host and port are configured.
     * Username/password are optional (some servers allow unauthenticated relay).
     */
    public static function hasCompanySmtpOverride(Company $company): bool
    {
        if (! filled($company->smtp_host) || ! filled($company->smtp_port)) {
            return false;
        }

        // If a username is set, require a stored password so we don't silently
        // authenticate with an empty password against a secured SMTP server.
        if (filled($company->smtp_username) && ! self::companyHasStoredSmtpPassword($company)) {
            return false;
        }

        return true;
    }

    public static function hasPartialCompanySmtp(Company $company): bool
    {
        return filled($company->smtp_host)
            || filled($company->smtp_port)
            || filled($company->smtp_username)
            || self::companyHasStoredSmtpPassword($company);
    }

    /**
     * Check for a stored SMTP password without relying on JSON serialization
     * (smtp_password is hidden on the Company model).
     */
    public static function companyHasStoredSmtpPassword(Company $company): bool
    {
        $raw = $company->getAttributes()['smtp_password'] ?? null;

        return is_string($raw) && trim($raw) !== '';
    }
}
