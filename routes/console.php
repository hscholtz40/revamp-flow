<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Xero token refresh to run every 20 minutes to prevent expiration
Schedule::command('xero:refresh-tokens')->cron('*/20 * * * *');

// Stagger Xero sync commands to reduce burst traffic and daily API pressure.
Schedule::command('xero:sync-invoices')->cron('*/2 * * * *')->withoutOverlapping(10);
Schedule::command('xero:sync-invoices-from-xero')->cron('*/5 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-customers')->cron('1-59/10 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-products')->cron('2-59/10 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-suppliers')->cron('3-59/10 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-quotes')->cron('4-59/10 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-credit-notes')->cron('5-59/10 * * * *')->withoutOverlapping(20);
Schedule::command('xero:sync-purchase-orders')->cron('6-59/10 * * * *')->withoutOverlapping(20);

// Schedule automated reminders to run daily at 9 AM
Schedule::command('reminders:send')->dailyAt('09:00');

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
