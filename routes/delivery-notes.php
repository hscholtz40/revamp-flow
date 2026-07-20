<?php

use App\Http\Controllers\DeliveryNotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/delivery-notes/create', [DeliveryNotesController::class, 'create'])->middleware('module.permission:delivery-notes,create')->name('delivery-notes.create');
    Route::post('/delivery-notes', [DeliveryNotesController::class, 'store'])->middleware('module.permission:delivery-notes,create')->name('delivery-notes.store');
    Route::get('/delivery-notes/{deliveryNote}', [DeliveryNotesController::class, 'show'])->middleware('module.permission:delivery-notes,view')->name('delivery-notes.show');
    Route::get('/delivery-notes/{deliveryNote}/edit', [DeliveryNotesController::class, 'edit'])->middleware('module.permission:delivery-notes,edit')->name('delivery-notes.edit');
    Route::put('/delivery-notes/{deliveryNote}', [DeliveryNotesController::class, 'update'])->middleware('module.permission:delivery-notes,edit')->name('delivery-notes.update');
    Route::put('/delivery-notes/{deliveryNote}/status', [DeliveryNotesController::class, 'updateStatus'])->middleware('module.permission:delivery-notes,edit')->name('delivery-notes.update-status');
    Route::get('/delivery-notes/{deliveryNote}/download-pdf', [DeliveryNotesController::class, 'downloadPdf'])->middleware('module.permission:delivery-notes,view')->name('delivery-notes.download-pdf');
    Route::delete('/delivery-notes/{deliveryNote}', [DeliveryNotesController::class, 'destroy'])->middleware('module.permission:delivery-notes,delete')->name('delivery-notes.destroy');
});
