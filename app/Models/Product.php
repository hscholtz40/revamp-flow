<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'tags' => 'array',
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

    public function jobcardLineItems(): HasMany
    {
        return $this->hasMany(JobcardLineItem::class);
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock_quantity <= $this->min_stock_level;
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
