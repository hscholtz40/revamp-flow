<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_administrator',
        'payment_method_card',
        'payment_method_cash',
        'payment_method_eft',
    ];

    protected $casts = [
        'is_administrator' => 'boolean',
        'payment_method_card' => 'boolean',
        'payment_method_cash' => 'boolean',
        'payment_method_eft' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(GroupPermission::class);
    }
}


