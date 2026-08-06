<?php

namespace App\Console\Commands;

use App\Services\LicenseBillingService;
use Illuminate\Console\Command;

class GenerateLicenseInvoices extends Command
{
    protected $signature = 'licenses:generate-invoices';

    protected $description = 'Generate due monthly/annual license invoices and email customers';

    public function handle(LicenseBillingService $service): int
    {
        $result = $service->generateDueInvoices();
        $this->info(
            "License invoice generation complete. Created: {$result['created']}, "
            ."Emailed: {$result['emailed']}, Skipped: {$result['skipped']}, Failed: {$result['failed']}"
        );

        return self::SUCCESS;
    }
}
