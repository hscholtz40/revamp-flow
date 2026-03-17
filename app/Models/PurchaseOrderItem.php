<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'line_group_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total',
        'tax_rate_id',
        'tax_amount',
        'account_id',
        'quantity_received',
        'description',
        'product_batch_id',
        'serial_number_ids',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'quantity_received' => 'integer',
        'serial_number_ids' => 'array',
    ];

    /**
     * Get the purchase order for this item.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lineGroup(): BelongsTo
    {
        return $this->belongsTo(LineGroup::class);
    }

    /**
     * Get the product for this item.
     */
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
     * Get the batch for this item.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    /**
     * Calculate total before saving.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total = $item->quantity * $item->unit_cost;

            // Calculate tax amount based on associated tax rate
            if ($item->tax_rate_id) {
                $taxRate = TaxRate::find($item->tax_rate_id);
                if ($taxRate) {
                    $item->tax_amount = round($item->total * ($taxRate->rate / 100), 2);
                } else {
                    $item->tax_amount = 0;
                }
            } else {
                $item->tax_amount = 0;
            }
        });
    }

    /**
     * Check if item is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->quantity_received >= $this->quantity;
    }

    /**
     * Get remaining quantity to receive.
     */
    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->quantity_received);
    }
}
