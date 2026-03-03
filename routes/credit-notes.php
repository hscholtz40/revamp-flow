<?php

use App\Http\Controllers\CreditNotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/credit-notes', [CreditNotesController::class, 'index'])->middleware('module.permission:credit-notes,list')->name('credit-notes.index');
    Route::get('/credit-notes/create', [CreditNotesController::class, 'create'])->middleware('module.permission:credit-notes,create')->name('credit-notes.create');
    Route::post('/credit-notes', [CreditNotesController::class, 'store'])->middleware('module.permission:credit-notes,create')->name('credit-notes.store');
    Route::get('/credit-notes/{creditNote}', [CreditNotesController::class, 'show'])->middleware('module.permission:credit-notes,view')->name('credit-notes.show');
    Route::get('/credit-notes/{creditNote}/edit', [CreditNotesController::class, 'edit'])->middleware('module.permission:credit-notes,edit')->name('credit-notes.edit');
    Route::put('/credit-notes/{creditNote}', [CreditNotesController::class, 'update'])->middleware('module.permission:credit-notes,edit')->name('credit-notes.update');
    Route::delete('/credit-notes/{creditNote}', [CreditNotesController::class, 'destroy'])->middleware('module.permission:credit-notes,delete')->name('credit-notes.destroy');
    Route::patch('/credit-notes/{creditNote}/status', [CreditNotesController::class, 'updateStatus'])->middleware('module.permission:credit-notes,edit')->name('credit-notes.update-status');
    Route::post('/credit-notes/{creditNote}/payments', [CreditNotesController::class, 'storePayment'])->middleware('module.permission:credit-notes,edit')->name('credit-notes.payments.store');
    Route::delete('/credit-notes/{creditNote}/payments/{payment}', [CreditNotesController::class, 'destroyPayment'])->middleware('module.permission:credit-notes,edit')->name('credit-notes.payments.destroy');
});
