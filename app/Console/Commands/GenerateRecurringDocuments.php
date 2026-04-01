<?php

namespace App\Console\Commands;

use App\Services\RecurringDocumentService;
use Illuminate\Console\Command;

class GenerateRecurringDocuments extends Command
{
    protected $signature = 'recurring:generate-documents';

    protected $description = 'Generate due recurring jobcards and invoices';

    public function handle(RecurringDocumentService $service): int
    {
        $result = $service->generateDueDocuments();
        $this->info("Recurring generation complete. Created: {$result['created']}, Failed: {$result['failed']}");

        return self::SUCCESS;
    }
}

