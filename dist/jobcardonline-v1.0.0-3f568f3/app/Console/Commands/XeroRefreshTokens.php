<?php

namespace App\Console\Commands;

use App\Services\XeroService;
use Illuminate\Console\Command;

class XeroRefreshTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xero:refresh-tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Xero tokens for all companies to prevent expiration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Refreshing Xero tokens for all companies...');
        
        $results = XeroService::refreshAllTokens();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($results as $result) {
            if ($result['success']) {
                $this->info("✓ Token refreshed for: {$result['company_name']}");
                $successCount++;
            } else {
                $this->error("✗ Failed to refresh token for: {$result['company_name']}");
                if (isset($result['error'])) {
                    $this->error("  Error: {$result['error']}");
                }
                $errorCount++;
            }
        }
        
        if (count($results) === 0) {
            $this->info('No companies have Xero configured.');
        } else {
            $this->info("Token refresh completed. Success: {$successCount}, Errors: {$errorCount}");
        }
        
        return 0;
    }
}
