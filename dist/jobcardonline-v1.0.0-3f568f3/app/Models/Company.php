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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
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
     * Get the logo file path for PDF generation
     */
    public function getLogoPathForPdf()
    {
        if ($this->logo_path) {
            return storage_path('app/public/' . $this->logo_path);
        }
        return null;
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
}
