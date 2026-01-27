<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'sku',
        'price',
        'cost',
        'unit',
        'stock_quantity',
        'min_stock_level',
        'track_stock',
        'is_active',
        'category',
        'tags',
        'image_path',
        'notes',
        'xero_item_id',
        'purchase_account_code',
        'sales_account_code',
        // New inventory fields
        'supplier_id',
        'barcode',
        'low_stock_threshold',
        'unit_of_measure',
        'cost_price',
        'selling_price',
        'valuation_method',
        'track_batches',
        'track_serial_numbers',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'tags' => 'array',
        'track_batches' => 'boolean',
        'track_serial_numbers' => 'boolean',
    ];

    /**
     * Scope to filter by type (product or service)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function jobcardLineItems(): HasMany
    {
        return $this->hasMany(JobcardLineItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ProductSerialNumber::class);
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        if (!$this->track_stock) {
            return false;
        }
        
        $threshold = $this->low_stock_threshold ?? $this->min_stock_level ?? 10;
        return $this->stock_quantity <= $threshold;
    }

    /**
     * Calculate profit margin
     */
    public function getProfitMarginAttribute(): ?float
    {
        $cost = (float) $this->cost;
        $price = (float) $this->price;
        
        if (!$cost || $cost == 0) {
            return null;
        }

        return (($price - $cost) / $cost) * 100;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format((float) $this->price, 2);
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): ?string
    {
        return $this->cost ? '$' . number_format((float) $this->cost, 2) : null;
    }
}
