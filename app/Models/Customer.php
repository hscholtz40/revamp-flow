<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'vat_number',
        'notes',
        'xero_contact_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function jobcards(): HasMany
    {
        return $this->hasMany(Jobcard::class);
    }

    public function smsActivities(): HasMany
    {
        return $this->hasMany(SMSActivity::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}


