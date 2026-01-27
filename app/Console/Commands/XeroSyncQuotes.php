<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncQuotes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-quotes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync quotes to Xero';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting quote sync to Xero for all companies...');
        
        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;
        
        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            if (!$settings->sync_quotes_to_xero && !$settings->sync_quotes_from_xero) {
                $this->info("Quote sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            $this->info("Syncing quotes for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                
                // Sync quotes FROM Xero first (import existing)
                if ($settings->sync_quotes_from_xero) {
                    $this->info("  Importing quotes from Xero...");
                    $fromResults = $xeroService->syncQuotesFromXero();
                    
                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} quotes, updated {$updatedCount} quotes, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} quotes and updated {$updatedCount} quotes from Xero.");
                        }
                    }
                }
                
                // Sync quotes TO Xero (export to Xero)
                if ($settings->sync_quotes_to_xero) {
                    $this->info("  Syncing quotes to Xero...");
                    $toResults = $xeroService->syncQuotesToXero();
                    
                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} quotes successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} quotes to Xero.");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync quotes for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero quote sync enabled.');
        } else {
            $this->info("Quote sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
