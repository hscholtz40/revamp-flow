<?php

use App\Http\Controllers\DispatchBoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dispatch', [DispatchBoardController::class, 'index'])
        ->middleware('module.permission:jobcards,list')
        ->name('dispatch.index');
});
