<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'po_number',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'status', // 'draft', 'sent', 'received', 'cancelled'
        'subtotal',
        'tax_amount',
        'total',
        'notes',
        'terms',
        'user_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Get the company that owns this purchase order.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the supplier for this purchase order.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created this purchase order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items for this purchase order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Generate a unique PO number.
     */
    public static function generatePONumber(int $companyId): string
    {
        $year = date('Y');
        $lastPO = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPO ? ((int) substr($lastPO->po_number, -4)) + 1 : 1;

        return 'PO-' . $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if purchase order is fully received.
     */
    public function isFullyReceived(): bool
    {
        return $this->items()->get()->every(function ($item) {
            return $item->quantity_received >= $item->quantity;
        });
    }

    /**
     * Calculate totals from items.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $this->subtotal = $subtotal;
        // Tax calculation can be added here if needed
        $this->tax_amount = 0;
        $this->total = $subtotal + $this->tax_amount;
    }
}
