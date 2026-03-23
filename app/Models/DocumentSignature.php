<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentSignature extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'signer_name',
        'signature_path',
        'signed_at',
    ];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    protected $appends = [
        'signature_url',
    ];

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        return asset('storage/'.$this->signature_path);
    }

    public function getSignaturePathForPdf(): ?string
    {
        if (! $this->signature_path) {
            return null;
        }

        $fullPath = storage_path('app/public/'.$this->signature_path);
        if (! file_exists($fullPath)) {
            return null;
        }

        $imageData = file_get_contents($fullPath);
        $imageInfo = getimagesize($fullPath);
        $mimeType = $imageInfo['mime'] ?? 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($imageData);
    }
}
