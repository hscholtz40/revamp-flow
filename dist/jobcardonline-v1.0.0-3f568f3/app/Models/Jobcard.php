<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jobcard extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'job_number',
        'title',
        'description',
        'status',
        'start_date',
        'due_date',
        'completed_date',
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
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_date' => 'date',
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
        return $this->hasMany(JobcardLineItem::class)->orderBy('sort_order');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Generate a unique job number
     */
    public static function generateJobNumber(): string
    {
        $prefix = 'JC';
        $year = date('Y');
        $month = date('m');
        
        // Get the last job number for this year/month
        $lastJob = static::where('job_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('job_number', 'desc')
            ->first();
        
        if ($lastJob && $lastJob->job_number) {
            $lastNumber = (int) substr($lastJob->job_number, -4);
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
        $subtotal = $this->lineItems()->sum('total') ?? 0;
        
        // Calculate discount
        $discountAmount = $this->discount_amount ?? 0;
        $discountPercentage = $this->discount_percentage ?? 0;
        
        // Apply percentage discount if specified
        if ($discountPercentage > 0) {
            $discountAmount = $subtotal * ($discountPercentage / 100);
        }
        
        // Calculate subtotal after discount
        $subtotalAfterDiscount = $subtotal - $discountAmount;
        
        // Calculate tax on discounted amount
        $taxRate = $this->tax_rate ?? 0;
        $taxAmount = $subtotalAfterDiscount * ($taxRate / 100);
        $total = $subtotalAfterDiscount + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
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
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Convert jobcard to invoice
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
            'source_type' => 'jobcard',
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
