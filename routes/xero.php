<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\XeroSettingsController;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    // Xero Settings Routes
    Route::get('/administration/xero-settings', [XeroSettingsController::class, 'index'])
        ->name('administration.xero-settings');
    Route::put('/administration/xero-settings', [XeroSettingsController::class, 'update'])
        ->name('administration.xero-settings.update');
    
    // Xero OAuth Routes
    Route::get('/xero/authorize', [XeroSettingsController::class, 'authorize'])
        ->name('xero.authorize');
    Route::get('/xero/callback', [XeroSettingsController::class, 'callback'])
        ->name('xero.callback');
    Route::delete('/xero/disconnect', [XeroSettingsController::class, 'disconnect'])
        ->name('xero.disconnect');
    
    // Xero Sync Routes
    Route::post('/xero/sync/customers', function () {
        $xeroService = app(\App\Services\XeroService::class);
        $results = $xeroService->syncCustomersToXero();
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} customers successfully, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully synced {$successCount} customers to Xero.");
    })->name('xero.sync.customers');
    
    Route::post('/xero/sync/products', function () {
        $xeroService = app(\App\Services\XeroService::class);
        $results = $xeroService->syncProductsToXero();
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} products successfully, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully synced {$successCount} products to Xero.");
    })->name('xero.sync.products');
    
    // Xero Webhook Route
    Route::post('/xero/webhook', function (\Illuminate\Http\Request $request) {
        $xeroService = app(\App\Services\XeroService::class);
        $xeroService->handleInvoiceWebhook($request->all());
        return response()->json(['status' => 'success']);
    })->name('xero.webhook');
});
