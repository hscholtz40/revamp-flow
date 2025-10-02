<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\SMSSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])->name('administration.index');
    
    // SMS Settings routes
    Route::get('/administration/sms-settings', [SMSSettingsController::class, 'index'])->name('sms-settings.index');
    Route::post('/administration/sms-settings', [SMSSettingsController::class, 'store'])->name('sms-settings.store');
    Route::put('/administration/sms-settings/{smsSettings}', [SMSSettingsController::class, 'update'])->name('sms-settings.update');
    Route::delete('/administration/sms-settings/{smsSettings}', [SMSSettingsController::class, 'destroy'])->name('sms-settings.destroy');
});
