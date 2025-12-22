<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSettings extends Model
{
    protected $table = 'whatsapp_settings';

    protected $fillable = [
        'company_id',
        'provider',
        'api_key',
        'api_secret',
        'account_sid',
        'from_number',
        'is_active',
    ];

    protected $hidden = [
        'api_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the company that owns these settings.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the current WhatsApp settings instance for the authenticated user's company
     */
    public static function getCurrent(): ?self
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }
        
        $currentCompany = $user->getCurrentCompany();
        if (!$currentCompany) {
            return null;
        }
        
        return static::where('company_id', $currentCompany->id)->first();
    }

    /**
     * Get WhatsApp settings for a specific company
     */
    public static function getForCompany(int $companyId): ?self
    {
        return static::where('company_id', $companyId)->first();
    }

    /**
     * Get the active WhatsApp settings (for backward compatibility)
     * @deprecated Use getCurrent() instead
     */
    public static function getActive(): ?self
    {
        return static::getCurrent();
    }

    /**
     * Check if WhatsApp is configured and active for the current company
     */
    public static function isConfigured(): bool
    {
        $settings = static::getCurrent();
        return $settings && $settings->is_active && $settings->api_key && $settings->from_number;
    }

    /**
     * Check if WhatsApp is configured for a specific company
     */
    public static function isConfiguredForCompany(int $companyId): bool
    {
        $settings = static::getForCompany($companyId);
        return $settings && $settings->is_active && $settings->api_key && $settings->from_number;
    }
}
