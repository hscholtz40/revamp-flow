<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'line_group_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'discount_percentage',
        'total',
        'tax_rate_id',
        'tax_amount',
        'account_id',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'formatted_unit_price',
        'formatted_total',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function lineGroup(): BelongsTo
    {
        return $this->belongsTo(LineGroup::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    /**
     * Calculate total based on quantity, unit price, and discounts
     */
    public function calculateTotal(): void
    {
        $quantity = $this->quantity ?? 0;
        $unitPrice = $this->unit_price ?? 0;
        $discountAmount = $this->discount_amount ?? 0;
        $discountPercentage = $this->discount_percentage ?? 0;
        
        $subtotal = $quantity * $unitPrice;
        
        // Apply discount: percentage takes precedence over amount
        if ($discountPercentage > 0) {
            $discountAmount = $subtotal * ($discountPercentage / 100);
        }
        
        $this->total = number_format((float) ($subtotal - $discountAmount), 2, '.', '');

        // Calculate tax amount based on associated tax rate
        if ($this->tax_rate_id && $this->taxRate) {
            $this->tax_amount = number_format((float) (ceil(($this->total * ($this->taxRate->rate / 100)) * 100) / 100), 2, '.', '');
        } else {
            $this->tax_amount = '0.00';
        }
    }

    /**
     * Get formatted unit price
     */
    public function getFormattedUnitPriceAttribute(): string
    {
        return 'R' . number_format($this->unit_price ?? 0, 2);
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'R' . number_format($this->total ?? 0, 2);
    }
}
