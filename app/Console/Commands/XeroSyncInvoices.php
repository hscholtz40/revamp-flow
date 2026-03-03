<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync invoices to Xero';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting invoice sync to Xero for all companies...');
        
        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;
        
        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            if (!$settings->sync_invoices_to_xero) {
                $this->info("Invoice sync to Xero is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            $this->info("Syncing invoices for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                $results = $xeroService->syncInvoicesToXero($settings->company);
                
                if (isset($results['skipped']) && $results['skipped']) {
                    $this->info("  {$results['message']}");
                    continue;
                }
                
                $successCount = collect($results)->where('status', 'success')->count();
                $errorCount = collect($results)->where('status', 'error')->count();
                
                $totalSuccess += $successCount;
                $totalErrors += $errorCount;
                
                if ($errorCount > 0) {
                    $this->warn("  Synced {$successCount} invoices successfully, {$errorCount} failed.");
                } else {
                    $this->info("  Successfully synced {$successCount} invoices to Xero.");
                }
                
                // Sync payments FROM Xero (created in the last hour)
                $this->info("  Syncing payments from Xero...");
                try {
                    $paymentSyncFromXero = $xeroService->syncPaymentsFromXero();
                    
                    // Check if sync was skipped entirely (boolean true)
                    if (isset($paymentSyncFromXero['skipped']) && $paymentSyncFromXero['skipped'] === true) {
                        $message = $paymentSyncFromXero['message'] ?? 'Payment sync skipped';
                        $this->info("  {$message}");
                    } else {
                        // Normal return with counts
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
                
                // Payment export is intentionally handled by xero:sync-payments
                // to avoid duplicate processing from overlapping scheduler paths.
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync invoices for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero invoice sync enabled.');
        } else {
            $this->info("Invoice sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
