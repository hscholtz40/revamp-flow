<?php

use App\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/export', [AuditLogController::class, 'export'])->name('export');
        Route::get('/model/{modelType}/{modelId}', [AuditLogController::class, 'forModel'])->name('model');
        Route::get('/{auditLog}', [AuditLogController::class, 'show'])->name('show');
        Route::get('/model/{modelType}/{modelId}/versions', [AuditLogController::class, 'versions'])->name('versions');
        Route::post('/versions/{modelVersion}/restore', [AuditLogController::class, 'restoreVersion'])->name('versions.restore');
    });
});

