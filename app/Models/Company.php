<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'smtp_port' => 'integer',
        'visible_modules' => 'array',
        'invoice_number_next' => 'integer',
        'quote_number_next' => 'integer',
        'jobcard_number_next' => 'integer',
        'credit_note_number_next' => 'integer',
        'purchase_order_number_next' => 'integer',
    ];

    protected $hidden = [
        'smtp_password',
    ];

    /**
     * Get the logo URL
     */
    public function getLogoAttribute()
    {
        if ($this->logo_path) {
            return asset('storage/' . $this->logo_path);
        }
        return null;
    }

    /**
     * Get the logo as a base64 data URI for PDF generation.
     * DomPDF cannot fetch images from URLs, so we convert to inline base64.
     */
    public function getLogoPathForPdf()
    {
        if (!$this->logo_path) {
            return null;
        }

        $fullPath = storage_path('app/public/' . $this->logo_path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $imageData = file_get_contents($fullPath);
        $imageInfo = getimagesize($fullPath);
        $mimeType = $imageInfo['mime'] ?? 'image/png';

        return 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
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
     * Check if company has SMTP settings configured.
     */
    public function hasSmtpConfigured(): bool
    {
        return !empty($this->smtp_host) && !empty($this->smtp_username) && !empty($this->smtp_password);
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
