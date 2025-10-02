<?php

use App\Http\Controllers\CompanySettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/company-settings', [CompanySettingsController::class, 'index'])->name('company-settings.index');
    Route::get('/company-settings/create', [CompanySettingsController::class, 'create'])->name('company-settings.create');
    Route::post('/company-settings', [CompanySettingsController::class, 'store'])->name('company-settings.store');
    Route::get('/company-settings/{company}', [CompanySettingsController::class, 'show'])->name('company-settings.show');
    Route::get('/company-settings/{company}/edit', [CompanySettingsController::class, 'edit'])->name('company-settings.edit');
    Route::put('/company-settings/{company}', [CompanySettingsController::class, 'update'])->name('company-settings.update');
    Route::post('/company-settings/{company}/upload-logo', [CompanySettingsController::class, 'uploadLogo'])->name('company-settings.upload-logo');
    Route::delete('/company-settings/{company}', [CompanySettingsController::class, 'destroy'])->name('company-settings.destroy');
    Route::post('/company-settings/{company}/set-default', [CompanySettingsController::class, 'setDefault'])->name('company-settings.set-default');
    Route::post('/company-settings/{company}/switch', [CompanySettingsController::class, 'switch'])->name('company-settings.switch');
});
