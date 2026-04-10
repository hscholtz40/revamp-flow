<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

class CustomerStatementService
{
    /**
     * @param  iterable<int>|Collection<int>  $customerIds
     * @return array{rows: Collection<int, array<string, mixed>>, creditNoteRows: Collection<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function buildAgeingStatement(iterable $customerIds, int $companyId): array
    {
        $ids = $customerIds instanceof Collection ? $customerIds : collect($customerIds);

        $invoices = Invoice::query()
            ->with(['company:id,name', 'payments'])
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderBy('due_date')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) {
            $total = (float) ($invoice->total ?? 0);
            $payments = (float) $invoice->payments->sum('amount');
            $balance = (float) $invoice->remaining_balance;
            $daysOverdue = $invoice->due_date
                ? Date::now()->startOfDay()->diffInDays($invoice->due_date->copy()->startOfDay(), false) * -1
                : 0;

            $bucket = 'current';
            if ($daysOverdue > 0 && $daysOverdue <= 30) {
                $bucket = 'days_30';
            } elseif ($daysOverdue > 30 && $daysOverdue <= 60) {
                $bucket = 'days_60';
            } elseif ($daysOverdue > 60) {
                $bucket = 'days_90_plus';
            }

            return [
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'status' => ucfirst(str_replace('_', ' ', (string) $invoice->status)),
                'total' => $total,
                'payments' => $payments,
                'balance' => $balance,
                'bucket' => $bucket,
            ];
        })->filter(fn (array $row) => $row['balance'] > 0.0001)->values();

        $creditNotes = CreditNote::query()
            ->whereIn('customer_id', $ids)
            ->where('company_id', $companyId)
            ->where('status', '!=', 'voided')
            ->where('remaining_credit', '>', 0.0001)
            ->orderBy('credit_note_date')
            ->orderBy('credit_note_number')
            ->get();

        $creditNoteRows = $creditNotes->map(function (CreditNote $cn) {
            return [
                'credit_note_number' => $cn->credit_note_number,
                'credit_note_date' => $cn->credit_note_date,
                'status' => ucfirst(str_replace('_', ' ', (string) $cn->status)),
                'total' => (float) ($cn->total ?? 0),
                'remaining_credit' => (float) ($cn->remaining_credit ?? 0),
            ];
        })->values();

        $unappliedCreditTotal = round((float) $creditNoteRows->sum('remaining_credit'), 2);

        $invoiceBalanceTotal = (float) $rows->sum('balance');

        $totals = [
            'current' => (float) $rows->where('bucket', 'current')->sum('balance'),
            'days_30' => (float) $rows->where('bucket', 'days_30')->sum('balance'),
            'days_60' => (float) $rows->where('bucket', 'days_60')->sum('balance'),
            'days_90_plus' => (float) $rows->where('bucket', 'days_90_plus')->sum('balance'),
            'total_balance' => $invoiceBalanceTotal,
            'invoice_total' => (float) $rows->sum('total'),
            'payments_total' => (float) $rows->sum('payments'),
            'unapplied_credit_total' => $unappliedCreditTotal,
            'net_balance' => round($invoiceBalanceTotal - $unappliedCreditTotal, 2),
        ];

        return [
            'rows' => $rows,
            'creditNoteRows' => $creditNoteRows,
            'totals' => $totals,
        ];
    }

    public function makeStatementPdf(
        Customer $customer,
        Company $company,
        Collection $rows,
        Collection $creditNoteRows,
        array $totals,
        ?Carbon $generatedAt = null,
    ): \Barryvdh\DomPDF\PDF {
        $generatedAt ??= Date::now();

        return Pdf::loadView('pdf.client-statement', [
            'customer' => $customer,
            'company' => $company,
            'rows' => $rows,
            'creditNoteRows' => $creditNoteRows,
            'totals' => $totals,
            'generatedAt' => $generatedAt,
        ]);
    }
}
