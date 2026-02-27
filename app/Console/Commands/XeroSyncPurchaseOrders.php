<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncPurchaseOrders extends Command
{
    protected $signature = 'xero:sync-purchase-orders';

    protected $description = 'Sync purchase orders to and from Xero';

    public function handle()
    {
        $this->info('Starting purchase order sync with Xero for all companies...');

        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;

        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }

            if (!$settings->sync_purchase_orders_to_xero && !$settings->sync_purchase_orders_from_xero) {
                $this->info("Purchase order sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }

            $this->info("Syncing purchase orders for company: {$settings->company->name}...");
            $companiesProcessed++;

            try {
                $xeroService = new XeroService($settings->company);

                if ($settings->sync_purchase_orders_from_xero) {
                    $this->info("  Importing purchase orders from Xero...");
                    $fromResults = $xeroService->syncPurchaseOrdersFromXero();

                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();

                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} purchase orders, updated {$updatedCount}, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} purchase orders and updated {$updatedCount} from Xero.");
                        }
                    }
                }

                if ($settings->sync_purchase_orders_to_xero) {
                    $this->info("  Syncing purchase orders to Xero...");
                    $toResults = $xeroService->syncPurchaseOrdersToXero();

                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();

                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} purchase orders successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} purchase orders to Xero.");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Failed to sync purchase orders for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }

        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero purchase order sync enabled.');
        } else {
            $this->info("Purchase order sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }

        return 0;
    }
}
