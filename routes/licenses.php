<?php

use App\Http\Controllers\LicenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'licensing'])->group(function () {
    Route::get('/licenses', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('/licenses/create', [LicenseController::class, 'create'])->name('licenses.create');
    Route::post('/licenses', [LicenseController::class, 'store'])->name('licenses.store');
    Route::get('/licenses/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    Route::get('/licenses/{license}/edit', [LicenseController::class, 'edit'])->name('licenses.edit');
    Route::put('/licenses/{license}', [LicenseController::class, 'update'])->name('licenses.update');
    Route::delete('/licenses/{license}', [LicenseController::class, 'destroy'])->name('licenses.destroy');
    Route::post('/licenses/{license}/deploy', [LicenseController::class, 'deploy'])
        ->middleware('license.infrastructure')
        ->name('licenses.deploy');
    Route::post('/licenses/{license}/upgrade', [LicenseController::class, 'upgrade'])
        ->middleware('license.infrastructure')
        ->name('licenses.upgrade');
    Route::post('/licenses/{license}/force-ssl', [LicenseController::class, 'forceSSL'])
        ->middleware('license.infrastructure')
        ->name('licenses.forceSSL');
});
