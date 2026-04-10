<?php

use App\Http\Controllers\RegisteredUsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/registered-users', [RegisteredUsersController::class, 'index'])->name('registered-users.index');

    Route::middleware('module.permission:registered-users,list')->group(function () {
        Route::get('/registered-users/registered', [RegisteredUsersController::class, 'registeredIndex'])->name('registered-users.registered.index');
        Route::get('/registered-users/pending', [RegisteredUsersController::class, 'pendingIndex'])->name('registered-users.pending.index');
    });

    Route::middleware('module.permission:registered-users,view')->group(function () {
        Route::get('/registered-users/registered/{user}', [RegisteredUsersController::class, 'registeredShow'])->name('registered-users.registered.show');
        Route::get('/registered-users/pending/{user}', [RegisteredUsersController::class, 'pendingShow'])->name('registered-users.pending.show');
    });

    Route::middleware('module.permission:registered-users,approve')->group(function () {
        Route::post('/registered-users/{user}/approve', [RegisteredUsersController::class, 'approve'])->name('registered-users.approve');
        Route::post('/registered-users/{user}/reject', [RegisteredUsersController::class, 'reject'])->name('registered-users.reject');
        Route::post('/registered-users/{user}/deactivate', [RegisteredUsersController::class, 'deactivate'])->name('registered-users.deactivate');
        Route::post('/registered-users/{user}/reactivate', [RegisteredUsersController::class, 'reactivate'])->name('registered-users.reactivate');
    });

    Route::middleware('module.permission:customer-update-requests,list')->group(function () {
        Route::get('/registered-users/update-requests', [RegisteredUsersController::class, 'updateRequestsIndex'])->name('registered-users.update-requests.index');
    });

    Route::middleware('module.permission:customer-update-requests,view')->group(function () {
        Route::get('/registered-users/update-requests/{updateRequest}', [RegisteredUsersController::class, 'updateRequestShow'])->name('registered-users.update-requests.show');
    });

    Route::middleware('module.permission:customer-update-requests,approve')->group(function () {
        Route::post('/registered-users/update-requests/{updateRequest}/approve', [RegisteredUsersController::class, 'approveUpdate'])->name('registered-users.update-requests.approve');
        Route::post('/registered-users/update-requests/{updateRequest}/reject', [RegisteredUsersController::class, 'rejectUpdate'])->name('registered-users.update-requests.reject');
    });
});
