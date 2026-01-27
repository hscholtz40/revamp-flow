<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Invoice extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'invoice_number',
        'title',
        'description',
        'customer_id',
        'salesperson_id',
        'company_id',
        'status',
        'invoice_date',
        'due_date',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'xero_invoice_id',
        'terms',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'total_paid',
        'remaining_balance',
    ];

    /**
     * Get the customer that owns the invoice.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the salesperson assigned to the invoice.
     */
    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    /**
     * Get the company that owns the invoice.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the line items for the invoice.
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class)->orderBy('sort_order');
    }

    /**
     * Get the payments for this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date', 'desc');
    }

    /**
     * Get the source quote or jobcard that this invoice was converted from.
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Get the last invoice number for this year/month
        $lastInvoice = static::where('invoice_number', 'like', "INV-{$year}{$month}%")
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract the sequence number and increment it
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return "INV-{$year}{$month}" . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals for the invoice.
     */
    public function calculateTotals(): void
    {
        // Calculate subtotal before discounts (sum of quantity * unit_price)
        $subtotalBeforeDiscount = $this->lineItems->sum(function ($item) {
            return ($item->quantity ?? 0) * ($item->unit_price ?? 0);
        });
        
        // Calculate total discount from line items
        $totalDiscount = $this->lineItems->sum(function ($item) {
            $quantity = $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;
            $discountAmount = $item->discount_amount ?? 0;
            $discountPercentage = $item->discount_percentage ?? 0;
            
            $itemSubtotal = $quantity * $unitPrice;
            
            // Apply discount: percentage takes precedence over amount
            if ($discountPercentage > 0) {
                return $itemSubtotal * ($discountPercentage / 100);
            }
            
            return $discountAmount;
        });
        
        // Subtotal after discounts (sum of line item totals)
        $subtotal = $this->lineItems->sum('total');
        
        // Calculate tax on discounted amount and round UP to 2 decimal places
        $taxAmount = ceil(($subtotal * ($this->tax_rate / 100)) * 100) / 100;
        $total = $subtotal + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $totalDiscount,
            'discount_percentage' => 0, // Clear percentage since we're using amount from line items
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Check if the invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->status !== 'cancelled' && $this->due_date < now();
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'paid' => 'green',
            'overdue' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get the days until due date.
     */
    public function getDaysUntilDueAttribute(): int
    {
        return now()->diffInDays($this->due_date, false);
    }

    /**
     * Scope to filter by company.
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter overdue invoices.
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->where('due_date', '<', now());
    }

    /**
     * Get the total amount paid for this invoice.
     */
    public function getTotalPaidAttribute(): float
    {
        // Use loaded relationship if available, otherwise query
        if ($this->relationLoaded('payments')) {
            return $this->payments->sum('amount');
        }
        return $this->payments()->sum('amount');
    }

    /**
     * Get the remaining balance for this invoice.
     */
    public function getRemainingBalanceAttribute(): float
    {
        // Calculate total paid directly to avoid accessor recursion issues
        $totalPaid = 0;
        if ($this->relationLoaded('payments')) {
            $totalPaid = $this->payments->sum('amount');
        } else {
            $totalPaid = $this->payments()->sum('amount');
        }
        
        $total = (float) ($this->total ?? 0);
        return max(0, $total - $totalPaid);
    }

    /**
     * Check if the invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        $totalPaid = $this->payments()->sum('amount');
        return ($this->total - $totalPaid) <= 0;
    }
}