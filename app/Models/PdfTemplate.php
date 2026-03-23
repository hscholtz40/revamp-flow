<?php

namespace App\Models;

use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfTemplate extends Model
{
    use HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'module',
        'name',
        'html_template',
        'css_styles',
        'default_data',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'default_data' => 'array',
    ];

    /**
     * Get available modules
     */
    public static function getModules(): array
    {
        return [
            'invoice' => 'Invoice',
            'quote' => 'Quote',
            'jobcard' => 'Jobcard',
            'proforma-invoice' => 'Proforma Invoice',
            'purchase-order' => 'Purchase Order',
        ];
    }

    /**
     * Scope to filter by module
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope to filter active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter default templates
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
