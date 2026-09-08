<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderAttachment extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'path',
        'type',
        'original_name',
        'description',
        'latitude',
        'longitude',
        'location_accuracy',
        'uploaded_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
    ];

    protected $appends = [
        'has_location',
    ];

    public function getHasLocationAttribute(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
