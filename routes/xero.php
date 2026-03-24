<?php

use App\Http\Controllers\XeroSettingsController;
use App\Http\Controllers\XeroWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    // Xero Settings Routes
    Route::get('/administration/xero-settings', [XeroSettingsController::class, 'index'])
        ->name('administration.xero-settings');
    Route::put('/administration/xero-settings', [XeroSettingsController::class, 'update'])
        ->name('administration.xero-settings.update');

    // Xero OAuth Routes
    Route::get('/xero/authorize', [XeroSettingsController::class, 'redirectToXero'])
        ->name('xero.authorize');
    Route::get('/xero/callback', [XeroSettingsController::class, 'callback'])
        ->name('xero.callback');
    Route::delete('/xero/disconnect', [XeroSettingsController::class, 'disconnect'])
        ->name('xero.disconnect');
    Route::post('/xero/select-tenant', [XeroSettingsController::class, 'selectTenant'])
        ->name('xero.select-tenant');
    Route::post('/xero/fetch-tenants', [XeroSettingsController::class, 'fetchTenants'])
        ->name('xero.fetch-tenants');
    Route::post('/xero/switch-company', [XeroSettingsController::class, 'switchCompany'])
        ->name('xero.switch-company');
    Route::post('/xero/reset-initial-sync-status', [XeroSettingsController::class, 'resetInitialSyncStatus'])
        ->name('xero.reset-initial-sync-status');

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

    Route::post('/xero/sync/customers-from-xero/resync', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncCustomersFromXero($currentCompany, true);

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Resync imported {$createdCount} customers, updated {$updatedCount} customers, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "Successfully resynced all customers from Xero ({$createdCount} imported, {$updatedCount} updated).");
    })->name('xero.sync.customers-from-xero.resync');

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

        return redirect()->back()->with('success', "Successfully synced {$successCount} invoices to Xero".($skippedCount > 0 ? " ({$skippedCount} skipped)" : '').'.');
    })->name('xero.sync.invoices');

    Route::post('/xero/sync/invoices-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncInvoicesFromXero();

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} invoices, updated {$updatedCount} invoices, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "Successfully imported {$createdCount} invoices and updated {$updatedCount} invoices from Xero.");
    })->name('xero.sync.invoices-from-xero');

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

    Route::post('/xero/sync/credit-notes', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncCreditNotesToXero($currentCompany);

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $collection = collect($results);
        $successCount = $collection->where('status', 'success')->count();
        $updatedFromXeroCount = $collection->where('status', 'updated_from_xero')->count();
        $errorCount = $collection->where('status', 'error')->count();

        $message = "Synced {$successCount} credit notes to Xero";
        if ($updatedFromXeroCount > 0) {
            $message .= ", {$updatedFromXeroCount} updated from Xero";
        }

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "{$message}, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "{$message}.");
    })->name('xero.sync.credit-notes');

    Route::post('/xero/sync/credit-notes-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncCreditNotesFromXero($currentCompany);

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} credit notes, updated {$updatedCount} credit notes, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "Successfully imported {$createdCount} credit notes and updated {$updatedCount} credit notes from Xero.");
    })->name('xero.sync.credit-notes-from-xero');

    Route::post('/xero/sync/purchase-orders', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncPurchaseOrdersToXero($currentCompany);

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $successCount = collect($results)->where('status', 'success')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Synced {$successCount} purchase orders successfully, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "Successfully synced {$successCount} purchase orders to Xero.");
    })->name('xero.sync.purchase-orders');

    Route::post('/xero/sync/purchase-orders-from-xero', function () {
        $currentCompany = auth()->user()->getCurrentCompany();
        $xeroService = new \App\Services\XeroService($currentCompany);
        $results = $xeroService->syncPurchaseOrdersFromXero($currentCompany);

        if (isset($results['skipped']) && $results['skipped']) {
            return redirect()->back()->with('info', $results['message']);
        }

        $createdCount = collect($results)->where('status', 'created')->count();
        $updatedCount = collect($results)->where('status', 'updated')->count();
        $errorCount = collect($results)->where('status', 'error')->count();

        if ($errorCount > 0) {
            return redirect()->back()->with('warning', "Imported {$createdCount} purchase orders, updated {$updatedCount} purchase orders, {$errorCount} failed. Check logs for details.");
        }

        return redirect()->back()->with('success', "Successfully imported {$createdCount} purchase orders and updated {$updatedCount} purchase orders from Xero.");
    })->name('xero.sync.purchase-orders-from-xero');
});

// Xero Webhook (no auth; signature verified in controller; CSRF excluded in bootstrap/app.php)
Route::post('/xero/webhook', XeroWebhookController::class)->name('xero.webhook');
