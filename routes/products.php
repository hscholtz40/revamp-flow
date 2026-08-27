<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductBatchController;
use App\Http\Controllers\ProductSerialNumberController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/products', [ProductController::class, 'index'])->middleware('module.permission:products,list')->name('products.index');
    Route::get('/products/export', [ProductController::class, 'export'])->middleware('module.permission:products,list')->name('products.export');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('module.permission:products,create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('module.permission:products,create')->name('products.store');
    Route::get('/products/search-by-barcode', [ProductController::class, 'searchByBarcode'])->middleware('module.permission:products,view')->name('products.search-by-barcode');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('module.permission:products,view')->name('products.show');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('module.permission:products,edit')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('module.permission:products,edit')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('module.permission:products,delete')->name('products.destroy');
    
    // Product Batches
    Route::get('/products/{product}/batches', [ProductBatchController::class, 'index'])->middleware('module.permission:products,view')->name('products.batches.index');
    Route::get('/products/{product}/batches/create', [ProductBatchController::class, 'create'])->middleware('module.permission:products,edit')->name('products.batches.create');
    Route::post('/products/{product}/batches', [ProductBatchController::class, 'store'])->middleware('module.permission:products,edit')->name('products.batches.store');
    Route::get('/products/{product}/batches/{batch}', [ProductBatchController::class, 'show'])->middleware('module.permission:products,view')->name('products.batches.show');
    Route::get('/products/{product}/batches/{batch}/edit', [ProductBatchController::class, 'edit'])->middleware('module.permission:products,edit')->name('products.batches.edit');
    Route::put('/products/{product}/batches/{batch}', [ProductBatchController::class, 'update'])->middleware('module.permission:products,edit')->name('products.batches.update');
    Route::delete('/products/{product}/batches/{batch}', [ProductBatchController::class, 'destroy'])->middleware('module.permission:products,delete')->name('products.batches.destroy');
    
    // Product Serial Numbers
    Route::get('/products/{product}/serial-numbers', [ProductSerialNumberController::class, 'index'])->middleware('module.permission:products,view')->name('products.serial-numbers.index');
    Route::get('/products/{product}/serial-numbers/create', [ProductSerialNumberController::class, 'create'])->middleware('module.permission:products,edit')->name('products.serial-numbers.create');
    Route::post('/products/{product}/serial-numbers', [ProductSerialNumberController::class, 'store'])->middleware('module.permission:products,edit')->name('products.serial-numbers.store');
    Route::get('/products/{product}/serial-numbers/{serialNumber}', [ProductSerialNumberController::class, 'show'])->middleware('module.permission:products,view')->name('products.serial-numbers.show');
    Route::put('/products/{product}/serial-numbers/{serialNumber}', [ProductSerialNumberController::class, 'update'])->middleware('module.permission:products,edit')->name('products.serial-numbers.update');
    Route::delete('/products/{product}/serial-numbers/{serialNumber}', [ProductSerialNumberController::class, 'destroy'])->middleware('module.permission:products,delete')->name('products.serial-numbers.destroy');
});
