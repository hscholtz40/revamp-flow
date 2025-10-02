<?php

use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('invoices', InvoicesController::class)->middleware('module.permission:invoices,view');
    
    // Additional routes
    Route::patch('/invoices/{invoice}/status', [InvoicesController::class, 'updateStatus'])->middleware('module.permission:invoices,view')->name('invoices.update-status');
    Route::get('/invoices/{invoice}/download-pdf', [InvoicesController::class, 'downloadPdf'])->middleware('module.permission:invoices,view')->name('invoices.download-pdf');
    Route::post('/invoices/{invoice}/email', [InvoicesController::class, 'email'])->middleware('module.permission:invoices,view')->name('invoices.email');
    
    // Payment routes
    Route::post('/payments', [PaymentsController::class, 'store'])->middleware('module.permission:invoices,edit')->name('payments.store');
    Route::delete('/payments/{payment}', [PaymentsController::class, 'destroy'])->middleware('module.permission:invoices,edit')->name('payments.destroy');
    
});
