<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'supplier_id',
        'xero_contact_person_id',
        'name',
        'email',
        'phone',
        'position',
        'notes',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function emailActivities(): HasMany
    {
        return $this->hasMany(EmailActivity::class);
    }

    public function smsActivities(): HasMany
    {
        return $this->hasMany(SMSActivity::class);
    }
}
