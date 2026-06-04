<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Query extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id',
        'name',
        'surname',
        'email',
        'cell',
        'description',
        'status',
    ];

    protected static function booted(): void
    {
        // Remove stored attachment files when a query is deleted.
        static::deleting(function (Query $query) {
            foreach ($query->attachments as $attachment) {
                if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                    Storage::disk('public')->delete($attachment->path);
                }
            }

            $query->attachments()->delete();
        });
    }

    /**
     * The company this query was submitted to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The files attached to this query.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(QueryAttachment::class);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
