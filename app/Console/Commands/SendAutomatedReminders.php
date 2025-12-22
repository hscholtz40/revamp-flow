<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendAutomatedReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automated reminders for overdue invoices and expiring quotes';

    /**
     * Execute the console command.
     */
    public function handle(ReminderService $reminderService)
    {
        $this->info('Processing automated reminders...');

        try {
            $reminderService->processAllReminders();
            $this->info('Automated reminders processed successfully.');
        } catch (\Exception $e) {
            $this->error('Error processing reminders: ' . $e->getMessage());
            \Log::error('Failed to process automated reminders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }

        return 0;
    }
}
