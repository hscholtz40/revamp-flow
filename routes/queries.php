<?php

use App\Http\Controllers\QueriesController;
use Illuminate\Support\Facades\Route;

// Public hosted query form (direct link + embeddable iframe JS).
Route::get('/query-form/{companyId}/{token}', [QueriesController::class, 'publicForm'])
    ->middleware('throttle:60,1')
    ->name('queries.public.form');

Route::post('/query-form/{companyId}/{token}', [QueriesController::class, 'publicStore'])
    ->middleware('throttle:20,1')
    ->name('queries.public.store');

Route::get('/query-form/embed.js', [QueriesController::class, 'embedScript'])
    ->middleware('throttle:120,1')
    ->name('queries.public.embed');

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

    // Contractor accept/decline for dispatched job queries (first-accept-wins).
    Route::post('/queries/{query}/accept', [QueriesController::class, 'accept'])
        ->middleware('module.permission:queries,edit')
        ->name('queries.accept');

    Route::post('/queries/{query}/decline', [QueriesController::class, 'decline'])
        ->middleware('module.permission:queries,edit')
        ->name('queries.decline');

    Route::post('/queries/{query}/accept-contractor', [QueriesController::class, 'acceptContractor'])
        ->middleware(['licensing', 'module.permission:queries,edit'])
        ->name('queries.accept-contractor');

    // Convert an accepted Revamp quote query into a jobcard.
    Route::post('/queries/{query}/convert-to-jobcard', [QueriesController::class, 'convertToJobcard'])
        ->middleware('module.permission:queries,edit')
        ->name('queries.convert-to-jobcard');

    Route::delete('/queries/{query}', [QueriesController::class, 'destroy'])
        ->middleware('module.permission:queries,delete')
        ->name('queries.destroy');
});
