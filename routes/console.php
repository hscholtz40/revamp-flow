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
// Keep invoice + payment export responsive; run lower-churn datasets less frequently.
Schedule::command('xero:sync-invoices')->everyTwoMinutes()->withoutOverlapping();
Schedule::command('xero:sync-invoices-from-xero')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('xero:sync-customers')->everyTenMinutes()->withoutOverlapping();
Schedule::command('xero:sync-products')->everyTenMinutes()->withoutOverlapping();
Schedule::command('xero:sync-suppliers')->everyTenMinutes()->withoutOverlapping();
Schedule::command('xero:sync-quotes')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('xero:sync-credit-notes')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('xero:sync-purchase-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('xero:sync-payments')->everyTwoMinutes()->withoutOverlapping();

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
