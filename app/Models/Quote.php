<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Quote extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'company_id',
        'customer_id',
        'email',
        'phone',
        'invoice_id',
        'quote_number',
        'order_number',
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
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    protected $appends = [
        'recipient_email',
        'recipient_phone',
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
    public static function generateQuoteNumber(int $companyId): string
    {
        $customNumber = DB::transaction(function () use ($companyId) {
            $company = Company::whereKey($companyId)->lockForUpdate()->first();
            if ($company && $company->quote_number_prefix !== null && $company->quote_number_next !== null) {
                $next = max(1, (int) $company->quote_number_next);
                $number = $company->quote_number_prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                $company->quote_number_next = $next + 1;
                $company->save();

                return $number;
            }

            return null;
        });

        if ($customNumber !== null) {
            return $customNumber;
        }

        $prefix = 'QT';
        $year = date('Y');
        $month = date('m');
        
        // Get the last quote number for this year/month
        $lastQuote = static::where('company_id', $companyId)
            ->where('quote_number', 'like', "{$prefix}{$year}{$month}%")
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
        $lineItems = $this->lineItems()->get();

        // Calculate total discount from line items
        $totalDiscount = $lineItems->sum(function ($item) {
            $quantity = $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;
            $discountAmount = $item->discount_amount ?? 0;
            $discountPercentage = $item->discount_percentage ?? 0;
            
            $itemSubtotal = $quantity * $unitPrice;
            
            if ($discountPercentage > 0) {
                return $itemSubtotal * ($discountPercentage / 100);
            }
            
            return $discountAmount;
        });
        
        // Subtotal after discounts (sum of line item totals)
        $subtotal = $lineItems->sum('total') ?? 0;
        
        // Tax is now calculated per line item - sum all line item tax amounts
        $taxAmount = $lineItems->sum('tax_amount') ?? 0;
        $total = $subtotal + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $totalDiscount,
            'discount_percentage' => 0,
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

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->email ?: $this->customer?->email;
    }

    public function getRecipientPhoneAttribute(): ?string
    {
        return $this->phone ?: $this->customer?->phone;
    }

    /**
     * Convert quote to jobcard
     */
    public function convertToJobcard(): Jobcard
    {
        $jobcard = Jobcard::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_number' => Jobcard::generateJobNumber($this->company_id),
            'order_number' => $this->order_number,
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
                'tax_rate_id' => $lineItem->tax_rate_id,
                'tax_amount' => $lineItem->tax_amount,
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
            'email' => $this->email,
            'phone' => $this->phone,
            'invoice_number' => Invoice::generateInvoiceNumber($this->company_id),
            'order_number' => $this->order_number,
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
                'tax_rate_id' => $lineItem->tax_rate_id,
                'tax_amount' => $lineItem->tax_amount,
                'sort_order' => $lineItem->sort_order,
            ]);
        }

        return $invoice;
    }
}
