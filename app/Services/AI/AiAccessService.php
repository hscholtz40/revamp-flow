<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use App\Models\GoogleIntegrationSettings;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;

class AiAccessService
{
    public function ensureFeatureAllowed(User $user, string $feature): void
    {
        $settings = GoogleIntegrationSettings::record();
        if (! $settings->ai_enabled) {
            throw new AuthorizationException('AI features are disabled.');
        }
        if ($settings->ai_admin_only && ! $user->isAdministrator()) {
            throw new AuthorizationException('AI access is restricted to administrators.');
        }
        if (GoogleIntegrationSettings::openAiApiKey() === '') {
            throw new AuthorizationException('OpenAI API key is not configured.');
        }

        $start = CarbonImmutable::now()->startOfDay();
        $userCount = AiUsage::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature)
            ->where('created_at', '>=', $start)
            ->count();
        if ($userCount >= (int) $settings->ai_daily_user_limit) {
            throw new AuthorizationException('Daily AI limit reached for this user.');
        }

        $companyId = $user->getCurrentCompany()?->id;
        if ($companyId) {
            $companyCount = AiUsage::query()
                ->where('company_id', $companyId)
                ->where('feature', $feature)
                ->where('created_at', '>=', $start)
                ->count();
            if ($companyCount >= (int) $settings->ai_daily_company_limit) {
                throw new AuthorizationException('Daily AI limit reached for this company.');
            }
        }
    }

    public function capabilitiesForUser(?User $user): array
    {
        $settings = GoogleIntegrationSettings::record();
        $available = $settings->ai_enabled && GoogleIntegrationSettings::openAiApiKey() !== '';
        $allowed = $available && $user && (! $settings->ai_admin_only || $user->isAdministrator());

        return [
            'enabled' => (bool) $settings->ai_enabled,
            'available' => $available,
            'allowed' => (bool) $allowed,
            'admin_only' => (bool) $settings->ai_admin_only,
            'prompt_logging_enabled' => (bool) $settings->ai_prompt_logging_enabled,
            'daily_user_limit' => (int) $settings->ai_daily_user_limit,
            'daily_company_limit' => (int) $settings->ai_daily_company_limit,
        ];
    }
}
