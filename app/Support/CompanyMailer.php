<?php

namespace App\Support;

use App\Models\Company;

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
            return [
                'mailer' => 'smtp',
                'from_address' => $defaultFromAddress,
                'from_name' => $company?->name ?: $defaultFromName,
            ];
        }

        $encryption = is_string($company->smtp_encryption) ? strtolower(trim($company->smtp_encryption)) : '';
        if ($encryption === '' || $encryption === 'none') {
            $encryption = null;
        }

        config([
            'mail.mailers.company_smtp' => [
                'transport' => 'smtp',
                'host' => (string) $company->smtp_host,
                'port' => (int) $company->smtp_port,
                'encryption' => $encryption,
                'username' => (string) $company->smtp_username,
                'password' => (string) $company->smtp_password,
                'timeout' => null,
                'local_domain' => env('MAIL_EHLO_DOMAIN'),
            ],
        ]);

        return [
            'mailer' => 'company_smtp',
            'from_address' => (string) ($company->smtp_from_email ?: $defaultFromAddress),
            'from_name' => (string) ($company->smtp_from_name ?: ($company->name ?: $defaultFromName)),
        ];
    }

    public static function hasCompanySmtpOverride(Company $company): bool
    {
        return filled($company->smtp_host)
            && filled($company->smtp_port)
            && filled($company->smtp_username)
            && filled($company->smtp_password);
    }
}

