<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->middleware('module.permission:products,list')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('module.permission:products,create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('module.permission:products,create')->name('products.store');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('module.permission:products,view')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('module.permission:products,edit')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('module.permission:products,edit')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('module.permission:products,delete')->name('products.destroy');
});
