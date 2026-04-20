<?php

use App\Http\Controllers\TaskBoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tasks', [TaskBoardController::class, 'index'])
        ->middleware('module.permission:jobcards,list')
        ->name('tasks.index');

    Route::post('/tasks', [TaskBoardController::class, 'store'])
        ->middleware('module.permission:jobcards,list')
        ->name('tasks.store');
});
