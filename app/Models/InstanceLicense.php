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
        'last_validated_at',
    ];

    protected $casts = [
        'limited_users' => 'integer',
        'standard_users' => 'integer',
        'last_validated_at' => 'datetime',
    ];
}
