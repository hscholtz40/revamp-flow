<?php

use App\Http\Controllers\Api\V1\DispatchController as ApiDispatchController;
use App\Http\Controllers\DispatchBoardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'dispatch.enabled'])->group(function () {
    Route::get('/dispatch', [DispatchBoardController::class, 'index'])
        ->middleware('module.permission:dispatch,list')
        ->name('dispatch.index');

    Route::get('/dispatch/board-data', [ApiDispatchController::class, 'board'])
        ->middleware('module.permission:dispatch,list')
        ->name('dispatch.board-data');
    Route::patch('/dispatch/jobcards/{jobcard}/assign', [ApiDispatchController::class, 'assignJobcard'])
        ->middleware('module.permission:dispatch,edit')
        ->name('dispatch.jobcards.assign');
    Route::patch('/dispatch/cards/{type}/{id}/status', [ApiDispatchController::class, 'moveCard'])
        ->middleware('module.permission:dispatch,edit')
        ->whereIn('type', ['jobcard', 'task'])
        ->name('dispatch.cards.move');
    Route::patch('/dispatch/cards/{type}/{id}/schedule', [ApiDispatchController::class, 'rescheduleCard'])
        ->middleware('module.permission:dispatch,edit')
        ->whereIn('type', ['jobcard', 'task'])
        ->name('dispatch.cards.reschedule');
    Route::post('/dispatch/routes/generate', [ApiDispatchController::class, 'generateRoute'])
        ->middleware('module.permission:dispatch,create')
        ->name('dispatch.routes.generate');
    Route::post('/dispatch/tasks', [ApiDispatchController::class, 'createTask'])
        ->middleware('module.permission:tasks,create')
        ->name('dispatch.tasks.create');
});
