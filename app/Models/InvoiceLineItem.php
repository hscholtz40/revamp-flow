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
        'total',
        'sort_order',
        'serial_number_ids',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
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
        $this->total = $this->quantity * $this->unit_price;
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