<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CompanyScopedRules
{
    public static function customer(int $companyId)
    {
        return Rule::exists('customers', 'id')->where('company_id', $companyId);
    }

    /**
     * Contact must belong to the company; when customer_id is present on the request, it must match.
     */
    public static function contactForRequest(int $companyId)
    {
        return Rule::exists('contacts', 'id')->where(function ($query) use ($companyId) {
            $query->where('contacts.company_id', $companyId);
            if (request()->filled('customer_id')) {
                $query->where('contacts.customer_id', (int) request()->input('customer_id'));
            }
        });
    }

    public static function invoice(int $companyId, bool $matchCustomerFromRequest = false)
    {
        return Rule::exists('invoices', 'id')->where(function ($query) use ($companyId, $matchCustomerFromRequest) {
            $query->where('invoices.company_id', $companyId);
            if ($matchCustomerFromRequest && request()->filled('customer_id')) {
                $query->where('invoices.customer_id', (int) request()->input('customer_id'));
            }
        });
    }

    public static function jobcard(int $companyId)
    {
        return Rule::exists('jobcards', 'id')->where('company_id', $companyId);
    }

    public static function product(int $companyId)
    {
        return Rule::exists('products', 'id')->where('company_id', $companyId);
    }

    public static function taxRate(int $companyId)
    {
        return Rule::exists('tax_rates', 'id')->where('company_id', $companyId);
    }

    public static function chartOfAccount(int $companyId)
    {
        return Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId);
    }

    public static function supplier(int $companyId)
    {
        return Rule::exists('suppliers', 'id')->where('company_id', $companyId);
    }

    public static function team(int $companyId)
    {
        return Rule::exists('teams', 'id')->where('company_id', $companyId);
    }

    public static function emailTemplate(int $companyId)
    {
        return Rule::exists('email_templates', 'id')->where('company_id', $companyId);
    }

    public static function reportTemplate(int $companyId)
    {
        return Rule::exists('report_templates', 'id')->where('company_id', $companyId);
    }

    /**
     * Report templates owned by the company or marked global default (matches report builder UI).
     */
    public static function reportTemplateSelectableForCompany(int $companyId)
    {
        return Rule::exists('report_templates', 'id')->where(function ($query) use ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('report_templates.company_id', $companyId)
                    ->orWhere('report_templates.is_default', true);
            });
        });
    }

    public static function productSerialNumberForCompany(int $companyId)
    {
        return Rule::exists('product_serial_numbers', 'id')->where('company_id', $companyId);
    }

    public static function productBatchForCompany(int $companyId)
    {
        return Rule::exists('product_batches', 'id')->where('company_id', $companyId);
    }

    public static function productBatchForProduct(int $companyId, int $productId)
    {
        return Rule::exists('product_batches', 'id')->where(function ($query) use ($companyId, $productId) {
            $query->where('company_id', $companyId)->where('product_id', $productId);
        });
    }

    public static function jobcardLineItemForJobcard(int $jobcardId)
    {
        return Rule::exists('jobcard_line_items', 'id')->where('jobcard_id', $jobcardId);
    }

    public static function purchaseOrderItemForPurchaseOrder(int $purchaseOrderId)
    {
        return Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $purchaseOrderId);
    }

    public static function timeEntry(int $companyId)
    {
        return Rule::exists('time_entries', 'id')->where('company_id', $companyId);
    }

    public static function chartOfAccountParent(int $companyId, ?int $excludeAccountId = null)
    {
        return Rule::exists('chart_of_accounts', 'id')->where(function ($query) use ($companyId, $excludeAccountId) {
            $query->where('company_id', $companyId);
            if ($excludeAccountId !== null) {
                $query->where('id', '!=', $excludeAccountId);
            }
        });
    }

    /**
     * Ensure each line's serial numbers belong to the company and match that line's product_id.
     */
    public static function afterValidateLineItemSerialsMatchProduct(int $companyId): Closure
    {
        return function (Validator $validator) use ($companyId) {
            $lines = $validator->getData()['line_items'] ?? [];
            if (! is_array($lines)) {
                return;
            }
            foreach ($lines as $i => $line) {
                if (! is_array($line)) {
                    continue;
                }
                $productId = $line['product_id'] ?? null;
                $ids = $line['serial_number_ids'] ?? [];
                if (! $productId || ! is_array($ids) || $ids === []) {
                    continue;
                }
                foreach ($ids as $sid) {
                    if ($sid === null || $sid === '') {
                        continue;
                    }
                    $ok = DB::table('product_serial_numbers')
                        ->where('id', $sid)
                        ->where('company_id', $companyId)
                        ->where('product_id', $productId)
                        ->exists();
                    if (! $ok) {
                        $validator->errors()->add(
                            "line_items.{$i}.serial_number_ids",
                            'One or more serial numbers are invalid for this product.'
                        );

                        break;
                    }
                }
            }
        };
    }

    /**
     * PO line batches must belong to the company and match the line's product_id.
     */
    public static function afterValidatePurchaseOrderItemBatches(int $companyId): Closure
    {
        return function (Validator $validator) use ($companyId) {
            $items = $validator->getData()['items'] ?? [];
            if (! is_array($items)) {
                return;
            }
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $batchId = $item['product_batch_id'] ?? null;
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                if ($productId === null && isset($item['id'])) {
                    $productId = (int) (DB::table('purchase_order_items')->where('id', $item['id'])->value('product_id') ?? 0) ?: null;
                }
                if (! $batchId || ! $productId) {
                    continue;
                }
                $ok = DB::table('product_batches')
                    ->where('id', $batchId)
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->exists();
                if (! $ok) {
                    $validator->errors()->add(
                        "items.{$i}.product_batch_id",
                        'The selected batch is invalid for this product.'
                    );
                }
            }
        };
    }

    /**
     * PO line serials: company + product match.
     */
    public static function afterValidatePurchaseOrderItemSerials(int $companyId): Closure
    {
        return function (Validator $validator) use ($companyId) {
            $items = $validator->getData()['items'] ?? [];
            if (! is_array($items)) {
                return;
            }
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
                if ($productId === null && isset($item['id'])) {
                    $productId = (int) (DB::table('purchase_order_items')->where('id', $item['id'])->value('product_id') ?? 0) ?: null;
                }
                $ids = $item['serial_number_ids'] ?? [];
                if (! $productId || ! is_array($ids) || $ids === []) {
                    continue;
                }
                foreach ($ids as $sid) {
                    if ($sid === null || $sid === '') {
                        continue;
                    }
                    $ok = DB::table('product_serial_numbers')
                        ->where('id', $sid)
                        ->where('company_id', $companyId)
                        ->where('product_id', $productId)
                        ->exists();
                    if (! $ok) {
                        $validator->errors()->add(
                            "items.{$i}.serial_number_ids",
                            'One or more serial numbers are invalid for this product.'
                        );

                        break;
                    }
                }
            }
        };
    }

    /**
     * Convert-to-line-items: each time entry must belong to the jobcard and company.
     */
    public static function afterTimeEntriesBelongToJobcard(int $jobcardId, int $companyId): Closure
    {
        return function (Validator $validator) use ($jobcardId, $companyId) {
            $ids = $validator->getData()['time_entry_ids'] ?? [];
            if (! is_array($ids)) {
                return;
            }
            foreach ($ids as $i => $id) {
                if ($id === null || $id === '') {
                    continue;
                }
                $ok = DB::table('time_entries')
                    ->where('id', $id)
                    ->where('jobcard_id', $jobcardId)
                    ->where('company_id', $companyId)
                    ->exists();
                if (! $ok) {
                    $validator->errors()->add(
                        "time_entry_ids.{$i}",
                        'The selected time entry is invalid for this jobcard.'
                    );
                }
            }
        };
    }

    /**
     * Stock movement serials must belong to company and selected product.
     */
    public static function afterValidateStockMovementSerials(int $companyId): Closure
    {
        return function (Validator $validator) use ($companyId) {
            $productId = (int) ($validator->getData()['product_id'] ?? 0);
            if (! $productId) {
                return;
            }
            $ids = $validator->getData()['serial_number_ids'] ?? [];
            if (! is_array($ids) || $ids === []) {
                return;
            }
            foreach ($ids as $sid) {
                if ($sid === null || $sid === '') {
                    continue;
                }
                $ok = DB::table('product_serial_numbers')
                    ->where('id', $sid)
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId)
                    ->exists();
                if (! $ok) {
                    $validator->errors()->add(
                        'serial_number_ids',
                        'One or more serial numbers are invalid for this product.'
                    );

                    break;
                }
            }
        };
    }

    /**
     * When source_type is jobcard, source_id must be a jobcard in this company.
     */
    public static function afterSourceJobcardInCompany(int $companyId, string $sourceTypeField = 'source_type', string $sourceIdField = 'source_id'): Closure
    {
        return function (Validator $validator) use ($companyId, $sourceTypeField, $sourceIdField) {
            $data = $validator->getData();
            if (($data[$sourceTypeField] ?? null) !== 'jobcard' || empty($data[$sourceIdField])) {
                return;
            }
            $ok = DB::table('jobcards')
                ->where('id', $data[$sourceIdField])
                ->where('company_id', $companyId)
                ->exists();
            if (! $ok) {
                $validator->errors()->add($sourceIdField, 'The selected source jobcard is invalid.');
            }
        };
    }

    /**
     * For stock transfers, destination company must exist and be accessible to the user.
     */
    public static function afterTransferDestinationCompanyAccessible(): Closure
    {
        return function (Validator $validator) {
            $data = $validator->getData();
            if (($data['type'] ?? '') !== 'transfer') {
                return;
            }
            $toId = $data['to_company_id'] ?? null;
            if ($toId === null || $toId === '') {
                return;
            }
            $user = auth()->user();
            if (! $user || ! $user->hasAccessToCompany((int) $toId)) {
                $validator->errors()->add('to_company_id', 'You do not have access to the destination company.');
            }
        };
    }
}
