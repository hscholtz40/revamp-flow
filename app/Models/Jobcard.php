<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Jobcard extends Model
{
    use HasFactory, Auditable;

    protected static function booted(): void
    {
        static::created(function (Jobcard $jobcard) {
            JobcardStatusTransition::create([
                'jobcard_id' => $jobcard->id,
                'from_status' => null,
                'to_status' => $jobcard->status ?? 'draft',
                'transitioned_at' => now(),
                'user_id' => auth()->id(),
            ]);
        });

        static::updating(function (Jobcard $jobcard) {
            if ($jobcard->isDirty('status')) {
                JobcardStatusTransition::create([
                    'jobcard_id' => $jobcard->id,
                    'from_status' => $jobcard->getOriginal('status'),
                    'to_status' => $jobcard->status,
                    'transitioned_at' => now(),
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }

    protected $fillable = [
        'company_id',
        'customer_id',
        'assigned_to_user_id',
        'assigned_to_team_id',
        'invoice_id',
        'job_number',
        'title',
        'description',
        'status',
        'start_date',
        'due_date',
        'completed_date',
        'subtotal',
        'discount_amount',
        'discount_percentage',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'completed_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'formatted_total',
        'status_color',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(JobcardLineItem::class)->orderBy('sort_order');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderBy('date', 'desc')->orderBy('start_time', 'desc');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'assigned_to_team_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function statusTransitions(): HasMany
    {
        return $this->hasMany(JobcardStatusTransition::class)->orderBy('transitioned_at');
    }

    /**
     * Calculate how long the jobcard spent in each status (in minutes).
     *
     * @return array<string, int> e.g. ['draft' => 120, 'pending' => 4320, ...]
     */
    public function getStatusDurations(): array
    {
        $transitions = $this->statusTransitions()
            ->orderBy('transitioned_at')
            ->get();

        if ($transitions->isEmpty()) {
            return [];
        }

        $durations = [];

        for ($i = 0; $i < $transitions->count(); $i++) {
            $current = $transitions[$i];
            $statusKey = $current->to_status;

            if ($i + 1 < $transitions->count()) {
                $next = $transitions[$i + 1];
                $minutes = (int) $current->transitioned_at->diffInMinutes($next->transitioned_at);
            } else {
                // Last transition — duration is until now (for active jobcards)
                // or until completed_date for completed ones
                if (in_array($this->status, ['completed', 'cancelled'])) {
                    $endTime = $this->completed_date
                        ? $this->completed_date->endOfDay()
                        : $current->transitioned_at;
                    $minutes = (int) $current->transitioned_at->diffInMinutes($endTime);
                } else {
                    $minutes = (int) $current->transitioned_at->diffInMinutes(now());
                }
            }

            if (!isset($durations[$statusKey])) {
                $durations[$statusKey] = 0;
            }
            $durations[$statusKey] += $minutes;
        }

        return $durations;
    }

    /**
     * Generate a unique job number
     */
    public static function generateJobNumber(int $companyId): string
    {
        $customNumber = DB::transaction(function () use ($companyId) {
            $company = Company::whereKey($companyId)->lockForUpdate()->first();
            if ($company && $company->jobcard_number_prefix !== null && $company->jobcard_number_next !== null) {
                $next = max(1, (int) $company->jobcard_number_next);
                $number = $company->jobcard_number_prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
                $company->jobcard_number_next = $next + 1;
                $company->save();

                return $number;
            }

            return null;
        });

        if ($customNumber !== null) {
            return $customNumber;
        }

        $prefix = 'JC';
        $year = date('Y');
        $month = date('m');
        
        // Get the last job number for this year/month
        $lastJob = static::where('job_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('job_number', 'desc')
            ->first();
        
        if ($lastJob && $lastJob->job_number) {
            $lastNumber = (int) substr($lastJob->job_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . $year . $month . str_pad((string)$newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculate totals from line items
     */
    public function calculateTotals(): void
    {
        $lineItems = $this->lineItems()->get();

        // Calculate total discount from line items
        $totalDiscount = $lineItems->sum(function ($item) {
            $quantity = $item->quantity ?? 0;
            $unitPrice = $item->unit_price ?? 0;
            $discountAmount = $item->discount_amount ?? 0;
            $discountPercentage = $item->discount_percentage ?? 0;
            
            $itemSubtotal = $quantity * $unitPrice;
            
            if ($discountPercentage > 0) {
                return $itemSubtotal * ($discountPercentage / 100);
            }
            
            return $discountAmount;
        });
        
        // Subtotal after discounts (sum of line item totals)
        $subtotal = $lineItems->sum('total') ?? 0;
        
        // Tax is now calculated per line item - sum all line item tax amounts
        $taxAmount = $lineItems->sum('tax_amount') ?? 0;
        $total = $subtotal + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $totalDiscount,
            'discount_percentage' => 0,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ]);
    }

    /**
     * Get formatted total
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'R' . number_format($this->total ?? 0, 2);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'in_progress' => 'blue',
            'completed' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    /**
     * Convert jobcard to invoice
     */
    public function convertToInvoice(): Invoice
    {
        // Ensure line items and time entries are loaded
        $this->load('lineItems', 'timeEntries.user');
        
        $invoice = Invoice::create([
            'company_id' => $this->company_id,
            'customer_id' => $this->customer_id,
            'invoice_number' => Invoice::generateInvoiceNumber($this->company_id),
            'title' => $this->title,
            'description' => $this->description,
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $this->subtotal,
            'discount_amount' => $this->discount_amount,
            'discount_percentage' => $this->discount_percentage,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'notes' => $this->notes,
            'terms' => $this->terms_conditions,
            'source_type' => 'jobcard',
            'source_id' => $this->id,
        ]);

        // Copy line items
        $sortOrder = 0;
        foreach ($this->lineItems as $lineItem) {
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $lineItem->product_id,
                'description' => $lineItem->description,
                'quantity' => $lineItem->quantity,
                'unit_price' => $lineItem->unit_price,
                'total' => $lineItem->total,
                'tax_rate_id' => $lineItem->tax_rate_id,
                'tax_amount' => $lineItem->tax_amount,
                'sort_order' => $sortOrder++,
            ]);
        }

        // Add billable time entries as line items
        // Filter for billable entries with hourly rates
        $billableTimeEntries = $this->timeEntries->filter(function ($entry) {
            return $entry->is_billable === true && $entry->hourly_rate !== null && $entry->hourly_rate > 0;
        });
        
        \Log::info('Jobcard conversion - time entries check', [
            'jobcard_id' => $this->id,
            'total_time_entries' => $this->timeEntries->count(),
            'billable_time_entries_count' => $billableTimeEntries->count(),
            'time_entries_details' => $this->timeEntries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'is_billable' => $entry->is_billable,
                    'hourly_rate' => $entry->hourly_rate,
                    'duration_minutes' => $entry->duration_minutes,
                ];
            })->toArray(),
        ]);
        
        if ($billableTimeEntries->isNotEmpty()) {
            // Group time entries by user and hourly rate
            $groupedEntries = $billableTimeEntries->groupBy(function ($entry) {
                return $entry->user_id . '_' . ($entry->hourly_rate ?? 0);
            });

            foreach ($groupedEntries as $group) {
                $firstEntry = $group->first();
                
                // Calculate total hours and amount for the group
                $totalMinutes = $group->sum('duration_minutes');
                $totalHours = round($totalMinutes / 60, 2);
                $hourlyRate = $firstEntry->hourly_rate ?? 0;
                $totalAmount = round($totalHours * $hourlyRate, 2);
                
                // Create description with user name and formatted duration
                $userName = $firstEntry->user->name ?? 'Unknown User';
                $hours = floor($totalMinutes / 60);
                $remainingMinutes = $totalMinutes % 60;
                $formattedDuration = '';
                if ($hours > 0 && $remainingMinutes > 0) {
                    $formattedDuration = "{$hours}h {$remainingMinutes}m";
                } elseif ($hours > 0) {
                    $formattedDuration = "{$hours}h";
                } else {
                    $formattedDuration = "{$remainingMinutes}m";
                }
                $description = "Time: {$userName} ({$formattedDuration} @ R" . number_format($hourlyRate, 2) . "/hr)";
                
                \Log::info('Creating invoice line item for time entry', [
                    'invoice_id' => $invoice->id,
                    'description' => $description,
                    'quantity' => $totalHours,
                    'unit_price' => $hourlyRate,
                    'total' => $totalAmount,
                    'sort_order' => $sortOrder,
                ]);
                
                $lineItem = InvoiceLineItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => null,
                    'description' => $description,
                    'quantity' => $totalHours,
                    'unit_price' => $hourlyRate,
                    'total' => $totalAmount,
                    'sort_order' => $sortOrder++,
                ]);
                
                \Log::info('Invoice line item created', [
                    'line_item_id' => $lineItem->id,
                    'invoice_id' => $invoice->id,
                ]);
            }
        } else {
            \Log::info('No billable time entries found for jobcard', [
                'jobcard_id' => $this->id,
                'total_time_entries' => $this->timeEntries->count(),
            ]);
        }

        // Recalculate invoice totals to include time entry line items
        $invoice->refresh();
        $invoice->load('lineItems');
        $invoice->calculateTotals();
        
        \Log::info('Invoice totals recalculated', [
            'invoice_id' => $invoice->id,
            'line_items_count' => $invoice->lineItems->count(),
            'subtotal' => $invoice->subtotal,
            'total' => $invoice->total,
        ]);

        return $invoice;
    }
}
