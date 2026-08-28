<?php

namespace App\Models;

use App\Support\NotificationEventCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationSettings extends Model
{
    protected $fillable = [
        'company_id',
        'automation_enabled',
        'events',
    ];

    protected function casts(): array
    {
        return [
            'automation_enabled' => 'boolean',
            'events' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function getForCompany(int $companyId): self
    {
        $settings = static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'automation_enabled' => true,
                'events' => NotificationEventCatalog::defaultEvents(),
            ]
        );

        if (! is_array($settings->events) || $settings->events === []) {
            $settings->update(['events' => NotificationEventCatalog::defaultEvents()]);
            $settings->refresh();
        }

        return $settings;
    }

    public function eventConfig(string $eventKey): array
    {
        $defaults = NotificationEventCatalog::events()[$eventKey]['default'] ?? [
            'enabled' => false,
            'notify_admin' => false,
            'notify_client' => false,
            'notify_staff' => false,
        ];

        $stored = $this->events[$eventKey] ?? [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? $defaults['enabled']),
            'notify_admin' => (bool) ($stored['notify_admin'] ?? $defaults['notify_admin']),
            'notify_client' => (bool) ($stored['notify_client'] ?? $defaults['notify_client']),
            'notify_staff' => (bool) ($stored['notify_staff'] ?? $defaults['notify_staff']),
        ];
    }

    public function shouldSend(string $eventKey, string $recipientType): bool
    {
        if (! $this->automation_enabled) {
            return false;
        }

        $config = $this->eventConfig($eventKey);

        if (! $config['enabled']) {
            return false;
        }

        return match ($recipientType) {
            'admin' => $config['notify_admin'],
            'client' => $config['notify_client'],
            'staff' => $config['notify_staff'],
            default => false,
        };
    }
}
