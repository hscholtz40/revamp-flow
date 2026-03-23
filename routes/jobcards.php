<?php

use App\Http\Controllers\JobcardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/jobcards', [JobcardController::class, 'index'])->middleware('module.permission:jobcards,list')->name('jobcards.index');
    Route::get('/jobcards/create', [JobcardController::class, 'create'])->middleware('module.permission:jobcards,create')->name('jobcards.create');
    Route::post('/jobcards', [JobcardController::class, 'store'])->middleware('module.permission:jobcards,create')->name('jobcards.store');
    Route::get('/jobcards/{jobcard}', [JobcardController::class, 'show'])->middleware('module.permission:jobcards,view')->name('jobcards.show');
    Route::get('/jobcards/{jobcard}/edit', [JobcardController::class, 'edit'])->middleware('module.permission:jobcards,edit')->name('jobcards.edit');
    Route::put('/jobcards/{jobcard}', [JobcardController::class, 'update'])->middleware('module.permission:jobcards,edit')->name('jobcards.update');
    Route::patch('/jobcards/{jobcard}/status', [JobcardController::class, 'updateStatus'])->middleware('module.permission:jobcards,edit')->name('jobcards.update-status');
    Route::get('/jobcards/{jobcard}/print', [JobcardController::class, 'print'])->middleware('module.permission:jobcards,view')->name('jobcards.print');
    Route::post('/jobcards/{jobcard}/email', [JobcardController::class, 'email'])->middleware('module.permission:jobcards,edit')->name('jobcards.email');
    Route::post('/jobcards/{jobcard}/sign', [JobcardController::class, 'sign'])->middleware('module.permission:jobcards,edit')->name('jobcards.sign');
    Route::post('/jobcards/{jobcard}/convert-to-quote', [JobcardController::class, 'convertToQuote'])->middleware('module.permission:jobcards,edit')->name('jobcards.convert-to-quote');
    Route::post('/jobcards/{jobcard}/convert-to-invoice', [JobcardController::class, 'convertToInvoice'])->middleware('module.permission:jobcards,edit')->name('jobcards.convert-to-invoice');
    Route::delete('/jobcards/{jobcard}', [JobcardController::class, 'destroy'])->middleware('module.permission:jobcards,delete')->name('jobcards.destroy');
});
