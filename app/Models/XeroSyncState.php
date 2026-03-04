<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XeroSyncState extends Model
{
    protected $fillable = [
        'company_id',
        'module',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
