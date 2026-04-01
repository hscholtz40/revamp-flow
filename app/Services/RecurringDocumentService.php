<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Jobcard;
use App\Models\JobcardLineItem;
use App\Models\LineGroup;
use App\Models\RecurringDocument;
use Carbon\Carbon;

class RecurringDocumentService
{
    public function generateDueDocuments(?Carbon $runDate = null): array
    {
        $today = ($runDate ?? now())->copy()->startOfDay();
        $created = 0;
        $failed = 0;

        $due = RecurringDocument::query()
            ->where('is_active', true)
            ->whereDate('next_run_date', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $today->toDateString());
            })
            ->orderBy('id')
            ->get();

        foreach ($due as $recurring) {
            try {
                $this->createFromSchedule($recurring, $today);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::warning('Recurring document generation failed', [
                    'recurring_document_id' => $recurring->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    private function createFromSchedule(RecurringDocument $recurring, Carbon $today): void
    {
        if ($recurring->document_type === RecurringDocument::TYPE_JOBCARD) {
            $this->createJobcard($recurring, $today);
        } else {
            $this->createInvoice($recurring, $today);
        }

        $next = $recurring->next_run_date ? Carbon::parse($recurring->next_run_date) : $today->copy();
        do {
            $next = $this->increment($next, $recurring->frequency);
        } while ($next->lte($today));

        $recurring->update([
            'last_run_date' => $today->toDateString(),
            'next_run_date' => $next->toDateString(),
            'is_active' => ! ($recurring->end_date && $next->gt(Carbon::parse($recurring->end_date))),
        ]);
    }

    private function createJobcard(RecurringDocument $recurring, Carbon $today): void
    {
        $source = Jobcard::query()
            ->where('company_id', $recurring->company_id)
            ->with(['lineItems', 'lineGroups'])
            ->findOrFail($recurring->source_id);

        $jobcard = Jobcard::create([
            'company_id' => $source->company_id,
            'customer_id' => $source->customer_id,
            'contact_id' => $source->contact_id,
            'email' => $source->email,
            'phone' => $source->phone,
            'assigned_to_user_id' => $source->assigned_to_user_id,
            'assigned_to_team_id' => $source->assigned_to_team_id,
            'job_number' => Jobcard::generateJobNumber($source->company_id),
            'order_number' => $source->order_number,
            'title' => $source->title,
            'description' => $source->description,
            'status' => 'draft',
            'start_date' => $today->toDateString(),
            'due_date' => $today->toDateString(),
            'tax_rate' => (float) $source->tax_rate,
            'notes' => $source->notes,
            'terms_conditions' => $source->terms_conditions,
        ]);

        $groupMap = [];
        foreach ($source->lineGroups as $group) {
            $newGroup = $jobcard->lineGroups()->create([
                'name' => $group->name ?: 'Items',
                'sort_order' => $group->sort_order ?? 0,
            ]);
            $groupMap[$group->id] = $newGroup->id;
        }
        if (empty($groupMap)) {
            $defaultGroup = LineGroup::createDefaultFor($jobcard);
            $defaultGroupId = $defaultGroup->id;
        } else {
            $defaultGroupId = reset($groupMap);
        }

        foreach ($source->lineItems as $item) {
            $line = new JobcardLineItem([
                'line_group_id' => $groupMap[$item->line_group_id] ?? $defaultGroupId,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount ?? 0,
                'discount_percentage' => $item->discount_percentage ?? 0,
                'tax_rate_id' => $item->tax_rate_id,
                'account_id' => $item->account_id,
                'sort_order' => $item->sort_order,
            ]);
            $line->calculateTotal();
            $jobcard->lineItems()->save($line);
        }

        $jobcard->calculateTotals();
    }

    private function createInvoice(RecurringDocument $recurring, Carbon $today): void
    {
        $source = Invoice::query()
            ->where('company_id', $recurring->company_id)
            ->with(['lineItems', 'lineGroups', 'customer'])
            ->findOrFail($recurring->source_id);

        $invoiceDate = $today->copy();
        $sourceInvoiceDate = $source->invoice_date ? Carbon::parse($source->invoice_date) : $today->copy();
        $sourceDueDate = $source->due_date ? Carbon::parse($source->due_date) : $sourceInvoiceDate->copy();
        $dueOffset = $sourceDueDate->diffInDays($sourceInvoiceDate, false);

        $invoice = Invoice::create([
            'company_id' => $source->company_id,
            'customer_id' => $source->customer_id,
            'contact_id' => $source->contact_id,
            'email' => $source->email,
            'phone' => $source->phone,
            'salesperson_id' => $source->salesperson_id,
            'invoice_number' => Invoice::generateInvoiceNumber($source->company_id),
            'order_number' => $source->order_number,
            'title' => $source->title,
            'description' => $source->description,
            'status' => 'draft',
            'invoice_date' => $invoiceDate->toDateString(),
            'due_date' => $invoiceDate->copy()->addDays($dueOffset)->toDateString(),
            'tax_rate' => (float) $source->tax_rate,
            'notes' => $source->notes,
            'terms' => $source->terms,
            'terms_conditions' => $source->terms_conditions,
        ]);

        $groupMap = [];
        foreach ($source->lineGroups as $group) {
            $newGroup = $invoice->lineGroups()->create([
                'name' => $group->name ?: 'Items',
                'sort_order' => $group->sort_order ?? 0,
            ]);
            $groupMap[$group->id] = $newGroup->id;
        }
        if (empty($groupMap)) {
            $defaultGroup = LineGroup::createDefaultFor($invoice);
            $defaultGroupId = $defaultGroup->id;
        } else {
            $defaultGroupId = reset($groupMap);
        }

        foreach ($source->lineItems as $item) {
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'line_group_id' => $groupMap[$item->line_group_id] ?? $defaultGroupId,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_amount' => $item->discount_amount ?? 0,
                'discount_percentage' => $item->discount_percentage ?? 0,
                'total' => $item->total,
                'tax_rate_id' => $item->tax_rate_id,
                'tax_amount' => $item->tax_amount,
                'account_id' => $item->account_id,
                'sort_order' => $item->sort_order,
                'serial_number_ids' => [],
            ]);
        }

        $invoice->refresh();
        $invoice->calculateTotals();
    }

    private function increment(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            RecurringDocument::FREQ_DAILY => $date->copy()->addDay(),
            RecurringDocument::FREQ_WEEKLY => $date->copy()->addWeek(),
            RecurringDocument::FREQ_MONTHLY => $date->copy()->addMonth(),
            RecurringDocument::FREQ_QUARTERLY => $date->copy()->addMonths(3),
            RecurringDocument::FREQ_YEARLY => $date->copy()->addYear(),
            default => $date->copy()->addDay(),
        };
    }
}

