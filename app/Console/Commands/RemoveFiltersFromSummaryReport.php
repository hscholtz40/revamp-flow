<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Console\Command;

class RemoveFiltersFromSummaryReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:remove-filters-from-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove filters from Summary Report template';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Try to find report by exact name
        $report = Report::where('name', 'Summary Report')->first();

        // If not found, try case-insensitive search
        if (!$report) {
            $report = Report::whereRaw('LOWER(name) = ?', ['summary report'])->first();
        }

        // If still not found, try partial match
        if (!$report) {
            $report = Report::where('name', 'like', '%Summary%')->first();
        }

        if (!$report) {
            $this->error('Summary Report not found.');
            $this->info('Available reports:');
            Report::select('id', 'name')->get()->each(function ($r) {
                $this->line("  - {$r->name} (ID: {$r->id})");
            });
            return 1;
        }

        $this->info("Found report: {$report->name} (ID: {$report->id})");

        if (!$report->template) {
            $this->error('Summary Report has no associated template.');
            return 1;
        }

        $this->info("Found template: {$report->template->name} (ID: {$report->template->id})");

        $report->template->update(['filters' => null]);

        $this->info('Filters removed from Summary Report template successfully.');

        return 0;
    }
}
