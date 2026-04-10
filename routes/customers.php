<?php

use App\Http\Controllers\CustomersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/customers', [CustomersController::class, 'index'])->middleware('module.permission:customers,list')->name('customers.index');
    Route::get('/customers/search', [CustomersController::class, 'search'])->middleware('module.permission:customers,list')->name('customers.search');
    Route::get('/customers/create', [CustomersController::class, 'create'])->middleware('module.permission:customers,create')->name('customers.create');
    Route::post('/customers', [CustomersController::class, 'store'])->middleware('module.permission:customers,create')->name('customers.store');
    Route::post('/customers/quick-create', [CustomersController::class, 'quickCreate'])->middleware('module.permission:customers,create')->name('customers.quickCreate');
    Route::get('/customers/{customer}', [CustomersController::class, 'show'])->middleware('module.permission:customers,view')->name('customers.show');
    Route::get('/customers/{customer}/statement-pdf', [CustomersController::class, 'downloadStatement'])->middleware(['module.permission:customers,view', 'module.permission:invoices,view'])->name('customers.statement.download-pdf');
    Route::get('/customers/{customer}/edit', [CustomersController::class, 'edit'])->middleware('module.permission:customers,edit')->name('customers.edit');
    Route::put('/customers/{customer}', [CustomersController::class, 'update'])->middleware('module.permission:customers,edit')->name('customers.update');
    Route::post('/customers/{customer}/set-default-sales', [CustomersController::class, 'setDefaultSales'])->middleware('module.permission:customers,edit')->name('customers.setDefaultSales');
    Route::delete('/customers/{customer}', [CustomersController::class, 'destroy'])->middleware('module.permission:customers,delete')->name('customers.destroy');
    Route::post('/customers/{customer}/send-sms', [CustomersController::class, 'sendSMS'])->middleware('module.permission:customers,view')->name('customers.sendSMS');
    Route::post('/customers/{customer}/send-email', [CustomersController::class, 'sendEmail'])->middleware('module.permission:customers,view')->name('customers.sendEmail');
});
