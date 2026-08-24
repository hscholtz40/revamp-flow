<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;
use App\Models\Query;
use Carbon\Carbon;

class ContractorLicenseProvisioningService
{
    public const TRIAL_DAYS = 60;

    /**
     * Create (or reuse) a not-deployed billed license for an accepted contractor query.
     */
    public function provisionFromAcceptedQuery(Query $query, int $customerId): License
    {
        $existing = License::query()
            ->where('source_query_id', $query->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $product = $this->resolvePackageProduct($query);
        $code = $product?->resolvedPackageCode() ?: (string) ($query->selected_package ?: Query::PACKAGE_CUSTOM);
        $defaults = Product::defaultPackageEntitlements($code);

        $standardUsers = (int) ($product?->license_standard_users ?: $defaults['standard']);
        $limitedUsers = (int) ($product?->license_limited_users ?: $defaults['limited']);
        $credits = (int) ($product?->monthly_credits ?: $defaults['credits']);
        $amount = (float) ($product?->price ?: $defaults['price']);

        $signupDate = Carbon::parse($query->created_at)->startOfDay();
        $firstInvoiceDate = $signupDate->copy()->addDays(self::TRIAL_DAYS);
        $isBillable = $amount > 0 && $code !== Query::PACKAGE_CUSTOM;

        $notes = trim(collect([
            'Created from contractor query #'.$query->id,
            $product ? 'Package product: '.$product->name : 'Package: '.($query->selectedPackageLabel() ?: $code),
            'First invoice scheduled '.$firstInvoiceDate->toDateString().' (60 days after signup).',
        ])->filter()->implode("\n"));

        return License::create([
            'company_id' => $query->company_id,
            'customer_id' => $customerId,
            'product_id' => $product?->id,
            'source_query_id' => $query->id,
            'license_key' => License::generateLicenseKey(),
            'standard_users' => $standardUsers,
            'limited_users' => $limitedUsers,
            'monthly_credits' => $credits,
            'status' => 'active',
            'notes' => $notes,
            'billing_cycle' => $isBillable ? License::BILLING_CYCLE_MONTHLY : null,
            'pricing_model' => $isBillable ? License::PRICING_MODEL_FIXED : null,
            'fixed_amount_monthly' => $isBillable ? $amount : null,
            'auto_email_invoice' => $isBillable,
            'next_invoice_date' => $isBillable ? $firstInvoiceDate->toDateString() : null,
        ]);
    }

    public function resolvePackageProduct(Query $query): ?Product
    {
        if ($query->selected_product_id) {
            $product = Product::query()
                ->where('company_id', $query->company_id)
                ->whereKey($query->selected_product_id)
                ->first();
            if ($product) {
                return $product;
            }
        }

        $code = (string) ($query->selected_package ?: '');

        return Product::licensingPackagesForCompany((int) $query->company_id)
            ->first(function (Product $product) use ($code) {
                return $product->resolvedPackageCode() === $code;
            });
    }
}
