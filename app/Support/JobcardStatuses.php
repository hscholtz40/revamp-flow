<?php

namespace App\Support;

use App\Models\Company;

class JobcardStatuses
{
    public const ALL = [
        'new',
        'needs_scheduling',
        'scheduled',
        'dispatched',
        'purchase_order_sent_on_dispatch',
        'order_received',
        'stock_checked',
        'delivery_scheduled',
        'delivered',
        'accepted',
        'en_route',
        'on_site',
        'paused',
        'waiting_for_parts',
        'needs_follow_up',
        'emergency',
        'completed',
        'cancelled',
    ];

    public static function validationRule(): string
    {
        return 'in:'.implode(',', self::ALL);
    }

    public static function mysqlEnumDefinition(): string
    {
        return "'".implode("','", self::ALL)."'";
    }

    /**
     * @return array<string, string>
     */
    public static function defaultLabels(): array
    {
        return Company::DEFAULT_JOBCARD_STATUS_LABELS;
    }

    /**
     * @return array<string, string>
     */
    public static function badgeColors(): array
    {
        return [
            'new' => 'gray',
            'needs_scheduling' => 'yellow',
            'scheduled' => 'blue',
            'dispatched' => 'indigo',
            'purchase_order_sent_on_dispatch' => 'indigo',
            'order_received' => 'blue',
            'stock_checked' => 'blue',
            'delivery_scheduled' => 'blue',
            'delivered' => 'green',
            'accepted' => 'blue',
            'en_route' => 'indigo',
            'on_site' => 'blue',
            'paused' => 'orange',
            'waiting_for_parts' => 'orange',
            'needs_follow_up' => 'yellow',
            'emergency' => 'red',
            'completed' => 'green',
            'cancelled' => 'red',
        ];
    }
}
