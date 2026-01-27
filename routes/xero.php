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
    Route::post('/xero/switch-company', [XeroSettingsController::class, 'switchCompany'])
        ->name('xero.switch-company');
    
    // Xero Sync Routes
    Route::post('/xero/sync/customers', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncCustomersToXero($currentCompany);
        
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
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncProductsToXero($currentCompany);
        
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
    
    Route::post('/xero/sync/customers-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncCustomersFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} customers, updated {$updatedCount} customers, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} customers and updated {$updatedCount} customers from Xero.");
    })->name('xero.sync.customers-from-xero');
    
    Route::post('/xero/sync/products-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncProductsFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} products, updated {$updatedCount} products, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} products and updated {$updatedCount} products from Xero.");
    })->name('xero.sync.products-from-xero');
    
    Route::post('/xero/sync/invoices', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncInvoicesToXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $successCount = collect($results)->where('status', 'success')->count();
        $skippedCount = collect($results)->where('status', 'skipped')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} invoices successfully, {$skippedCount} skipped, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully synced {$successCount} invoices to Xero" . ($skippedCount > 0 ? " ({$skippedCount} skipped)" : '') . ".");
    })->name('xero.sync.invoices');
    
    Route::post('/xero/sync/suppliers', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncSuppliersToXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} suppliers successfully, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully synced {$successCount} suppliers to Xero.");
    })->name('xero.sync.suppliers');
    
    Route::post('/xero/sync/suppliers-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncSuppliersFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} suppliers, updated {$updatedCount} suppliers, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} suppliers and updated {$updatedCount} suppliers from Xero.");
    })->name('xero.sync.suppliers-from-xero');
    
    Route::post('/xero/sync/quotes', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncQuotesToXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} quotes successfully, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully synced {$successCount} quotes to Xero.");
    })->name('xero.sync.quotes');
    
    Route::post('/xero/sync/quotes-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncQuotesFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} quotes, updated {$updatedCount} quotes, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} quotes and updated {$updatedCount} quotes from Xero.");
    })->name('xero.sync.quotes-from-xero');
    
    Route::post('/xero/sync/tax-rates-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncTaxRatesFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} tax rates, updated {$updatedCount} tax rates, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} tax rates and updated {$updatedCount} tax rates from Xero.");
    })->name('xero.sync.tax-rates-from-xero');
    
    Route::post('/xero/sync/bank-accounts-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncBankAccountsFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} bank accounts, updated {$updatedCount} bank accounts, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} bank accounts and updated {$updatedCount} bank accounts from Xero.");
    })->name('xero.sync.bank-accounts-from-xero');
    
    Route::post('/xero/sync/chart-of-accounts-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncChartOfAccountsFromXero($currentCompany);
        
        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }
        
        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();
        
        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} accounts, updated {$updatedCount} accounts, {$errorCount} failed. Check logs for details.");
        }
        
        return redirect()->back()->with('success', "Successfully imported {$createdCount} accounts and updated {$updatedCount} accounts from Xero.");
    })->name('xero.sync.chart-of-accounts-from-xero');
    
    // Xero Webhook Route
    Route::post('/xero/webhook', function (\Illuminate\Http\Request $request) {
        // For webhooks, we need to determine the company based on the tenant_id in the webhook data
        $tenantId = $request->input('tenantId') ?? $request->input('tenant_id');
        if ($tenantId) {
            $settings = \App\Models\XeroSettings::where('tenant_id', $tenantId)->first();
            if ($settings) {
                $company = $settings->company;
                $xeroService = new \App\Services\XeroService($company);
                $xeroService->handleInvoiceWebhook($request->all());
                return response()->json(['status' => 'success']);
            }
        }
        
        // Fallback to default behavior if no matching company found
        $xeroService = app(\App\Services\XeroService::class);
        $xeroService->handleInvoiceWebhook($request->all());
        return response()->json(['status' => 'success']);
    })->name('xero.webhook');
});
