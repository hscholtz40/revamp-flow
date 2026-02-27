<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncSuppliers extends Command
{
    protected $signature = 'xero:sync-suppliers';

    protected $description = 'Sync suppliers to and from Xero';

    public function handle()
    {
        $this->info('Starting supplier sync with Xero for all companies...');

        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;

        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }

            if (!$settings->sync_suppliers_to_xero && !$settings->sync_suppliers_from_xero) {
                $this->info("Supplier sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }

            $this->info("Syncing suppliers for company: {$settings->company->name}...");
            $companiesProcessed++;

            try {
                $xeroService = new XeroService($settings->company);

                if ($settings->sync_suppliers_from_xero) {
                    $this->info("  Importing suppliers from Xero...");
                    $fromResults = $xeroService->syncSuppliersFromXero();

                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();

                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} suppliers, updated {$updatedCount} suppliers, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} suppliers and updated {$updatedCount} suppliers from Xero.");
                        }
                    }
                }

                if ($settings->sync_suppliers_to_xero) {
                    $this->info("  Syncing suppliers to Xero...");
                    $toResults = $xeroService->syncSuppliersToXero();

                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();

                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} suppliers successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} suppliers to Xero.");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Failed to sync suppliers for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }

        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero supplier sync enabled.');
        } else {
            $this->info("Supplier sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }

        return 0;
    }
}
