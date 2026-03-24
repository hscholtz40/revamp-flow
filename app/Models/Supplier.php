<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'xero_contact_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'vat_number',
        'notes',
        'is_active',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    /**
     * Get the company that owns this supplier.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the products supplied by this supplier.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the purchase orders for this supplier.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Scope to filter active suppliers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
