<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Middleware\EnsureNotInstalled;

Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->isClientUser()) {
            return redirect()->route('client-zone.dashboard');
        }

        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/customers.php';
require __DIR__.'/contacts.php';
require __DIR__.'/products.php';
require __DIR__.'/categories.php';
require __DIR__.'/jobcards.php';
require __DIR__.'/quotes.php';
require __DIR__.'/invoices.php';
require __DIR__.'/credit-notes.php';
require __DIR__.'/users.php';
require __DIR__.'/groups.php';
require __DIR__.'/administration.php';
require __DIR__.'/company-settings.php';
require __DIR__.'/xero.php';
require __DIR__.'/suppliers.php';
require __DIR__.'/stock-movements.php';
require __DIR__.'/purchase-orders.php';
require __DIR__.'/time-entries.php';
require __DIR__.'/messages.php';
require __DIR__.'/dispatch.php';
require __DIR__.'/tasks.php';
require __DIR__.'/audit-logs.php';
require __DIR__.'/backups.php';
require __DIR__.'/tax-rates.php';
require __DIR__.'/chart-of-accounts.php';
require __DIR__.'/bank-accounts.php';
require __DIR__.'/reports.php';
require __DIR__.'/teams.php';
require __DIR__.'/licenses.php';
require __DIR__.'/list-view-preferences.php';
require __DIR__.'/notes.php';
require __DIR__.'/client-zone.php';
require __DIR__.'/registered-users.php';

// Installer routes (available only before first install)
Route::middleware([EnsureNotInstalled::class])->group(function () {
    Route::get('/install', [App\Http\Controllers\InstallerController::class, 'show'])->name('install.show');
    Route::post('/install', [App\Http\Controllers\InstallerController::class, 'install'])->name('install.perform');
});
