<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class DeliveryNote extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'jobcard_id',
        'customer_id',
        'contact_id',
        'user_id',
        'delivery_note_number',
        'delivery_date',
        'delivery_address',
        'status',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobcard(): BelongsTo
    {
        return $this->belongsTo(Jobcard::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(DeliveryNoteLineItem::class)->orderBy('sort_order');
    }

    public function lineGroups(): MorphMany
    {
        return $this->morphMany(LineGroup::class, 'line_groupable')->orderBy('sort_order');
    }

    public static function generateDeliveryNoteNumber(int $companyId): string
    {
        $customNumber = DB::transaction(function () use ($companyId) {
            $company = Company::whereKey($companyId)->lockForUpdate()->first();
            if ($company && $company->delivery_note_number_prefix !== null && $company->delivery_note_number_next !== null) {
                $next = max(1, (int) $company->delivery_note_number_next);
                $number = $company->delivery_note_number_prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                $company->delivery_note_number_next = $next + 1;
                $company->save();

                return $number;
            }

            return null;
        });

        if ($customNumber !== null) {
            return $customNumber;
        }

        $year = date('Y');
        $lastNote = static::where('company_id', $companyId)
            ->whereYear('created_at', $year)
            ->orderByDesc('id')
            ->first();

        $sequence = $lastNote ? ((int) substr($lastNote->delivery_note_number, -4)) + 1 : 1;

        return 'DN-'.$year.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
