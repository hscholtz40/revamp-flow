<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync customers to Xero';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting customer sync to Xero for all companies...');
        
        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;
        
        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            if (!$settings->sync_customers_to_xero && !$settings->sync_customers_from_xero) {
                $this->info("Customer sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            $this->info("Syncing customers for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                
                // Sync customers FROM Xero first (import existing)
                if ($settings->sync_customers_from_xero) {
                    $this->info("  Importing customers from Xero...");
                    $fromResults = $xeroService->syncCustomersFromXero();
                    
                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} customers, updated {$updatedCount} customers, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} customers and updated {$updatedCount} customers from Xero.");
                        }
                    }
                }
                
                // Sync customers TO Xero (export to Xero)
                if ($settings->sync_customers_to_xero) {
                    $this->info("  Syncing customers to Xero...");
                    $toResults = $xeroService->syncCustomersToXero();
                    
                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} customers successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} customers to Xero.");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync customers for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero customer sync enabled.');
        } else {
            $this->info("Customer sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
