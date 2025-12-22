<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Xero token refresh to run every 20 minutes to prevent expiration
Schedule::command('xero:refresh-tokens')->cron('*/20 * * * *');

// Schedule Xero sync commands to run every minute
Schedule::command('xero:sync-customers')->everyMinute();
Schedule::command('xero:sync-products')->everyMinute();
Schedule::command('xero:sync-invoices')->everyMinute();

// Schedule automated reminders to run daily at 9 AM
Schedule::command('reminders:send')->dailyAt('09:00');
