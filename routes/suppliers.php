<?php

use App\Http\Controllers\SuppliersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/suppliers', [SuppliersController::class, 'index'])->middleware('module.permission:suppliers,list')->name('suppliers.index');
    Route::get('/suppliers/search', [SuppliersController::class, 'search'])->name('suppliers.search');
    Route::get('/suppliers/create', [SuppliersController::class, 'create'])->middleware('module.permission:suppliers,create')->name('suppliers.create');
    Route::post('/suppliers', [SuppliersController::class, 'store'])->middleware('module.permission:suppliers,create')->name('suppliers.store');
    Route::get('/suppliers/{supplier}', [SuppliersController::class, 'show'])->middleware('module.permission:suppliers,view')->name('suppliers.show');
    Route::get('/suppliers/{supplier}/edit', [SuppliersController::class, 'edit'])->middleware('module.permission:suppliers,edit')->name('suppliers.edit');
    Route::put('/suppliers/{supplier}', [SuppliersController::class, 'update'])->middleware('module.permission:suppliers,edit')->name('suppliers.update');
    Route::delete('/suppliers/{supplier}', [SuppliersController::class, 'destroy'])->middleware('module.permission:suppliers,delete')->name('suppliers.destroy');
});
