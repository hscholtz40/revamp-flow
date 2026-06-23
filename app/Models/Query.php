<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Query extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const KIND_ENQUIRY = 'enquiry';

    public const KIND_JOB = 'job';

    public const RESPONSE_PENDING = 'pending';

    public const RESPONSE_ACCEPTED = 'accepted';

    public const RESPONSE_DECLINED = 'declined';

    // The job was claimed by another contractor first; this copy is locked out.
    public const RESPONSE_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'kind',
        'external_source',
        'external_quote_id',
        'contractor_company_key',
        'name',
        'surname',
        'email',
        'cell',
        'description',
        'status',
        'response',
        'responded_at',
        'job_location',
        'job_latitude',
        'job_longitude',
        'quote_line_items',
        'quote_total_amount',
        'quote_client_email',
        'quote_client_phone',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'job_latitude' => 'decimal:7',
        'job_longitude' => 'decimal:7',
        'quote_line_items' => 'array',
        'quote_total_amount' => 'decimal:2',
    ];

    /**
     * Scope to contractor "job" queries (dispatched from an external quote).
     */
    public function scopeJobs($query)
    {
        return $query->where('kind', self::KIND_JOB);
    }

    /**
     * Whether this query is a contractor job (dispatched from an external quote)
     * rather than a public enquiry.
     */
    public function isJob(): bool
    {
        return $this->kind === self::KIND_JOB;
    }

    /**
     * Whether this is a job still awaiting the contractor's accept/decline.
     */
    public function isPendingJob(): bool
    {
        return $this->isJob() && $this->response === self::RESPONSE_PENDING;
    }

    protected static function booted(): void
    {
        // Only purge stored files and attachment rows on a permanent (force) delete.
        // A soft delete keeps everything so the query can be restored intact.
        static::deleting(function (Query $query) {
            if (! $query->isForceDeleting()) {
                return;
            }

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
