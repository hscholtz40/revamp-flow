<?php

use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/administration/teams', [TeamController::class, 'index'])->name('administration.teams.index');
    Route::get('/administration/teams/create', [TeamController::class, 'create'])->name('administration.teams.create');
    Route::post('/administration/teams', [TeamController::class, 'store'])->name('administration.teams.store');
    Route::get('/administration/teams/{team}', [TeamController::class, 'show'])->name('administration.teams.show');
    Route::get('/administration/teams/{team}/edit', [TeamController::class, 'edit'])->name('administration.teams.edit');
    Route::put('/administration/teams/{team}', [TeamController::class, 'update'])->name('administration.teams.update');
    Route::delete('/administration/teams/{team}', [TeamController::class, 'destroy'])->name('administration.teams.destroy');
});
