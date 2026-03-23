<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

class Invoice extends Model
{
    use Auditable, HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'invoice_number',
        'order_number',
        'title',
        'description',
        'customer_id',
        'contact_id',
        'email',
        'phone',
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
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    protected $appends = [
        'job_number',
        'recipient_email',
        'recipient_phone',
        'total_paid',
        'total_credited',
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
     * Get the contact linked to the invoice.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
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

    public function lineGroups(): MorphMany
    {
        return $this->morphMany(LineGroup::class, 'line_groupable')->orderBy('sort_order');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable')->orderByDesc('signed_at');
    }

    /**
     * Get the payments for this invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('payment_date', 'desc');
    }

    /**
     * Get the credit notes for this invoice.
     */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class)->orderBy('credit_note_date', 'desc');
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
    public static function generateInvoiceNumber(?int $companyId = null): string
    {
        if ($companyId) {
            return DB::transaction(function () use ($companyId) {
                $company = Company::whereKey($companyId)->lockForUpdate()->first();
                if ($company && $company->invoice_number_prefix !== null && $company->invoice_number_next !== null) {
                    $next = max(1, (int) $company->invoice_number_next);
                    $number = $company->invoice_number_prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                    $company->invoice_number_next = $next + 1;
                    $company->save();

                    return $number;
                }

                return self::generateLegacyInvoiceNumber($companyId);
            });
        }

        return self::generateLegacyInvoiceNumber($companyId);
    }

    private static function generateLegacyInvoiceNumber(?int $companyId = null): string
    {
        $year = date('Y');
        $month = date('m');

        // Get the last invoice number for this year/month
        $lastInvoiceQuery = static::where('invoice_number', 'like', "INV-{$year}{$month}%");
        if ($companyId !== null) {
            $lastInvoiceQuery->where('company_id', $companyId);
        }

        $lastInvoice = $lastInvoiceQuery
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            // Extract the sequence number and increment it
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return "INV-{$year}{$month}".str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals for the invoice.
     */
    public function calculateTotals(): void
    {
        $lineItems = $this->lineItems;

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
        $subtotal = $lineItems->sum('total');

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
     * Heading for PDFs and print: "Tax Invoice" when total tax is positive, otherwise "Invoice"
     * (often styled with text-transform: uppercase → INVOICE).
     */
    public function getPdfDocumentTitle(): string
    {
        $tax = (float) ($this->tax_amount ?? 0);

        return $tax > 0.00001 ? 'Tax Invoice' : 'Invoice';
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

    public function getJobNumberAttribute(): ?string
    {
        if ($this->source_type === 'jobcard') {
            if ($this->relationLoaded('source')) {
                return $this->source?->job_number;
            }

            /** @var \App\Models\Jobcard|null $jobcard */
            $jobcard = $this->source()->first();

            return $jobcard?->job_number;
        }

        if ($this->source_type !== 'quote') {
            return null;
        }

        /** @var \App\Models\Quote|null $quote */
        $quote = null;
        if ($this->relationLoaded('source') && $this->source instanceof Quote) {
            $quote = $this->source;
        } elseif (! empty($this->source_id)) {
            $quote = Quote::with('source')->find($this->source_id);
        }

        if (! $quote || $quote->source_type !== 'jobcard') {
            return null;
        }

        if ($quote->relationLoaded('source')) {
            return $quote->source?->job_number;
        }

        return Jobcard::find($quote->source_id)?->job_number;
    }

    public function getRecipientEmailAttribute(): ?string
    {
        return $this->email ?: $this->contact?->email ?: $this->customer?->email;
    }

    public function getRecipientPhoneAttribute(): ?string
    {
        return $this->phone ?: $this->contact?->phone ?: $this->customer?->phone;
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
        if ($this->relationLoaded('payments')) {
            return $this->payments->sum('amount');
        }

        return $this->payments()->sum('amount');
    }

    /**
     * Get the total credit notes applied to this invoice.
     */
    public function getTotalCreditedAttribute(): float
    {
        if ($this->relationLoaded('creditNotes')) {
            return (float) $this->creditNotes
                ->where('status', '!=', 'voided')
                ->sum(fn ($cn) => (float) $cn->total);
        }

        return (float) $this->creditNotes()
            ->where('status', '!=', 'voided')
            ->sum('total');
    }

    /**
     * Get the remaining balance for this invoice (total minus payments minus credit notes).
     */
    public function getRemainingBalanceAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            $totalPaid = (float) $this->payments->sum(fn ($p) => (float) $p->amount);
        } else {
            $totalPaid = (float) $this->payments()->sum('amount');
        }

        if ($this->relationLoaded('creditNotes')) {
            $totalCredited = (float) $this->creditNotes
                ->where('status', '!=', 'voided')
                ->sum(fn ($cn) => (float) $cn->total);
        } else {
            $totalCredited = (float) $this->creditNotes()
                ->where('status', '!=', 'voided')
                ->sum('total');
        }

        $total = (float) ($this->total ?? 0);

        return max(0, round($total - $totalPaid - $totalCredited, 2));
    }

    /**
     * Check if the invoice is fully paid (including credit notes).
     */
    public function isFullyPaid(): bool
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $totalCredited = (float) $this->creditNotes()
            ->where('status', '!=', 'voided')
            ->sum('total');

        return ((float) $this->total - $totalPaid - $totalCredited) <= 0.01;
    }
}
