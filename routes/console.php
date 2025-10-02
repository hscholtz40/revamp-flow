<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Xero sync commands to run every minute
Schedule::command('xero:sync-customers')->everyMinute();
Schedule::command('xero:sync-products')->everyMinute();
Schedule::command('xero:sync-invoices')->everyMinute();
