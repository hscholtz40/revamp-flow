<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync payments to Xero';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting payment sync to Xero for all companies...');
        
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
            
            $this->info("Syncing payments for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                $results = $xeroService->syncAllPaymentsToXero();
                
                if (isset($results['skipped']) && $results['skipped']) {
                    $this->info("  {$results['message']}");
                    continue;
                }
                
                $totalPayments = 0;
                $paymentErrors = 0;
                $paymentSkipped = 0;
                
                foreach ($results as $invoiceResult) {
                    if (isset($invoiceResult['payments'])) {
                        foreach ($invoiceResult['payments'] as $payment) {
                            $totalPayments++;
                            if ($payment['status'] === 'error') {
                                $paymentErrors++;
                            } elseif ($payment['status'] === 'skipped') {
                                $paymentSkipped++;
                            }
                        }
                    }
                }
                
                $paymentSuccess = $totalPayments - $paymentErrors - $paymentSkipped;
                $totalSuccess += $paymentSuccess;
                $totalErrors += $paymentErrors;
                
                if ($paymentErrors > 0 || $paymentSkipped > 0) {
                    $message = "  Synced {$paymentSuccess} payments successfully";
                    if ($paymentSkipped > 0) {
                        $message .= ", {$paymentSkipped} skipped (already paid in Xero)";
                    }
                    if ($paymentErrors > 0) {
                        $message .= ", {$paymentErrors} failed";
                    }
                    $message .= ".";
                    $this->warn($message);
                } else {
                    $this->info("  Successfully synced {$totalPayments} payments to Xero.");
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync payments for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero invoice sync enabled.');
        } else {
            $this->info("Payment sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
