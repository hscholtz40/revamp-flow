<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Model;

class XeroSyncState extends Model
{
    use ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'module',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
