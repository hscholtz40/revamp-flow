<?php

use App\Http\Controllers\QueriesController;
use Illuminate\Support\Facades\Route;

// Internal queries management.
// Public submissions are received via the API (see routes/api.php → api.queries.store).
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
