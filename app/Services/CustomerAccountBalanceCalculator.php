<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAccountBalanceCalculator
{
    /**
     * Net amount receivable for the customer(s) in one company: open invoice balances minus unapplied credit note balance.
     * JCO-only; not synced with Xero.
     *
     * @param  iterable<int>|Collection<int>  $customerIds
     * @return array{outstanding_invoices: float, unapplied_credit: float, account_balance: float}
     */
    public static function forCustomersInCompany(iterable $customerIds, int $companyId): array
    {
        $ids = $customerIds instanceof Collection ? $customerIds : collect($customerIds);
        if ($ids->isEmpty()) {
            return [
                'outstanding_invoices' => 0.0,
                'unapplied_credit' => 0.0,
                'account_balance' => 0.0,
            ];
        }

        $outstandingInvoices = (float) Invoice::query()
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->with('payments')
            ->get()
            ->sum(fn (Invoice $invoice) => $invoice->remaining_balance);

        $unappliedCredit = (float) CreditNote::query()
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->where('status', '!=', 'voided')
            ->sum('remaining_credit');

        $outstandingInvoices = round($outstandingInvoices, 2);
        $unappliedCredit = round($unappliedCredit, 2);

        return [
            'outstanding_invoices' => $outstandingInvoices,
            'unapplied_credit' => $unappliedCredit,
            'account_balance' => round($outstandingInvoices - $unappliedCredit, 2),
        ];
    }

    /**
     * Per-customer breakdown for a set of customers in one company (e.g. list pagination).
     *
     * @param  iterable<int>|Collection<int>  $customerIds
     * @return array<int, array{outstanding_invoices: float, unapplied_credit: float, account_balance: float}>
     */
    public static function balancesKeyedByCustomerId(iterable $customerIds, int $companyId): array
    {
        $ids = collect($customerIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        /** @var array<int, array{outstanding_invoices: float, unapplied_credit: float, account_balance: float}> $byCustomer */
        $byCustomer = [];
        foreach ($ids as $id) {
            $byCustomer[(int) $id] = [
                'outstanding_invoices' => 0.0,
                'unapplied_credit' => 0.0,
                'account_balance' => 0.0,
            ];
        }

        $invoices = Invoice::query()
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->with('payments')
            ->get();

        foreach ($invoices->groupBy('customer_id') as $customerId => $group) {
            $id = (int) $customerId;
            $sum = (float) $group->sum(fn (Invoice $invoice) => $invoice->remaining_balance);
            if (isset($byCustomer[$id])) {
                $byCustomer[$id]['outstanding_invoices'] = round($sum, 2);
            }
        }

        $creditTotals = CreditNote::query()
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->where('status', '!=', 'voided')
            ->groupBy('customer_id')
            ->selectRaw('customer_id, SUM(remaining_credit) as total')
            ->pluck('total', 'customer_id');

        foreach (array_keys($byCustomer) as $id) {
            $uc = round((float) ($creditTotals->get($id) ?? $creditTotals->get((string) $id) ?? 0), 2);
            $byCustomer[$id]['unapplied_credit'] = $uc;
            $byCustomer[$id]['account_balance'] = round($byCustomer[$id]['outstanding_invoices'] - $uc, 2);
        }

        return $byCustomer;
    }

    /**
     * Order a customers query by JCO account balance (matches {@see balancesKeyedByCustomerId} logic).
     */
    public static function applyAccountBalanceSort(Builder $query, int $companyId, string $direction): void
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $paymentsAgg = DB::table('payments')
            ->select('invoice_id')
            ->selectRaw('SUM(amount) as paid')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $allocAgg = DB::table('credit_note_allocations as cna')
            ->join('credit_notes as cn', 'cn.id', '=', 'cna.credit_note_id')
            ->where('cn.company_id', $companyId)
            ->where('cn.status', '!=', 'voided')
            ->select('cna.invoice_id')
            ->selectRaw('SUM(cna.amount) as allocated')
            ->groupBy('cna.invoice_id');

        $legacyAgg = DB::table('credit_notes as cn')
            ->where('cn.company_id', $companyId)
            ->where('cn.status', '!=', 'voided')
            ->whereNotNull('cn.invoice_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw('1'))
                    ->from('credit_note_allocations as cna2')
                    ->whereColumn('cna2.credit_note_id', 'cn.id');
            })
            ->select('cn.invoice_id')
            ->selectRaw('SUM(cn.total) as legacy')
            ->groupBy('cn.invoice_id');

        $remainder = '(invoices.total - COALESCE(payments_agg.paid, 0) - COALESCE(alloc_agg.allocated, 0) - COALESCE(legacy_agg.legacy, 0))';
        $remainingLine = "CASE WHEN {$remainder} < 0 THEN 0 ELSE {$remainder} END";

        $invoiceOutstanding = DB::table('invoices')
            ->leftJoinSub($paymentsAgg, 'payments_agg', function ($join) {
                $join->on('payments_agg.invoice_id', '=', 'invoices.id');
            })
            ->leftJoinSub($allocAgg, 'alloc_agg', function ($join) {
                $join->on('alloc_agg.invoice_id', '=', 'invoices.id');
            })
            ->leftJoinSub($legacyAgg, 'legacy_agg', function ($join) {
                $join->on('legacy_agg.invoice_id', '=', 'invoices.id');
            })
            ->where('invoices.company_id', $companyId)
            ->whereNotIn('invoices.status', ['paid', 'cancelled'])
            ->groupBy('invoices.customer_id')
            ->select('invoices.customer_id')
            ->selectRaw("SUM({$remainingLine}) as invoice_outstanding");

        $unappliedAgg = DB::table('credit_notes')
            ->where('company_id', $companyId)
            ->where('status', '!=', 'voided')
            ->select('customer_id')
            ->selectRaw('SUM(remaining_credit) as unapplied_credit')
            ->groupBy('customer_id');

        $query->select('customers.*')
            ->leftJoinSub($invoiceOutstanding, 'jco_inv_ob', function ($join) {
                $join->on('jco_inv_ob.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($unappliedAgg, 'jco_cn_uc', function ($join) {
                $join->on('jco_cn_uc.customer_id', '=', 'customers.id');
            })
            ->orderByRaw('(COALESCE(jco_inv_ob.invoice_outstanding, 0) - COALESCE(jco_cn_uc.unapplied_credit, 0)) '.$direction)
            ->orderBy('customers.name')
            ->orderBy('customers.id');
    }
}
