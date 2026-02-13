<?php

use App\Http\Controllers\JobcardReportController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->middleware('module.permission:reports,list')->name('reports.index');
    Route::get('/reports/create', [ReportController::class, 'create'])->middleware('module.permission:reports,create')->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])->middleware('module.permission:reports,create')->name('reports.store');
    Route::post('/reports/save-template', [ReportController::class, 'saveTemplate'])->middleware('module.permission:reports,create')->name('reports.save-template');

    // Detailed Jobcard Report (must be before {report} wildcard)
    Route::get('/reports/jobcard-detail', [JobcardReportController::class, 'index'])->middleware('module.permission:reports,view')->name('reports.jobcard-detail');
    Route::get('/reports/jobcard-detail/export', [JobcardReportController::class, 'export'])->middleware('module.permission:reports,view')->name('reports.jobcard-detail.export');

    // Wildcard report routes
    Route::get('/reports/{report}', [ReportController::class, 'show'])->middleware('module.permission:reports,view')->name('reports.show');
    Route::get('/reports/{report}/edit', [ReportController::class, 'edit'])->middleware('module.permission:reports,edit')->name('reports.edit');
    Route::put('/reports/{report}', [ReportController::class, 'update'])->middleware('module.permission:reports,edit')->name('reports.update');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->middleware('module.permission:reports,delete')->name('reports.destroy');
    Route::get('/reports/{report}/data', [ReportController::class, 'getData'])->middleware('module.permission:reports,view')->name('reports.data');
    Route::get('/reports/{report}/export', [ReportController::class, 'export'])->middleware('module.permission:reports,view')->name('reports.export');

    // Template routes
    Route::get('/reports/templates/{template}/edit', [ReportController::class, 'editTemplate'])->middleware('module.permission:reports,edit')->name('reports.templates.edit');
    Route::put('/reports/templates/{template}', [ReportController::class, 'updateTemplate'])->middleware('module.permission:reports,edit')->name('reports.templates.update');
    Route::delete('/reports/templates/{template}', [ReportController::class, 'destroyTemplate'])->middleware('module.permission:reports,delete')->name('reports.templates.destroy');
});
