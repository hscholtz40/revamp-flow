<?php

use App\Http\Controllers\JobcardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/jobcards', [JobcardController::class, 'index'])->middleware('module.permission:jobcards,list')->name('jobcards.index');
    Route::get('/jobcards/create', [JobcardController::class, 'create'])->middleware('module.permission:jobcards,create')->name('jobcards.create');
    Route::post('/jobcards/autosave', [JobcardController::class, 'autosaveStore'])->middleware('module.permission:jobcards,create')->name('jobcards.autosave.store');
    Route::post('/jobcards', [JobcardController::class, 'store'])->middleware('module.permission:jobcards,create')->name('jobcards.store');
    Route::post('/jobcards/recurring', [JobcardController::class, 'storeRecurring'])->middleware('module.permission:jobcards,create')->name('jobcards.recurring.store');
    Route::delete('/jobcards/recurring/{recurringDocument}', [JobcardController::class, 'destroyRecurring'])->middleware('module.permission:jobcards,delete')->name('jobcards.recurring.destroy');
    Route::get('/jobcards/{jobcard}', [JobcardController::class, 'show'])->middleware('module.permission:jobcards,view')->name('jobcards.show');
    Route::get('/jobcards/{jobcard}/edit', [JobcardController::class, 'edit'])->middleware('module.permission:jobcards,edit')->name('jobcards.edit');
    Route::put('/jobcards/{jobcard}', [JobcardController::class, 'update'])->middleware('module.permission:jobcards,edit')->name('jobcards.update');
    Route::put('/jobcards/{jobcard}/autosave', [JobcardController::class, 'autosaveUpdate'])->middleware('module.permission:jobcards,edit')->name('jobcards.autosave.update');
    Route::patch('/jobcards/{jobcard}/status', [JobcardController::class, 'updateStatus'])->middleware('module.permission:jobcards,edit')->name('jobcards.update-status');
    Route::get('/jobcards/{jobcard}/print', [JobcardController::class, 'print'])->middleware('module.permission:jobcards,view')->name('jobcards.print');
    Route::post('/jobcards/{jobcard}/email', [JobcardController::class, 'email'])->middleware('module.permission:jobcards,view')->name('jobcards.email');
    Route::post('/jobcards/{jobcard}/sign', [JobcardController::class, 'sign'])->middleware('module.permission:jobcards,edit')->name('jobcards.sign');
    Route::post('/jobcards/{jobcard}/convert-to-quote', [JobcardController::class, 'convertToQuote'])->middleware('module.permission:jobcards,edit')->name('jobcards.convert-to-quote');
    Route::post('/jobcards/{jobcard}/convert-to-invoice', [JobcardController::class, 'convertToInvoice'])->middleware('module.permission:jobcards,edit')->name('jobcards.convert-to-invoice');
    Route::post('/jobcards/{jobcard}/convert-to-delivery-note', [JobcardController::class, 'convertToDeliveryNote'])->middleware('module.permission:jobcards,edit')->name('jobcards.convert-to-delivery-note');
    Route::post('/jobcards/{jobcard}/attachments', [JobcardController::class, 'storeAttachments'])->middleware('module.permission:jobcards,edit')->name('jobcards.attachments.store');
    Route::patch('/jobcards/{jobcard}/attachments/{attachment}', [JobcardController::class, 'updateAttachment'])->middleware('module.permission:jobcards,edit')->name('jobcards.attachments.update');
    Route::delete('/jobcards/{jobcard}/attachments/{attachment}', [JobcardController::class, 'destroyAttachment'])->middleware('module.permission:jobcards,edit')->name('jobcards.attachments.destroy');
    Route::delete('/jobcards/{jobcard}', [JobcardController::class, 'destroy'])->middleware('module.permission:jobcards,delete')->name('jobcards.destroy');
});
