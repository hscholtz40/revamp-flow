<?php

use App\Http\Controllers\PurchaseOrdersController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/purchase-orders', [PurchaseOrdersController::class, 'index'])->middleware('module.permission:purchase-orders,list')->name('purchase-orders.index');
    Route::get('/purchase-orders/create', [PurchaseOrdersController::class, 'create'])->middleware('module.permission:purchase-orders,create')->name('purchase-orders.create');
    Route::post('/purchase-orders', [PurchaseOrdersController::class, 'store'])->middleware('module.permission:purchase-orders,create')->name('purchase-orders.store');
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrdersController::class, 'show'])->middleware('module.permission:purchase-orders,view')->name('purchase-orders.show');
    Route::put('/purchase-orders/{purchaseOrder}/status', [PurchaseOrdersController::class, 'updateStatus'])->middleware('module.permission:purchase-orders,edit')->name('purchase-orders.update-status');
    Route::post('/purchase-orders/{purchaseOrder}/receive-items', [PurchaseOrdersController::class, 'receiveItems'])->middleware('module.permission:purchase-orders,edit')->name('purchase-orders.receive-items');
    Route::get('/purchase-orders/{purchaseOrder}/download-pdf', [PurchaseOrdersController::class, 'downloadPdf'])->middleware('module.permission:purchase-orders,view')->name('purchase-orders.download-pdf');
    Route::post('/purchase-orders/{purchaseOrder}/email', [PurchaseOrdersController::class, 'email'])->middleware('module.permission:purchase-orders,view')->name('purchase-orders.email');
    Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrdersController::class, 'destroy'])->middleware('module.permission:purchase-orders,delete')->name('purchase-orders.destroy');
});

