<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\ScopedToCurrentCompanyRouteBinding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable, HasFactory, ScopedToCurrentCompanyRouteBinding;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'sku',
        'price',
        'cost',
        'unit',
        'stock_quantity',
        'min_stock_level',
        'track_stock',
        'is_active',
        'is_licensing_package',
        'package_code',
        'license_standard_users',
        'license_limited_users',
        'monthly_credits',
        'category',
        'tags',
        'image_path',
        'notes',
        'xero_item_id',
        'purchase_account_code',
        'sales_account_code',
        // New inventory fields
        'supplier_id',
        'barcode',
        'low_stock_threshold',
        'unit_of_measure',
        'cost_price',
        'selling_price',
        'valuation_method',
        'track_batches',
        'track_serial_numbers',
        'xero_updated_at',
        'xero_created_at',
    ];

    protected $casts = [
        'price' => 'float',
        'cost' => 'float',
        'stock_quantity' => 'integer',
        'min_stock_level' => 'integer',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_licensing_package' => 'boolean',
        'license_standard_users' => 'integer',
        'license_limited_users' => 'integer',
        'monthly_credits' => 'integer',
        'tags' => 'array',
        'track_batches' => 'boolean',
        'track_serial_numbers' => 'boolean',
        'xero_updated_at' => 'datetime',
        'xero_created_at' => 'datetime',
    ];

    /**
     * Scope to filter by type (product or service)
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter active products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function jobcardLineItems(): HasMany
    {
        return $this->hasMany(JobcardLineItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(ProductSerialNumber::class);
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        if (! $this->track_stock) {
            return false;
        }

        $threshold = $this->low_stock_threshold ?? $this->min_stock_level ?? 10;

        return $this->stock_quantity <= $threshold;
    }

    /**
     * Calculate profit margin
     */
    public function getProfitMarginAttribute(): ?float
    {
        $cost = (float) $this->cost;
        $price = (float) $this->price;

        if (! $cost || $cost == 0) {
            return null;
        }

        return (($price - $cost) / $cost) * 100;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$'.number_format((float) $this->price, 2);
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): ?string
    {
        return $this->cost ? '$'.number_format((float) $this->cost, 2) : null;
    }

    /**
     * @return array{standard:int,limited:int,credits:int,price:float}
     */
    public static function defaultPackageEntitlements(string $code): array
    {
        return match ($code) {
            'option_1' => ['standard' => 1, 'limited' => 5, 'credits' => 8, 'price' => 550.0],
            'option_2' => ['standard' => 2, 'limited' => 10, 'credits' => 12, 'price' => 850.0],
            default => ['standard' => 0, 'limited' => 0, 'credits' => 0, 'price' => 0.0],
        };
    }

    public function resolvedPackageCode(): ?string
    {
        if (filled($this->package_code)) {
            return (string) $this->package_code;
        }

        $sku = strtolower((string) preg_replace('/[\s\-]+/', '_', (string) $this->sku));
        if (in_array($sku, ['option_1', 'option1'], true)) {
            return 'option_1';
        }
        if (in_array($sku, ['option_2', 'option2'], true)) {
            return 'option_2';
        }
        if ($sku === 'custom') {
            return 'custom';
        }

        $name = strtolower((string) $this->name);
        if (str_contains($name, 'option 2') || str_contains($name, 'option_2')) {
            return 'option_2';
        }
        if (str_contains($name, 'option 1') || str_contains($name, 'option_1')) {
            return 'option_1';
        }
        if (str_contains($name, 'custom')) {
            return 'custom';
        }

        return $this->is_licensing_package ? 'product_'.$this->id : null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public static function licensingPackagesForCompany(int $companyId)
    {
        return static::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('price')
            ->orderBy('name')
            ->get()
            ->filter(function (self $product) {
                return $product->is_licensing_package || $product->resolvedPackageCode() !== null;
            })
            ->values();
    }
}
