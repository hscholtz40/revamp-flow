<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportFiltersService
{
    public function mergeTemplateAndRequestFilters(Report $report, Request $request, bool $filledOnly = false): array
    {
        $templateFilters = $report->template?->filters ?? [];
        $mergedFilters = [];

        foreach (['customer_id', 'product_id', 'status'] as $arrayField) {
            if (! empty($templateFilters[$arrayField])) {
                $mergedFilters[$arrayField] = (array) $templateFilters[$arrayField];
            }
        }

        foreach (['date_from', 'date_to'] as $dateField) {
            if (! empty($templateFilters[$dateField])) {
                $mergedFilters[$dateField] = $templateFilters[$dateField];
            }
        }

        foreach (['customer_id', 'product_id', 'status'] as $arrayField) {
            $value = $request->input($arrayField);
            $shouldOverride = $filledOnly
                ? (is_array($value) ? $value !== [] : $request->filled($arrayField))
                : ($request->has($arrayField) && $value !== null);
            if ($shouldOverride) {
                $mergedFilters[$arrayField] = (array) $value;
            }
        }

        foreach (['date_from', 'date_to'] as $dateField) {
            $value = $request->input($dateField);
            $shouldOverride = $filledOnly
                ? $request->filled($dateField)
                : ($request->has($dateField) && $value !== null && $value !== '');

            if ($shouldOverride) {
                $mergedFilters[$dateField] = $value;
            }
        }

        return $mergedFilters;
    }

    public function resolveFilterOptions(int $companyId): array
    {
        return [
            'customers' => Customer::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'account_code']),
            'products' => Product::where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'sku']),
        ];
    }
}
