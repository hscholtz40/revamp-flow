<?php

use App\Http\Controllers\UserListPreferenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/list-view-preferences', [UserListPreferenceController::class, 'show'])->name('list-view-preferences.show');
    Route::post('/list-view-preferences', [UserListPreferenceController::class, 'update'])->name('list-view-preferences.update');
});
