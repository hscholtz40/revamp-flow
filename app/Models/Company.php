<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    public const DEFAULT_JOBCARD_STATUS_LABELS = [
        'new' => 'New',
        'needs_scheduling' => 'Needs scheduling',
        'scheduled' => 'Scheduled',
        'dispatched' => 'Dispatched',
        'accepted' => 'Accepted',
        'en_route' => 'En route',
        'on_site' => 'On site',
        'paused' => 'Paused',
        'waiting_for_parts' => 'Waiting for parts',
        'needs_follow_up' => 'Needs follow-up',
        'emergency' => 'Emergency',
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
        'favicon_path',
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
        'enable_dispatch',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_email',
        'smtp_from_name',
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
        'delivery_note_number_prefix',
        'delivery_note_number_next',
        'locale_decimal_separator',
        'locale_thousands_separator',
        'locale_timezone',
        'locale_date_format',
        'locale_time_format',
        'jobcard_status_labels',
        'quote_status_labels',
        // Theme colors
        'theme_primary_hue',
        'theme_primary_saturation',
        'theme_primary_lightness',
        'theme_primary_dark_mode_lightness',
        'theme_secondary_hue',
        'theme_secondary_saturation',
        'theme_secondary_lightness',
        'theme_secondary_dark_mode_lightness',
        'theme_accent_hue',
        'theme_accent_saturation',
        'theme_accent_lightness',
        'theme_accent_dark_mode_lightness',
        'theme_chart_1_hue',
        'theme_chart_1_saturation',
        'theme_chart_1_lightness',
        'theme_chart_2_hue',
        'theme_chart_2_saturation',
        'theme_chart_2_lightness',
        'theme_chart_3_hue',
        'theme_chart_3_saturation',
        'theme_chart_3_lightness',
        'theme_chart_4_hue',
        'theme_chart_4_saturation',
        'theme_chart_4_lightness',
        'theme_chart_5_hue',
        'theme_chart_5_saturation',
        'theme_chart_5_lightness',
        'theme_sidebar_primary_hue',
        'theme_sidebar_primary_saturation',
        'theme_sidebar_primary_lightness',
        'theme_sidebar_primary_dark_mode_lightness',
        'theme_sidebar_accent_hue',
        'theme_sidebar_accent_saturation',
        'theme_sidebar_accent_lightness',
        'theme_background_light_mode_lightness',
        'theme_background_dark_mode_lightness',
        'theme_layout_sidebar_width',
        'theme_layout_sidebar_collapsed_width',
        'theme_layout_header_height',
        'theme_layout_border_radius',
        'theme_layout_spacing_unit',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'enable_pos' => 'boolean',
        'enable_document_signing' => 'boolean',
        'enable_dispatch' => 'boolean',
        'visible_modules' => 'array',
        'invoice_number_next' => 'integer',
        'quote_number_next' => 'integer',
        'jobcard_number_next' => 'integer',
        'credit_note_number_next' => 'integer',
        'purchase_order_number_next' => 'integer',
        'delivery_note_number_next' => 'integer',
        'smtp_password' => 'encrypted',
        'jobcard_status_labels' => 'array',
        'quote_status_labels' => 'array',
    ];

    /**
     * Get the logo URL (accessor)
     */
    public function getLogoAttribute()
    {
        if ($this->logo_path) {
            return '/storage/' . $this->logo_path;
        }
        return null;
    }

    public function getEmailLogoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return url('/storage/'.ltrim($this->logo_path, '/'));
    }

    /**
     * Get the favicon URL (accessor)
     */
    public function getFaviconAttribute()
    {
        if ($this->favicon_path) {
            return '/storage/' . $this->favicon_path;
        }
        return null;
    }

    /**
     * Embedded logo for PDF rendering (base64 data URI from companies.logo_path).
     */
    public function getLogoPathForPdf(): ?string
    {
        $filePath = $this->resolvePublicLogoFilePath();

        if ($filePath === null) {
            return null;
        }

        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false) {
            return null;
        }

        $imageData = file_get_contents($filePath);
        if ($imageData === false) {
            return null;
        }

        $mimeType = $imageInfo['mime'] ?? 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($imageData);
    }

    public function getEmailBranding(): array
    {
        return [
            'primary' => $this->getThemeColorHex('primary'),
            'secondary' => $this->getThemeColorHex('secondary'),
            'accent' => $this->getThemeColorHex('accent'),
            'text' => '#4b5563',
            'heading' => '#374151',
            'muted' => '#6b7280',
            'surface' => '#f9fafb',
            'border' => '#e5e7eb',
            'logo_url' => $this->getEmailLogoUrl(),
            'logo_path' => $this->resolvePublicLogoFilePath(),
        ];
    }

    public function getThemeColorHex(string $family): string
    {
        $defaults = [
            'primary' => ['hue' => 215, 'saturation' => 59, 'lightness' => 41],
            'secondary' => ['hue' => 178, 'saturation' => 62, 'lightness' => 46],
            'accent' => ['hue' => 215, 'saturation' => 48, 'lightness' => 48],
        ];

        $default = $defaults[$family] ?? $defaults['primary'];
        $prefix = 'theme_'.$family.'_';

        return $this->hslToHex(
            (int) ($this->{$prefix.'hue'} ?? $default['hue']),
            (int) ($this->{$prefix.'saturation'} ?? $default['saturation']),
            (int) ($this->{$prefix.'lightness'} ?? $default['lightness']),
        );
    }

    private function hslToHex(int $hue, int $saturation, int $lightness): string
    {
        $h = max(0, min(360, $hue));
        $s = max(0, min(100, $saturation)) / 100;
        $l = max(0, min(100, $lightness)) / 100;
        $a = $s * min($l, 1 - $l);

        $toHex = function (int $n) use ($h, $l, $a): string {
            $k = fmod($n + ($h / 30), 12);
            $color = $l - $a * max(min($k - 3, 9 - $k, 1), -1);

            return str_pad(dechex((int) round(255 * $color)), 2, '0', STR_PAD_LEFT);
        };

        return '#'.$toHex(0).$toHex(8).$toHex(4);
    }

    /**
     * Absolute filesystem path to the uploaded company logo (same file as /storage/{logo_path}).
     */
    private function resolvePublicLogoFilePath(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $relativePath = ltrim($this->logo_path, '/');

        foreach ([
            Storage::disk('public')->path($relativePath),
            storage_path('app/public/'.$relativePath),
            public_path('storage/'.$relativePath),
        ] as $path) {
            if (is_string($path) && file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

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

    public function getJobcardStatusOptions(): array
    {
        return $this->buildStatusOptions(
            self::DEFAULT_JOBCARD_STATUS_LABELS,
            is_array($this->jobcard_status_labels) ? $this->jobcard_status_labels : []
        );
    }

    public function getQuoteStatusOptions(): array
    {
        return $this->buildStatusOptions(
            self::DEFAULT_QUOTE_STATUS_LABELS,
            is_array($this->quote_status_labels) ? $this->quote_status_labels : []
        );
    }

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
        static::where('is_default', true)->update(['is_default' => false]);
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
     */
    public function isModuleVisible(string $moduleKey): bool
    {
        if ($this->visible_modules === null) {
            return true;
        }

        return in_array($moduleKey, $this->visible_modules ?? []);
    }

    public function getVisibleModules(): ?array
    {
        return $this->visible_modules;
    }

    public function setVisibleModules(?array $modules): void
    {
        $this->visible_modules = $modules;
        $this->save();
    }
}