<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'quote_number',
        'xero_quote_id',
        'title',
        'description',
        'status',
        'expiry_date',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'formatted_total',
        'status_color',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Generate a unique quote number
     */
    public static function generateQuoteNumber(): string
    {
        $prefix = 'QT';
        $year = date('Y');
        $month = date('m');
        
        // Get the last quote number for this year/month
        $lastQuote = static::where('quote_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('quote_number', 'desc')
            ->first();
        
        if ($lastQuote && $lastQuote->quote_number) {
            $lastNumber = (int) substr($lastQuote->quote_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . $month . str_pad((string)$newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals from line items
     */
    public function calculateTotals(): void
    {
        // Calculate subtotal before discounts (sum of quantity * unit_price)
        $subtotalBeforeDiscount = $this->lineItems()->get()->sum(function ($item) {
            return ($item->quantity ?? 0) * ($item->unit_price ?? 0);
        });
        
        // Calculate total discount from line items
        $totalDiscount = $this->lineItems()->get()->sum(function ($item) {
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
        $subtotal = $this->lineItems()->sum('total') ?? 0;
        
        // Calculate tax on discounted amount and round UP to 2 decimal places
        $taxRate = $this->tax_rate ?? 0;
        $taxAmount = ceil(($subtotal * ($taxRate / 100)) * 100) / 100;
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
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'R' . number_format($this->total ?? 0, 2);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'accepted' => 'green',
            'rejected' => 'red',
            'expired' => 'yellow',
            default => 'gray',
        };
    }

    /**
     * Convert quote to jobcard
     */
    public function convertToJobcard(): Jobcard
    {
        $jobcard = Jobcard::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'job_number' => Jobcard::generateJobNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'draft',
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
        ]);

        // Copy line items
        foreach ($this->lineItems as $lineItem) {
            JobcardLineItem::create([
                'jobcard_id' => $jobcard->id,
                'product_id' => $lineItem->product_id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'total' => $lineItem->total,
                'sort_order' => $lineItem->sort_order,
            ]);
        }

        return $jobcard;
    }

    /**
     * Convert quote to invoice
     */
    public function convertToInvoice(): Invoice
    {
        // Ensure line items are loaded
        $this->load('lineItems');
        
        $invoice = Invoice::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'discount_percentage' => $this->discount_percentage,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms_conditions,
            'source_type' => 'quote',
            'source_id' => $this->id,
        ]);

        // Copy line items
        foreach ($this->lineItems as $lineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItem->product_id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'total' => $lineItem->total,
                'sort_order' => $lineItem->sort_order,
            ]);
        }

        return $invoice;
    }
}
