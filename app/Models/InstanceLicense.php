<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanceLicense extends Model
{
    protected $fillable = [
        'license_key',
        'status',
        'message',
        'licensed_url',
        'limited_users',
        'standard_users',
        'monthly_credits',
        'customer_name',
        'expires_at',
        'last_validated_at',
    ];

    protected $casts = [
        'limited_users' => 'integer',
        'standard_users' => 'integer',
        'monthly_credits' => 'integer',
        'expires_at' => 'datetime',
        'last_validated_at' => 'datetime',
    ];
}
