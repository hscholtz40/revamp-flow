<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products to Xero';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting product sync to Xero for all companies...');
        
        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;
        
        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            if (!$settings->sync_products_to_xero && !$settings->sync_products_from_xero) {
                $this->info("Product sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }
            
            $this->info("Syncing products for company: {$settings->company->name}...");
            $companiesProcessed++;
            
            try {
                $xeroService = new XeroService($settings->company);
                
                // Sync products FROM Xero first (import existing)
                if ($settings->sync_products_from_xero) {
                    $this->info("  Importing products from Xero...");
                    $fromResults = $xeroService->syncProductsFromXero();
                    
                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} products, updated {$updatedCount} products, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} products and updated {$updatedCount} products from Xero.");
                        }
                    }
                }
                
                // Sync products TO Xero (export to Xero)
                if ($settings->sync_products_to_xero) {
                    $this->info("  Syncing products to Xero...");
                    $toResults = $xeroService->syncProductsToXero();
                    
                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();
                        
                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;
                        
                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} products successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} products to Xero.");
                        }
                    }
                }
                
            } catch (\Exception $e) {
                $this->error("  Failed to sync products for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }
        
        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero product sync enabled.');
        } else {
            $this->info("Product sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }
        
        return 0;
    }
}
