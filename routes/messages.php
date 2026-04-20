<?php

use App\Http\Controllers\MessageCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/messages', [MessageCenterController::class, 'index'])
        ->middleware('module.permission:messages,list')
        ->name('messages.index');
});
