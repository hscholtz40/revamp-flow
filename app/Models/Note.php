<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Note extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'subject',
        'description',
        'attachment_path',
        'attachment_original_name',
        'attachment_mime',
        'attachment_size',
    ];

    protected $appends = [
        'attachment_url',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return route('notes.download', $this);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $note): void {
            if ($note->attachment_path && Storage::disk('public')->exists($note->attachment_path)) {
                Storage::disk('public')->delete($note->attachment_path);
            }
        });
    }
}
