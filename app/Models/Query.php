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

    public const KIND_CONTRACTOR = 'contractor';

    public const RESPONSE_PENDING = 'pending';

    public const RESPONSE_ACCEPTED = 'accepted';

    public const RESPONSE_DECLINED = 'declined';

    public const RESPONSE_EXPIRED = 'expired';

    public const WEBSITE_STATUS_HAVE = 'have_website';

    public const WEBSITE_STATUS_NEED = 'need_website';

    public const PACKAGE_OPTION_1 = 'option_1';

    public const PACKAGE_OPTION_2 = 'option_2';

    public const PACKAGE_CUSTOM = 'custom';

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
        'company_name',
        'company_registration_no',
        'company_address',
        'company_city',
        'company_province',
        'company_email',
        'company_contact_number',
        'company_website',
        'website_status',
        'selected_package',
        'status',
        'response',
        'responded_at',
        'accepted_at',
        'accepted_customer_id',
        'accepted_contact_id',
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
        'accepted_at' => 'datetime',
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

    public function isContractorQuery(): bool
    {
        return $this->kind === self::KIND_CONTRACTOR;
    }

    public static function packageLabels(): array
    {
        return [
            self::PACKAGE_OPTION_1 => 'Option 1 - R 550 pm incl VAT (60 day trial)',
            self::PACKAGE_OPTION_2 => 'Option 2 - R 850 pm incl VAT (60 day trial)',
            self::PACKAGE_CUSTOM => 'Custom package - request contact',
        ];
    }

    public function selectedPackageLabel(): ?string
    {
        if (! $this->selected_package) {
            return null;
        }

        return self::packageLabels()[$this->selected_package] ?? $this->selected_package;
    }

    public function websiteStatusLabel(): ?string
    {
        return match ($this->website_status) {
            self::WEBSITE_STATUS_HAVE => 'I have a website',
            self::WEBSITE_STATUS_NEED => 'I need a website',
            default => null,
        };
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
