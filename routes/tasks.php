<?php

use App\Http\Controllers\TaskBoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/tasks', [TaskBoardController::class, 'index'])
        ->name('tasks.index');

    Route::get('/tasks/create', [TaskBoardController::class, 'create'])
        ->middleware('module.permission:tasks,create')
        ->name('tasks.create');

    Route::post('/tasks', [TaskBoardController::class, 'store'])
        ->middleware('module.permission:tasks,create')
        ->name('tasks.store');

    Route::get('/tasks/{task}/edit', [TaskBoardController::class, 'edit'])
        ->middleware('module.permission:tasks,edit')
        ->name('tasks.edit');

    Route::get('/tasks/{task}', [TaskBoardController::class, 'show'])
        ->name('tasks.show');

    Route::put('/tasks/{task}', [TaskBoardController::class, 'update'])
        ->name('tasks.update');

    Route::delete('/tasks/{task}', [TaskBoardController::class, 'destroy'])
        ->middleware('module.permission:tasks,delete')
        ->name('tasks.destroy');
});
