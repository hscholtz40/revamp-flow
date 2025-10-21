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
                $results = $xeroService->syncInvoicesToXero();
                
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
                
                // Also sync payments for invoices that have been synced
                $this->info("  Syncing payments to Xero...");
                $paymentResults = $xeroService->syncAllPaymentsToXero();
                
                if (isset($paymentResults['skipped']) && $paymentResults['skipped']) {
                    $this->info("  {$paymentResults['message']}");
                } else {
                    $totalPayments = 0;
                    $paymentErrors = 0;
                    
                    foreach ($paymentResults as $invoiceResult) {
                        if (isset($invoiceResult['payments'])) {
                            foreach ($invoiceResult['payments'] as $payment) {
                                $totalPayments++;
                                if ($payment['status'] === 'error') {
                                    $paymentErrors++;
                                }
                            }
                        }
                    }
                    
                    if ($paymentErrors > 0) {
                        $this->warn("  Synced " . ($totalPayments - $paymentErrors) . " payments successfully, {$paymentErrors} failed.");
                    } else {
                        $this->info("  Successfully synced {$totalPayments} payments to Xero.");
                    }
                }
                
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
