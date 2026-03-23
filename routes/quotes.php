<?php

use App\Http\Controllers\QuotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/quotes', [QuotesController::class, 'index'])->middleware('module.permission:quotes,view')->name('quotes.index');
    Route::get('/quotes/create', [QuotesController::class, 'create'])->middleware('module.permission:quotes,create')->name('quotes.create');
    Route::post('/quotes', [QuotesController::class, 'store'])->middleware('module.permission:quotes,create')->name('quotes.store');
    Route::get('/quotes/{quote}', [QuotesController::class, 'show'])->middleware('module.permission:quotes,view')->name('quotes.show');
    Route::get('/quotes/{quote}/edit', [QuotesController::class, 'edit'])->middleware('module.permission:quotes,edit')->name('quotes.edit');
    Route::put('/quotes/{quote}', [QuotesController::class, 'update'])->middleware('module.permission:quotes,edit')->name('quotes.update');
    Route::delete('/quotes/{quote}', [QuotesController::class, 'destroy'])->middleware('module.permission:quotes,delete')->name('quotes.destroy');

    Route::post('/quotes/{quote}/convert-to-jobcard', [QuotesController::class, 'convertToJobcard'])
        ->middleware('module.permission:quotes,edit')
        ->name('quotes.convert-to-jobcard');

    Route::post('/quotes/{quote}/convert-to-invoice', [QuotesController::class, 'convertToInvoice'])
        ->middleware('module.permission:quotes,edit')
        ->name('quotes.convert-to-invoice');

    Route::get('/quotes/{quote}/download-pdf', [QuotesController::class, 'downloadPDF'])
        ->middleware('module.permission:quotes,view')
        ->name('quotes.download-pdf');

    Route::post('/quotes/{quote}/email', [QuotesController::class, 'emailQuote'])
        ->middleware('module.permission:quotes,edit')
        ->name('quotes.email');

    Route::patch('/quotes/{quote}/status', [QuotesController::class, 'updateStatus'])
        ->middleware('module.permission:quotes,edit')
        ->name('quotes.update-status');
});
