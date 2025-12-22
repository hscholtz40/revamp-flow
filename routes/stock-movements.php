<?php

use App\Http\Controllers\StockMovementsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/stock-movements', [StockMovementsController::class, 'index'])->middleware('module.permission:stock-movements,view')->name('stock-movements.index');
    Route::get('/stock-movements/create', [StockMovementsController::class, 'create'])->middleware('module.permission:stock-movements,edit')->name('stock-movements.create');
    Route::post('/stock-movements', [StockMovementsController::class, 'store'])->middleware('module.permission:stock-movements,edit')->name('stock-movements.store');
    Route::get('/stock-movements/{stockMovement}', [StockMovementsController::class, 'show'])->middleware('module.permission:stock-movements,view')->name('stock-movements.show');
});

