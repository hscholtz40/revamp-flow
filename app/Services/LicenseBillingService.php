<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\EmailActivity;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\License;
use App\Models\LineGroup;
use App\Models\TaxRate;
use App\Support\CompanyMailer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LicenseBillingService
{
    /**
     * Create an invoice for a license when billing is configured.
     * Optionally emails the customer PDF invoice.
     *
     * @return array{invoice: Invoice|null, emailed: bool, skipped: bool, message: string}
     */
    public function createAndOptionallyEmail(License $license): array
    {
        $license->loadMissing(['customer', 'company']);

        if (! $license->billingEnabled()) {
            return [
                'invoice' => null,
                'emailed' => false,
                'skipped' => true,
                'message' => 'License billing is not configured.',
            ];
        }

        if ($license->status !== 'active') {
            return [
                'invoice' => null,
                'emailed' => false,
                'skipped' => true,
                'message' => 'License is not active.',
            ];
        }

        $lines = $this->buildLineItems($license);
        if ($lines === []) {
            return [
                'invoice' => null,
                'emailed' => false,
                'skipped' => true,
                'message' => 'Invoice amount would be zero.',
            ];
        }

        $invoice = $this->createInvoice($license, $lines);
        $this->advanceBillingSchedule($license);

        $emailed = false;
        if ($license->auto_email_invoice) {
            $emailed = $this->emailInvoicePdf($invoice);
        }

        return [
            'invoice' => $invoice,
            'emailed' => $emailed,
            'skipped' => false,
            'message' => $emailed
                ? 'Invoice created and emailed.'
                : 'Invoice created.',
        ];
    }

    /**
     * Generate invoices for all due licenses.
     *
     * @return array{created: int, emailed: int, skipped: int, failed: int}
     */
    public function generateDueInvoices(?Carbon $runDate = null): array
    {
        $today = ($runDate ?? now())->copy()->startOfDay();
        $created = 0;
        $emailed = 0;
        $skipped = 0;
        $failed = 0;

        $due = License::query()
            ->where('status', 'active')
            ->whereNotNull('billing_cycle')
            ->whereNotNull('pricing_model')
            ->whereDate('next_invoice_date', '<=', $today->toDateString())
            ->orderBy('id')
            ->get();

        foreach ($due as $license) {
            try {
                $result = $this->createAndOptionallyEmail($license);
                if ($result['skipped']) {
                    $skipped++;
                    // Still advance so we don't retry a zero-amount license every day.
                    if ($license->billingEnabled()) {
                        $this->advanceBillingSchedule($license);
                    }
                } else {
                    $created++;
                    if ($result['emailed']) {
                        $emailed++;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('License invoice generation failed', [
                    'license_id' => $license->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('created', 'emailed', 'skipped', 'failed');
    }

    /**
     * @return list<array{description: string, quantity: float, unit_price: float, total: float}>
     */
    public function buildLineItems(License $license): array
    {
        $cycle = $license->billing_cycle;
        $isAnnual = $cycle === License::BILLING_CYCLE_ANNUAL;
        $lines = [];

        if ($license->pricing_model === License::PRICING_MODEL_FIXED) {
            $amount = (float) ($isAnnual ? $license->fixed_amount_annual : $license->fixed_amount_monthly);
            if ($amount > 0) {
                $period = $isAnnual ? 'Annual' : 'Monthly';
                $lines[] = [
                    'description' => "Revamp Jobcards {$period} license fee ({$license->license_key})",
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ];
            }

            return $lines;
        }

        $standardRate = (float) ($isAnnual ? $license->price_standard_annual : $license->price_standard_monthly);
        $limitedRate = (float) ($isAnnual ? $license->price_limited_annual : $license->price_limited_monthly);
        $period = $isAnnual ? 'annual' : 'monthly';

        $standardUsers = max(0, (int) $license->standard_users);
        if ($standardUsers > 0 && $standardRate > 0) {
            $total = round($standardUsers * $standardRate, 2);
            $lines[] = [
                'description' => "Standard users ({$period}) × {$standardUsers} — {$license->license_key}",
                'quantity' => $standardUsers,
                'unit_price' => $standardRate,
                'total' => $total,
            ];
        }

        $limitedUsers = max(0, (int) $license->limited_users);
        if ($limitedUsers > 0 && $limitedRate > 0) {
            $total = round($limitedUsers * $limitedRate, 2);
            $lines[] = [
                'description' => "Limited users ({$period}) × {$limitedUsers} — {$license->license_key}",
                'quantity' => $limitedUsers,
                'unit_price' => $limitedRate,
                'total' => $total,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{description: string, quantity: float, unit_price: float, total: float}>  $lines
     */
    private function createInvoice(License $license, array $lines): Invoice
    {
        $customer = $license->customer;
        if (! $customer) {
            throw new \RuntimeException('License has no customer.');
        }

        $invoiceDate = now()->startOfDay();
        $dueDate = $this->resolveDueDate($customer, $invoiceDate);
        $taxRate = TaxRate::getDefaultSalesForCompany((int) $license->company_id);
        $taxRateId = $taxRate?->id;
        $taxRatePercent = (float) ($taxRate?->rate ?? 0);
        $salespersonId = auth()->id() ?? $this->resolveSalespersonId((int) $license->company_id);
        $invoiceNumber = Invoice::generateInvoiceNumber((int) $license->company_id);
        $cycleLabel = $license->billing_cycle === License::BILLING_CYCLE_ANNUAL ? 'Annual' : 'Monthly';

        return DB::transaction(function () use (
            $license,
            $customer,
            $lines,
            $invoiceDate,
            $dueDate,
            $taxRateId,
            $taxRatePercent,
            $salespersonId,
            $invoiceNumber,
            $cycleLabel
        ) {
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'title' => "{$cycleLabel} license — {$license->license_key}",
                'description' => "Automated {$cycleLabel} license billing",
                'customer_id' => $customer->id,
                'email' => $customer->email ?: $customer->contact_email,
                'phone' => $customer->phone ?: $customer->company_tel ?: $customer->contact_cell,
                'salesperson_id' => $salespersonId,
                'company_id' => $license->company_id,
                'status' => 'draft',
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'tax_rate' => 0,
                'terms' => (string) ($customer->terms ?: 'COD'),
                'source_type' => 'license',
                'source_id' => $license->id,
            ]);

            $group = LineGroup::createDefaultFor($invoice);

            foreach ($lines as $index => $line) {
                $lineTax = $taxRateId
                    ? round((float) $line['total'] * ($taxRatePercent / 100), 2)
                    : 0;

                InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'line_group_id' => $group->id,
                    'product_id' => null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'discount_amount' => 0,
                    'discount_percentage' => 0,
                    'total' => $line['total'],
                    'tax_rate_id' => $taxRateId,
                    'tax_amount' => $lineTax,
                    'account_id' => null,
                    'sort_order' => $index,
                ]);
            }

            $invoice->calculateTotals();
            $invoice->refresh();
            $invoice->load(['customer', 'company', 'lineItems']);

            return $invoice;
        });
    }

    public function advanceBillingSchedule(License $license): void
    {
        $base = $license->next_invoice_date
            ? Carbon::parse($license->next_invoice_date)->startOfDay()
            : now()->startOfDay();

        $today = now()->startOfDay();
        $next = $base->copy();

        // First invoice: schedule from today. Subsequent: advance from next_invoice_date.
        if (! $license->last_invoiced_at && ! $license->next_invoice_date) {
            $next = $today->copy();
        }

        do {
            $next = $license->billing_cycle === License::BILLING_CYCLE_ANNUAL
                ? $next->copy()->addYear()
                : $next->copy()->addMonth();
        } while ($next->lte($today));

        $license->forceFill([
            'last_invoiced_at' => now(),
            'next_invoice_date' => $next->toDateString(),
        ])->save();
    }

    public function emailInvoicePdf(Invoice $invoice, ?string $customMessage = null): bool
    {
        $invoice->loadMissing(['customer', 'contact', 'lineItems.product', 'lineItems.taxRate', 'company', 'signatures']);
        $company = $invoice->company;
        if (! $company) {
            return false;
        }

        $email = trim((string) ($invoice->email ?: $invoice->customer?->email ?: $invoice->customer?->contact_email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('License invoice email skipped — no valid customer email', [
                'invoice_id' => $invoice->id,
                'license_id' => $invoice->source_id,
            ]);

            return false;
        }

        $invoice->setRelation('lineItemsForRoundingTotals', $invoice->lineItems->values());
        $invoice->setRelation('lineItems', $invoice->lineItems->reject(function ($item) {
            return strtolower(trim((string) ($item->description ?? ''))) === 'rounding adjustment';
        })->values());

        try {
            $pdfService = app(PdfGenerationService::class);
            $pdf = $pdfService->generatePdf('invoice', compact('invoice', 'company'), $company);
            $pdfContent = $pdf->output();

            $mailConfig = CompanyMailer::resolve($company);
            Mail::mailer($mailConfig['mailer'])->send('emails.invoice', [
                'invoice' => $invoice,
                'customMessage' => $customMessage,
            ], function ($message) use ($email, $invoice, $pdfContent, $company, $mailConfig) {
                $message->to($email)
                    ->subject("Invoice {$invoice->invoice_number} - {$invoice->title}")
                    ->from($mailConfig['from_address'], $mailConfig['from_name'])
                    ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                        'mime' => 'application/pdf',
                    ]);

                if (! empty($company->email)) {
                    $message->replyTo($company->email, $company->name ?? null);
                }
            });

            EmailActivity::create([
                'company_id' => $invoice->company_id,
                'customer_id' => $invoice->customer_id,
                'contact_id' => $invoice->contact_id,
                'user_id' => auth()->id(),
                'recipient_email' => $email,
                'recipient_name' => $invoice->customer?->name,
                'subject' => "Invoice {$invoice->invoice_number} - {$invoice->title}",
                'body' => $customMessage ?? '',
                'email_type' => 'document',
                'related_type' => 'invoice',
                'related_id' => $invoice->id,
                'status' => 'sent',
                'metadata' => ['source' => 'license_billing'],
                'sent_at' => now(),
            ]);

            if ($invoice->status === 'draft') {
                $invoice->update(['status' => 'sent']);
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('License invoice email failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            EmailActivity::create([
                'company_id' => $invoice->company_id,
                'customer_id' => $invoice->customer_id,
                'contact_id' => $invoice->contact_id,
                'user_id' => auth()->id(),
                'recipient_email' => $email,
                'recipient_name' => $invoice->customer?->name,
                'subject' => "Invoice {$invoice->invoice_number} - {$invoice->title}",
                'body' => $customMessage ?? '',
                'email_type' => 'document',
                'related_type' => 'invoice',
                'related_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveDueDate(Customer $customer, Carbon $invoiceDate): Carbon
    {
        $terms = trim((string) ($customer->terms ?: 'COD'));
        $days = 0;

        if (preg_match('/^cod$/i', $terms) === 1) {
            $days = 0;
        } elseif (preg_match('/net\s*(\d+)\s*days?/i', $terms, $matches) === 1) {
            $days = (int) $matches[1];
        } elseif (preg_match('/(\d+)/', $terms, $matches) === 1) {
            $days = (int) $matches[1];
        }

        return $invoiceDate->copy()->addDays($days);
    }

    private function resolveSalespersonId(int $companyId): ?int
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return null;
        }

        $adminId = $company->users()
            ->whereHas('groups', fn ($q) => $q->where('is_administrator', true))
            ->orderBy('users.id')
            ->value('users.id');

        if ($adminId) {
            return (int) $adminId;
        }

        $userId = $company->users()->orderBy('users.id')->value('users.id');

        return $userId ? (int) $userId : null;
    }
}
