<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'discount_percentage',
        'total',
        'sort_order',
        'serial_number_ids',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'total' => 'decimal:2',
        'sort_order' => 'integer',
        'serial_number_ids' => 'array',
    ];

    /**
     * Get the invoice that owns the line item.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the product for this line item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the total for this line item.
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
        
        $this->total = max(0, $subtotal - $discountAmount);
        $this->save();
    }

    /**
     * Get the product name or custom description.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->product ? $this->product->name : $this->description;
    }
}