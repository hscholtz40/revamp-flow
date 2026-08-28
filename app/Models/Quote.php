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

class Quote extends Model
{
    use Auditable, HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'customer_id',
        'salesperson_id',
        'contact_id',
        'email',
        'phone',
        'invoice_id',
        'converted_jobcard_id',
        'source_type',
        'source_id',
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

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(QuoteLineItem::class)->orderBy('sort_order');
    }

    public function lineGroups(): MorphMany
    {
        return $this->morphMany(LineGroup::class, 'line_groupable')->orderBy('sort_order');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable')->orderByDesc('signed_at');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function convertedJobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class, 'converted_jobcard_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'source_id')->where('source_type', 'quote');
    }

    public function jobcards(): HasMany
    {
        return $this->hasMany(Jobcard::class, 'source_id')
            ->whereIn('source_type', ['quote', self::class]);
    }

    /**
     * Resolve the jobcard linked to this quote, including quote-to-jobcard conversions.
     */
    public function findLinkedJobcard(?int $companyId = null, bool $repairLink = false): ?Jobcard
    {
        $companyId ??= (int) $this->company_id;

        if ($this->converted_jobcard_id) {
            $convertedJobcard = Jobcard::query()
                ->where('company_id', $companyId)
                ->find($this->converted_jobcard_id);

            if ($convertedJobcard !== null) {
                return $convertedJobcard;
            }
        }

        $jobcard = $this->jobcards()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->first();

        if ($jobcard !== null) {
            if ($repairLink) {
                $this->markConvertedToJobcard($jobcard);
            }

            return $jobcard->fresh();
        }

        $jobcard = Jobcard::query()
            ->where('company_id', $companyId)
            ->where('source_id', $this->id)
            ->orderByDesc('id')
            ->first();

        if ($jobcard !== null) {
            if ($repairLink) {
                $this->markConvertedToJobcard($jobcard);
            }

            return $jobcard->fresh();
        }

        if ($this->source_type === 'jobcard' && $this->source_id) {
            return Jobcard::query()
                ->where('company_id', $companyId)
                ->find($this->source_id);
        }

        return null;
    }

    /**
     * @return array{id: int, job_number: string, title: string}|null
     */
    public function linkedJobcardPayload(?int $companyId = null, bool $repairLink = true): ?array
    {
        $jobcard = $this->findLinkedJobcard($companyId, $repairLink);

        if ($jobcard === null) {
            return null;
        }

        return [
            'id' => $jobcard->id,
            'job_number' => $jobcard->job_number,
            'title' => $jobcard->title,
        ];
    }

    public function markConvertedToJobcard(Jobcard $jobcard): void
    {
        if ((int) $jobcard->company_id !== (int) $this->company_id) {
            return;
        }

        if (
            $jobcard->source_type !== 'quote'
            || (int) $jobcard->source_id !== (int) $this->id
        ) {
            $jobcard->update([
                'source_type' => 'quote',
                'source_id' => $this->id,
            ]);
        }

        if ((int) $this->converted_jobcard_id !== (int) $jobcard->id) {
            $this->update(['converted_jobcard_id' => $jobcard->id]);
        }
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
                $number = $company->quote_number_prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
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

        return $prefix.$year.$month.str_pad((string) $newNumber, 4, '0', STR_PAD_LEFT);
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
        return 'R'.number_format($this->total ?? 0, 2);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
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
        return $this->email ?: $this->contact?->email ?: $this->customer?->email;
    }

    public function getRecipientPhoneAttribute(): ?string
    {
        return $this->phone ?: $this->contact?->phone ?: $this->customer?->phone;
    }

    /**
     * Convert quote to jobcard
     */
    public function convertToJobcard(): Jobcard
    {
        $jobcard = Jobcard::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'contact_id' => $this->contact_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'source_type' => 'quote',
            'source_id' => $this->id,
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

        $this->load('lineItems.lineGroup', 'lineGroups');
        $groupMap = [];
        foreach ($this->lineGroups as $group) {
            $newGroup = LineGroup::create([
                'line_groupable_type' => Jobcard::class,
                'line_groupable_id' => $jobcard->id,
                'name' => $group->name,
                'sort_order' => $group->sort_order,
            ]);
            $groupMap[$group->id] = $newGroup->id;
        }
        if (empty($groupMap)) {
            $defaultGroup = LineGroup::createDefaultFor($jobcard);
            $defaultGroupId = $defaultGroup->id;
        } else {
            $defaultGroupId = $groupMap[$this->lineGroups->first()?->id] ?? reset($groupMap);
        }

        foreach ($this->lineItems as $lineItem) {
            $groupId = ($lineItem->line_group_id && isset($groupMap[$lineItem->line_group_id]))
                ? $groupMap[$lineItem->line_group_id]
                : $defaultGroupId;
            JobcardLineItem::create([
                'jobcard_id' => $jobcard->id,
                'line_group_id' => $groupId,
                'product_id' => $lineItem->product_id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'cost' => $lineItem->cost ?? 0,
                'supplier_id' => $lineItem->supplier_id,
                'discount_amount' => $lineItem->discount_amount ?? 0,
                'discount_percentage' => $lineItem->discount_percentage ?? 0,
                'total' => $lineItem->total,
                'tax_rate_id' => $lineItem->tax_rate_id,
                'tax_amount' => $lineItem->tax_amount,
                'account_id' => $lineItem->account_id,
                'sort_order' => $lineItem->sort_order,
            ]);
        }

        $this->markConvertedToJobcard($jobcard);
        $this->update(['status' => 'accepted']);

        return $jobcard;
    }

    /**
     * Convert quote to invoice
     */
    public function convertToInvoice(): Invoice
    {
        // Ensure line items are loaded
        $this->load('lineItems');

        $this->loadMissing('customer');
        $paymentTerms = trim((string) ($this->customer?->terms ?? 'COD')) ?: 'COD';

        $invoice = Invoice::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'contact_id' => $this->contact_id,
            'email' => $this->email,
            'phone' => $this->phone,
            'salesperson_id' => $this->salesperson_id ?? auth()->id(),
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
            'terms' => $paymentTerms,
            'terms_conditions' => $this->terms_conditions,
            'source_type' => 'quote',
            'source_id' => $this->id,
        ]);

        $this->load('lineItems.lineGroup', 'lineGroups');
        $groupMap = [];
        foreach ($this->lineGroups as $group) {
            $newGroup = LineGroup::create([
                'line_groupable_type' => Invoice::class,
                'line_groupable_id' => $invoice->id,
                'name' => $group->name,
                'sort_order' => $group->sort_order,
            ]);
            $groupMap[$group->id] = $newGroup->id;
        }
        if (empty($groupMap)) {
            $defaultGroup = LineGroup::createDefaultFor($invoice);
            $defaultGroupId = $defaultGroup->id;
        } else {
            $defaultGroupId = $groupMap[$this->lineGroups->first()?->id] ?? reset($groupMap);
        }

        foreach ($this->lineItems as $lineItem) {
            $groupId = ($lineItem->line_group_id && isset($groupMap[$lineItem->line_group_id]))
                ? $groupMap[$lineItem->line_group_id]
                : $defaultGroupId;
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'line_group_id' => $groupId,
                'product_id' => $lineItem->product_id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'cost' => $lineItem->cost ?? 0,
                'discount_amount' => $lineItem->discount_amount ?? 0,
                'discount_percentage' => $lineItem->discount_percentage ?? 0,
                'total' => $lineItem->total,
                'tax_rate_id' => $lineItem->tax_rate_id,
                'tax_amount' => $lineItem->tax_amount,
                'account_id' => $lineItem->account_id,
                'sort_order' => $lineItem->sort_order,
            ]);
        }

        $this->update(['status' => 'accepted']);

        return $invoice;
    }
}
