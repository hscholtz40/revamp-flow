<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    public const DEFAULT_JOBCARD_STATUS_LABELS = [
        'draft' => 'Draft',
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const DEFAULT_QUOTE_STATUS_LABELS = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
        'expired' => 'Expired',
    ];

    /**
     * Restrict route binding to companies the authenticated user may access.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return static|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();
        $company = static::query()->where($field, $value)->first();
        if ($company === null) {
            return null;
        }

        $user = auth()->user();
        if ($user === null || ! $user->hasAccessToCompany($company->id)) {
            return null;
        }

        return $company;
    }

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'vat_number',
        'website',
        'logo_path',
        'description',
        'invoice_footer',
        'jobcard_footer',
        'quote_footer',
        'default_invoice_terms',
        'default_quote_terms',
        'default_jobcard_terms',
        'is_active',
        'is_default',
        'enable_pos',
        'enable_document_signing',
        'whatsapp_business_number',
        'visible_modules',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_sort_code',
        'invoice_number_prefix',
        'invoice_number_next',
        'quote_number_prefix',
        'quote_number_next',
        'jobcard_number_prefix',
        'jobcard_number_next',
        'credit_note_number_prefix',
        'credit_note_number_next',
        'purchase_order_number_prefix',
        'purchase_order_number_next',
        'locale_decimal_separator',
        'locale_thousands_separator',
        'locale_timezone',
        'locale_date_format',
        'locale_time_format',
        'jobcard_status_labels',
        'quote_status_labels',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'smtp_password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'enable_pos' => 'boolean',
        'enable_document_signing' => 'boolean',
        'visible_modules' => 'array',
        'invoice_number_next' => 'integer',
        'quote_number_next' => 'integer',
        'jobcard_number_next' => 'integer',
        'credit_note_number_next' => 'integer',
        'purchase_order_number_next' => 'integer',
        'smtp_password' => 'encrypted',
        'jobcard_status_labels' => 'array',
        'quote_status_labels' => 'array',
    ];

    /**
     * Format a numeric amount using this company's decimal and thousands separators (no currency symbol).
     */
    public function formatNumber(float|int|string|null $amount, int $decimals = 2): string
    {
        $num = (float) $amount;
        $dec = ($this->locale_decimal_separator !== null && $this->locale_decimal_separator !== '')
            ? $this->locale_decimal_separator
            : '.';
        $thou = ($this->locale_thousands_separator !== null && $this->locale_thousands_separator !== '')
            ? $this->locale_thousands_separator
            : ',';

        return number_format($num, $decimals, $dec, $thou);
    }

    /**
     * Format a ZAR amount with the R prefix and company number separators.
     */
    public function formatCurrencyZar(float|int|string|null $amount, int $decimals = 2): string
    {
        return 'R'.$this->formatNumber($amount, $decimals);
    }

    public function getLocalizedPhpDateFormat(): string
    {
        return match ($this->locale_date_format) {
            'mm/dd/yyyy' => 'm/d/Y',
            'yyyy-mm-dd' => 'Y-m-d',
            'd mmm yyyy' => 'j M Y',
            default => 'd/m/Y',
        };
    }

    public function getLocalizedPhpTimeFormat(): string
    {
        return $this->locale_time_format === '12h' ? 'h:i A' : 'H:i';
    }

    public function formatLocalizedDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->toLocalizedCarbon($value)->format($this->getLocalizedPhpDateFormat());
    }

    public function formatLocalizedTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->toLocalizedCarbon($value)->format($this->getLocalizedPhpTimeFormat());
    }

    public function formatLocalizedDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $this->toLocalizedCarbon($value)->format(
            $this->getLocalizedPhpDateFormat().' '.$this->getLocalizedPhpTimeFormat()
        );
    }

    private function toLocalizedCarbon(mixed $value): Carbon
    {
        $timezone = is_string($this->locale_timezone) && $this->locale_timezone !== ''
            ? $this->locale_timezone
            : config('app.timezone', 'UTC');

        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone($timezone);
        }

        return Carbon::parse($value)->setTimezone($timezone);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getJobcardStatusOptions(): array
    {
        return $this->buildStatusOptions(
            self::DEFAULT_JOBCARD_STATUS_LABELS,
            is_array($this->jobcard_status_labels) ? $this->jobcard_status_labels : []
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getQuoteStatusOptions(): array
    {
        return $this->buildStatusOptions(
            self::DEFAULT_QUOTE_STATUS_LABELS,
            is_array($this->quote_status_labels) ? $this->quote_status_labels : []
        );
    }

    /**
     * @param  array<string, string>  $defaults
     * @param  array<string, mixed>  $overrides
     * @return array<int, array{value: string, label: string}>
     */
    private function buildStatusOptions(array $defaults, array $overrides): array
    {
        $options = [];
        foreach ($defaults as $value => $label) {
            $override = $overrides[$value] ?? null;
            $resolved = is_string($override) && trim($override) !== '' ? trim($override) : $label;
            $options[] = [
                'value' => $value,
                'label' => $resolved,
            ];
        }

        return $options;
    }

    /**
     * Get the logo URL
     */
    public function getLogoAttribute()
    {
        if ($this->logo_path) {
            return asset('storage/'.$this->logo_path);
        }

        return null;
    }

    /**
     * Get the logo as a base64 data URI for PDF generation.
     * DomPDF cannot fetch images from URLs, so we convert to inline base64.
     */
    public function getLogoPathForPdf()
    {
        if (! $this->logo_path) {
            return null;
        }

        $fullPath = storage_path('app/public/'.$this->logo_path);

        if (! file_exists($fullPath)) {
            return null;
        }

        $imageData = file_get_contents($fullPath);
        $imageInfo = getimagesize($fullPath);
        $mimeType = $imageInfo['mime'] ?? 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($imageData);
    }

    /**
     * Scope to filter active companies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default company
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->first() ?? static::first();
    }

    /**
     * Get the customers for the company.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get the contacts for the company.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    /**
     * Get the products for the company.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the jobcards for the company.
     */
    public function jobcards(): HasMany
    {
        return $this->hasMany(Jobcard::class);
    }

    /**
     * Get the users for the company.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_companies');
    }

    /**
     * Set this company as the default
     */
    public function setAsDefault()
    {
        // Remove default from all other companies
        static::where('is_default', true)->update(['is_default' => false]);

        // Set this company as default
        $this->update(['is_default' => true]);
    }

    /**
     * Get the reminder settings for this company.
     */
    public function reminderSettings()
    {
        return $this->hasOne(ReminderSettings::class);
    }

    /**
     * Get reminder settings, creating if they don't exist.
     */
    public function getReminderSettings(): ReminderSettings
    {
        return ReminderSettings::getForCompany($this->id);
    }

    /**
     * Check if a module is visible for this company.
     * If visible_modules is null, all modules are visible by default.
     */
    public function isModuleVisible(string $moduleKey): bool
    {
        if ($this->visible_modules === null) {
            return true; // All modules visible by default
        }

        return in_array($moduleKey, $this->visible_modules ?? []);
    }

    /**
     * Get visible modules array.
     * Returns null if all modules should be visible, or array of module keys.
     */
    public function getVisibleModules(): ?array
    {
        return $this->visible_modules;
    }

    /**
     * Set visible modules.
     */
    public function setVisibleModules(?array $modules): void
    {
        $this->visible_modules = $modules;
        $this->save();
    }
}
