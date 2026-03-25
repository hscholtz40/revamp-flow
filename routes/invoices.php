<?php

use App\Http\Controllers\InvoicesController;
use App\Http\Controllers\PaymentsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/invoices/pos', [InvoicesController::class, 'pos'])->middleware('module.permission:invoices,create')->name('invoices.pos');
    Route::post('/invoices/pos', [InvoicesController::class, 'storePos'])->middleware('module.permission:invoices,create')->name('invoices.store-pos');
    Route::get('/invoices', [InvoicesController::class, 'index'])->middleware('module.permission:invoices,view')->name('invoices.index');
    Route::get('/invoices/create', [InvoicesController::class, 'create'])->middleware('module.permission:invoices,create')->name('invoices.create');
    Route::post('/invoices', [InvoicesController::class, 'store'])->middleware('module.permission:invoices,create')->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoicesController::class, 'show'])->middleware('module.permission:invoices,view')->name('invoices.show');
    Route::get('/invoices/{invoice}/edit', [InvoicesController::class, 'edit'])->middleware('module.permission:invoices,edit')->name('invoices.edit');
    Route::put('/invoices/{invoice}', [InvoicesController::class, 'update'])->middleware('module.permission:invoices,edit')->name('invoices.update');
    Route::delete('/invoices/{invoice}', [InvoicesController::class, 'destroy'])->middleware('module.permission:invoices,delete')->name('invoices.destroy');

    // Additional routes
    Route::patch('/invoices/{invoice}/status', [InvoicesController::class, 'updateStatus'])->middleware('module.permission:invoices,edit')->name('invoices.update-status');
    Route::get('/invoices/{invoice}/download-pdf', [InvoicesController::class, 'downloadPdf'])->middleware('module.permission:invoices,view')->name('invoices.download-pdf');
    Route::get('/invoices/{invoice}/print-pdf', [InvoicesController::class, 'printPdf'])->middleware('module.permission:invoices,view')->name('invoices.print-pdf');
    Route::post('/invoices/{invoice}/email', [InvoicesController::class, 'email'])->middleware('module.permission:invoices,view')->name('invoices.email');
    Route::post('/invoices/{invoice}/sign', [InvoicesController::class, 'sign'])->middleware('module.permission:invoices,edit')->name('invoices.sign');

    // Payment routes
    Route::post('/payments', [PaymentsController::class, 'store'])->middleware('module.permission:invoices,view')->name('payments.store');
    Route::delete('/payments/{payment}', [PaymentsController::class, 'destroy'])->middleware('module.permission:invoices,edit')->name('payments.destroy');

});
