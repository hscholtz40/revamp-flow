<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'xero_credit_note_id',
        'credit_note_number',
        'title',
        'description',
        'status',
        'credit_note_date',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'tax_rate',
        'tax_amount',
        'total',
        'remaining_credit',
        'notes',
        'reference',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'remaining_credit' => 'decimal:2',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    protected $appends = ['status_color', 'formatted_total'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(CreditNoteLineItem::class)->orderBy('sort_order');
    }

    public static function generateCreditNoteNumber(int $companyId): string
    {
        $customNumber = DB::transaction(function () use ($companyId) {
            $company = Company::whereKey($companyId)->lockForUpdate()->first();
            if ($company && $company->credit_note_number_prefix !== null && $company->credit_note_number_next !== null) {
                $next = max(1, (int) $company->credit_note_number_next);
                $number = $company->credit_note_number_prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                $company->credit_note_number_next = $next + 1;
                $company->save();

                return $number;
            }

            return null;
        });

        if ($customNumber !== null) {
            return $customNumber;
        }

        $prefix = 'CN-' . date('Ym');
        $lastCN = static::where('credit_note_number', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(credit_note_number, ' . (strlen($prefix) + 1) . ') AS UNSIGNED) DESC')
            ->first();

        $sequence = 1;
        if ($lastCN) {
            $lastSequence = (int) substr($lastCN->credit_note_number, strlen($prefix));
            $sequence = $lastSequence + 1;
        }

        return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $items = $this->lineItems()->get();
        $subtotal = $items->sum('total');
        $taxAmount = $items->sum('tax_amount');
        $discountAmount = $items->sum('discount_amount');

        $this->subtotal = $subtotal;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        $this->total = $subtotal + $taxAmount;
        $this->save();
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'submitted' => 'blue',
            'authorised' => 'green',
            'paid' => 'purple',
            'voided' => 'red',
            default => 'gray',
        };
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format((float) $this->total, 2);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
