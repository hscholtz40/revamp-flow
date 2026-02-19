<?php

namespace App\Console\Commands;

use App\Models\XeroSettings;
use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroSyncCreditNotes extends Command
{
    protected $signature = 'xero:sync-credit-notes';

    protected $description = 'Sync credit notes to and from Xero';

    public function handle()
    {
        $this->info('Starting credit note sync with Xero for all companies...');

        $allSettings = XeroSettings::getAllCompanies();
        $totalSuccess = 0;
        $totalErrors = 0;
        $companiesProcessed = 0;

        foreach ($allSettings as $settings) {
            if (!$settings->isConfigured()) {
                $this->info("Xero is not configured for company: {$settings->company->name}. Skipping.");
                continue;
            }

            if (!$settings->sync_credit_notes_to_xero && !$settings->sync_credit_notes_from_xero) {
                $this->info("Credit note sync is disabled for company: {$settings->company->name}. Skipping.");
                continue;
            }

            $this->info("Syncing credit notes for company: {$settings->company->name}...");
            $companiesProcessed++;

            try {
                $xeroService = new XeroService($settings->company);

                if ($settings->sync_credit_notes_from_xero) {
                    $this->info("  Importing credit notes from Xero...");
                    $fromResults = $xeroService->syncCreditNotesFromXero();

                    if (isset($fromResults['skipped']) && $fromResults['skipped']) {
                        $this->info("  {$fromResults['message']}");
                    } else {
                        $createdCount = collect($fromResults)->where('status', 'created')->count();
                        $updatedCount = collect($fromResults)->where('status', 'updated')->count();
                        $errorCount = collect($fromResults)->where('status', 'error')->count();

                        $totalSuccess += $createdCount + $updatedCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Imported {$createdCount} credit notes, updated {$updatedCount}, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully imported {$createdCount} credit notes and updated {$updatedCount} from Xero.");
                        }
                    }
                }

                if ($settings->sync_credit_notes_to_xero) {
                    $this->info("  Syncing credit notes to Xero...");
                    $toResults = $xeroService->syncCreditNotesToXero();

                    if (isset($toResults['skipped']) && $toResults['skipped']) {
                        $this->info("  {$toResults['message']}");
                    } else {
                        $successCount = collect($toResults)->where('status', 'success')->count();
                        $errorCount = collect($toResults)->where('status', 'error')->count();

                        $totalSuccess += $successCount;
                        $totalErrors += $errorCount;

                        if ($errorCount > 0) {
                            $this->warn("  Synced {$successCount} credit notes successfully, {$errorCount} failed.");
                        } else {
                            $this->info("  Successfully synced {$successCount} credit notes to Xero.");
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("  Failed to sync credit notes for {$settings->company->name}: " . $e->getMessage());
                $totalErrors++;
            }
        }

        if ($companiesProcessed === 0) {
            $this->info('No companies have Xero credit note sync enabled.');
        } else {
            $this->info("Credit note sync completed. Total: {$totalSuccess} successful, {$totalErrors} failed across {$companiesProcessed} companies.");
        }

        return 0;
    }
}
