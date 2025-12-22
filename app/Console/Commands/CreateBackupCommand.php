<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\BackupService;
use Illuminate\Console\Command;

class CreateBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:create {--schedule= : The ID of the schedule to run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a backup (manual or scheduled)';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $scheduleId = $this->option('schedule');

        if ($scheduleId) {
            // Run scheduled backup
            $schedule = BackupSchedule::find($scheduleId);
            
            if (!$schedule) {
                $this->error("Schedule {$scheduleId} not found");
                return Command::FAILURE;
            }

            if (!$schedule->is_active) {
                $this->info("Schedule {$scheduleId} is not active, skipping");
                return Command::SUCCESS;
            }

            $this->info("Creating backup for schedule: {$schedule->name}");

            try {
                $backup = $backupService->createBackup(
                    null, // System-wide backup
                    null,
                    'scheduled',
                    $schedule->storage_type
                );

                $schedule->update([
                    'last_run_at' => now(),
                ]);
                $schedule->calculateNextRun();

                // Cleanup old backups based on retention policy
                $backupService->cleanupOldBackups($schedule->retention_days);

                $this->info("Backup created successfully: {$backup->file_name}");
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error("Failed to create backup: " . $e->getMessage());
                return Command::FAILURE;
            }
        } else {
            // Manual backup
            $this->info('Creating manual backup...');

            try {
                $backup = $backupService->createBackup();
                $this->info("Backup created successfully: {$backup->file_name}");
                return Command::SUCCESS;
            } catch (\Exception $e) {
                $this->error("Failed to create backup: " . $e->getMessage());
                return Command::FAILURE;
            }
        }
    }
}
