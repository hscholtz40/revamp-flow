<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'serial_number',
        'batch_id',
        'status',
        'stock_movement_id',
        'invoice_id',
        'jobcard_id',
        'purchase_date',
        'sale_date',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'sale_date' => 'date',
    ];

    /**
     * Get the company that owns this serial number.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the product for this serial number.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the batch for this serial number.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    /**
     * Get the stock movement for this serial number.
     */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /**
     * Get the invoice where this serial number was sold.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the jobcard where this serial number was used.
     */
    public function jobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class);
    }

    /**
     * Scope to filter available serial numbers.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope to filter sold serial numbers.
     */
    public function scopeSold($query)
    {
        return $query->where('status', 'sold');
    }
}
