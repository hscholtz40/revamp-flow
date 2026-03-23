<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'supplier_id',
        'xero_purchase_order_id',
        'po_number',
        'order_date',
        'expected_delivery_date',
        'received_date',
        'status',
        'subtotal',
        'tax_amount',
        'total',
        'notes',
        'terms',
        'user_id',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'received_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
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

    public function lineGroups(): MorphMany
    {
        return $this->morphMany(LineGroup::class, 'line_groupable')->orderBy('sort_order');
    }

    /**
     * Generate a unique PO number.
     */
    public static function generatePONumber(int $companyId): string
    {
        $customNumber = DB::transaction(function () use ($companyId) {
            $company = Company::whereKey($companyId)->lockForUpdate()->first();
            if ($company && $company->purchase_order_number_prefix !== null && $company->purchase_order_number_next !== null) {
                $next = max(1, (int) $company->purchase_order_number_next);
                $number = $company->purchase_order_number_prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                $company->purchase_order_number_next = $next + 1;
                $company->save();

                return $number;
            }

            return null;
        });

        if ($customNumber !== null) {
            return $customNumber;
        }

        $year = date('Y');
        $lastPO = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPO ? ((int) substr($lastPO->po_number, -4)) + 1 : 1;

        return 'PO-'.$year.str_pad($sequence, 4, '0', STR_PAD_LEFT);
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
        $items = $this->items()->get();
        $subtotal = $items->sum('total');
        $taxAmount = $items->sum('tax_amount') ?? 0;

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->total = $subtotal + $taxAmount;
    }
}
