<?php

use App\Http\Controllers\RegisteredUsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/registered-users', [RegisteredUsersController::class, 'index'])->name('registered-users.index');
    Route::post('/registered-users/{user}/approve', [RegisteredUsersController::class, 'approve'])->name('registered-users.approve');
    Route::post('/registered-users/{user}/reject', [RegisteredUsersController::class, 'reject'])->name('registered-users.reject');
    Route::post('/registered-users/update-requests/{updateRequest}/approve', [RegisteredUsersController::class, 'approveUpdate'])->name('registered-users.update-requests.approve');
    Route::post('/registered-users/update-requests/{updateRequest}/reject', [RegisteredUsersController::class, 'rejectUpdate'])->name('registered-users.update-requests.reject');
});
