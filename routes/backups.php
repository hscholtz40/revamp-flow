<?php

use App\Http\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/backups', [BackupController::class, 'store'])->name('backups.store');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
    Route::delete('/backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    
    Route::post('/backups/schedules', [BackupController::class, 'storeSchedule'])->name('backups.schedules.store');
    Route::put('/backups/schedules/{schedule}', [BackupController::class, 'updateSchedule'])->name('backups.schedules.update');
    Route::delete('/backups/schedules/{schedule}', [BackupController::class, 'destroySchedule'])->name('backups.schedules.destroy');
});

