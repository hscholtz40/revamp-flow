<?php

use App\Http\Controllers\QueriesController;
use Illuminate\Support\Facades\Route;

// Public-facing query submission form (no authentication required).
Route::get('/submit-query/{company?}', [QueriesController::class, 'publicForm'])
    ->name('queries.public.form');

Route::post('/submit-query', [QueriesController::class, 'publicStore'])
    ->middleware('throttle:10,1')
    ->name('queries.public.store');

// Internal queries management.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/queries', [QueriesController::class, 'index'])
        ->middleware('module.permission:queries,list')
        ->name('queries.index');

    Route::get('/queries/{query}', [QueriesController::class, 'show'])
        ->middleware('module.permission:queries,view')
        ->name('queries.show');

    Route::patch('/queries/{query}', [QueriesController::class, 'update'])
        ->middleware('module.permission:queries,edit')
        ->name('queries.update');

    Route::delete('/queries/{query}', [QueriesController::class, 'destroy'])
        ->middleware('module.permission:queries,delete')
        ->name('queries.destroy');
});
