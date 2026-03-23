<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SMSSettings extends Model
{
    protected $table = 'sms_settings';

    protected $fillable = [
        'bulksms_username',
        'bulksms_password',
        'bulksms_sender_name',
        'is_active',
    ];

    protected $hidden = [
        'bulksms_password',
    ];

    protected function casts(): array
    {
        return [
            'bulksms_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the active SMS settings
     */
    public static function getActive(): ?self
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Check if SMS is configured and active
     */
    public static function isConfigured(): bool
    {
        $settings = static::getActive();

        return $settings && $settings->bulksms_username && $settings->bulksms_password;
    }
}
