<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncInvoicesFromXero extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-invoices-from-xero';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import invoices from Xero to local database (one-off operation)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting invoice import from Xero for all companies...');
        
        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;
        
        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            if (!$settings->sync_invoices_from_xero) {
                $this->info("Invoice sync from Xero is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            $this->info("Importing invoices for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                
                $this->info("  Importing invoices from Xero...");
                $results = $xeroService->syncInvoicesFromXero();
                
                if (isset($results['skipped']) && $results['skipped']) {
                    $this->info("  {$results['message']}");
                } else {
                    $createdCount = collect($results)->where('status', 'created')->count();
                    $updatedCount = collect($results)->where('status', 'updated')->count();
                    $errorCount = collect($results)->where('status', 'error')->count();
                    
                    $totalSuccess += $createdCount + $updatedCount;
                    $totalErrors += $errorCount;
                    
                    if ($errorCount > 0) {
                        $this->warn("  Imported {$createdCount} invoices, updated {$updatedCount} invoices, {$errorCount} failed.");
                    } else {
                        $this->info("  Successfully imported {$createdCount} invoices and updated {$updatedCount} invoices from Xero.");
                    }
                }

                $this->info("  Syncing payments from Xero...");
                try {
                    $paymentSyncFromXero = $xeroService->syncPaymentsFromXero();

                    if (isset($paymentSyncFromXero['skipped']) && $paymentSyncFromXero['skipped'] === true) {
                        $message = $paymentSyncFromXero['message'] ?? 'Payment sync skipped';
                        $this->info("  {$message}");
                    } else {
                        $createdPayments = $paymentSyncFromXero['created'] ?? 0;
                        $skippedPayments = is_numeric($paymentSyncFromXero['skipped'] ?? null) ? $paymentSyncFromXero['skipped'] : 0;
                        $paymentErrors = $paymentSyncFromXero['errors'] ?? 0;

                        if ($paymentErrors > 0) {
                            $this->warn("  Imported {$createdPayments} payments from Xero, {$skippedPayments} skipped, {$paymentErrors} failed.");
                        } elseif ($createdPayments > 0 || $skippedPayments > 0) {
                            $this->info("  Imported {$createdPayments} payments from Xero" . ($skippedPayments > 0 ? ", {$skippedPayments} skipped" : '') . ".");
                        } else {
                            $this->info("  No new payments found in Xero from the last hour.");
                        }
                    }
                } catch (\Exception $e) {
                    $this->warn("  Failed to sync payments from Xero: " . $e->getMessage());
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to import invoices for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero invoice sync from Xero enabled.');
        } else {
            $this->info("Invoice import completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
