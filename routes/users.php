<?php

use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/users', [UsersController::class, 'index'])->middleware('module.permission:users,list')->name('users.index');
    Route::get('/users/create', [UsersController::class, 'create'])->middleware('module.permission:users,create')->name('users.create');
    Route::post('/users', [UsersController::class, 'store'])->middleware('module.permission:users,create')->name('users.store');
    Route::get('/users/{user}', [UsersController::class, 'show'])->middleware('module.permission:users,view')->name('users.show');
    Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->middleware('module.permission:users,edit')->name('users.edit');
    Route::put('/users/{user}', [UsersController::class, 'update'])->middleware('module.permission:users,edit')->name('users.update');
    Route::delete('/users/{user}', [UsersController::class, 'destroy'])->middleware('module.permission:users,delete')->name('users.destroy');
});


