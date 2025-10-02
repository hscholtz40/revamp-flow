<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/contacts', [ContactController::class, 'index'])->middleware('module.permission:contacts,list')->name('contacts.index');
    Route::get('/contacts/create', [ContactController::class, 'create'])->middleware('module.permission:contacts,create')->name('contacts.create');
    Route::post('/contacts', [ContactController::class, 'store'])->middleware('module.permission:contacts,create')->name('contacts.store');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->middleware('module.permission:contacts,view')->name('contacts.show');
    Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->middleware('module.permission:contacts,edit')->name('contacts.edit');
    Route::put('/contacts/{contact}', [ContactController::class, 'update'])->middleware('module.permission:contacts,edit')->name('contacts.update');
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy'])->middleware('module.permission:contacts,delete')->name('contacts.destroy');
    Route::post('/contacts/{contact}/sms', [ContactController::class, 'sendSMS'])->middleware('module.permission:contacts,view')->name('contacts.sms');
});
