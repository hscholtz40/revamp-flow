<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Xero token refresh to run every 20 minutes to prevent expiration
Schedule::command('xero:refresh-tokens')->cron('*/20 * * * *');

/*
 * Xero sync: run on a 15-minute cadence by default, with each job offset by 1 minute so
 * nine Artisan commands do not fire HTTP bursts in the same second.
 *
 * Important: server cron often runs `schedule:run` *every minute* — that only means Laravel
 * checks what is due. Each command below has its own minute list (e.g. credit notes at
 * :06, :21, :36, :51), not every minute. Use `php artisan schedule:list` to verify.
 *
 * Tune cadence via XERO_SYNC_BASE_MINUTES in .env (see config/services.php).
 */
$xeroSyncBaseMinutes = config('services.xero.sync_schedule_base_minutes', [0, 15, 30, 45]);

$xeroSyncCommands = [
    'xero:sync-invoices',
    'xero:sync-invoices-from-xero',
    'xero:sync-customers',
    'xero:sync-products',
    'xero:sync-suppliers',
    'xero:sync-quotes',
    'xero:sync-credit-notes',
    'xero:sync-purchase-orders',
    'xero:sync-payments',
];

foreach ($xeroSyncCommands as $index => $signature) {
    $minutes = array_map(
        static fn (int $m): int => ($m + $index) % 60,
        $xeroSyncBaseMinutes
    );
    sort($minutes);
    $minuteList = implode(',', $minutes);
    Schedule::command($signature)->cron("{$minuteList} * * * *")->withoutOverlapping(90);
}

// Schedule automated reminders to run daily at 9 AM
Schedule::command('reminders:send')->dailyAt('09:00');

// Generate recurring documents daily.
Schedule::command('recurring:generate-documents')->dailyAt('00:10');

// Schedule automated backups
Schedule::call(function () {
    $schedules = \App\Models\BackupSchedule::where('is_active', true)
        ->where('next_run_at', '<=', now())
        ->get();
    
    foreach ($schedules as $schedule) {
        \Illuminate\Support\Facades\Artisan::call('backup:create', [
            '--schedule' => $schedule->id,
        ]);
    }
})->everyMinute();
