<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNoteAllocation extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'credit_note_id',
        'invoice_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

