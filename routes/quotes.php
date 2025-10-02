<?php

use App\Http\Controllers\QuotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('quotes', QuotesController::class)->middleware('module.permission:quotes,view');
    
    // Additional routes
    Route::post('/quotes/{quote}/convert-to-jobcard', [QuotesController::class, 'convertToJobcard'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.convert-to-jobcard');
    
    Route::post('/quotes/{quote}/convert-to-invoice', [QuotesController::class, 'convertToInvoice'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.convert-to-invoice');
    
    Route::get('/quotes/{quote}/download-pdf', [QuotesController::class, 'downloadPDF'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.download-pdf');
    
    Route::post('/quotes/{quote}/email', [QuotesController::class, 'emailQuote'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.email');
    
    Route::patch('/quotes/{quote}/status', [QuotesController::class, 'updateStatus'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.update-status');
});
