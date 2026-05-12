<?php

use App\Http\Controllers\QuickBooksSettingsController;
use App\Services\QuickBooksService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/administration/quickbooks-settings', [QuickBooksSettingsController::class, 'index'])
        ->name('administration.quickbooks-settings');
    Route::put('/administration/quickbooks-settings', [QuickBooksSettingsController::class, 'update'])
        ->name('administration.quickbooks-settings.update');

    Route::get('/quickbooks/authorize', [QuickBooksSettingsController::class, 'redirectToQuickBooks'])
        ->name('quickbooks.authorize');
    Route::get('/quickbooks/callback', [QuickBooksSettingsController::class, 'callback'])
        ->name('quickbooks.callback');
    Route::delete('/quickbooks/disconnect', [QuickBooksSettingsController::class, 'disconnect'])
        ->name('quickbooks.disconnect');
    Route::post('/quickbooks/switch-company', [QuickBooksSettingsController::class, 'switchCompany'])
        ->name('quickbooks.switch-company');

    $placeholder = function (string $label) {
        return function () use ($label) {
            $currentCompany = auth()->user()->getCurrentCompany();
            $service = new QuickBooksService($currentCompany);
            $results = $service->placeholderSync($label);

            return redirect()->back()->with('info', $results['message']);
        };
    };

    Route::post('/quickbooks/sync/customers', $placeholder('customers to QuickBooks'))->name('quickbooks.sync.customers');
    Route::post('/quickbooks/sync/products', $placeholder('products to QuickBooks'))->name('quickbooks.sync.products');
    Route::post('/quickbooks/sync/customers-from-quickbooks', $placeholder('customers from QuickBooks'))->name('quickbooks.sync.customers-from-quickbooks');
    Route::post('/quickbooks/sync/customers-from-quickbooks/resync', $placeholder('customers resync from QuickBooks'))->name('quickbooks.sync.customers-from-quickbooks.resync');
    Route::post('/quickbooks/sync/products-from-quickbooks', $placeholder('products from QuickBooks'))->name('quickbooks.sync.products-from-quickbooks');
    Route::post('/quickbooks/sync/invoices', $placeholder('invoices to QuickBooks'))->name('quickbooks.sync.invoices');
    Route::post('/quickbooks/sync/invoices-from-quickbooks', $placeholder('invoices from QuickBooks'))->name('quickbooks.sync.invoices-from-quickbooks');
    Route::post('/quickbooks/sync/suppliers', $placeholder('suppliers to QuickBooks'))->name('quickbooks.sync.suppliers');
    Route::post('/quickbooks/sync/suppliers-from-quickbooks', $placeholder('suppliers from QuickBooks'))->name('quickbooks.sync.suppliers-from-quickbooks');
    Route::post('/quickbooks/sync/quotes', $placeholder('quotes to QuickBooks'))->name('quickbooks.sync.quotes');
    Route::post('/quickbooks/sync/quotes-from-quickbooks', $placeholder('quotes from QuickBooks'))->name('quickbooks.sync.quotes-from-quickbooks');
    Route::post('/quickbooks/sync/tax-rates-from-quickbooks', $placeholder('tax rates from QuickBooks'))->name('quickbooks.sync.tax-rates-from-quickbooks');
    Route::post('/quickbooks/sync/bank-accounts-from-quickbooks', $placeholder('bank accounts from QuickBooks'))->name('quickbooks.sync.bank-accounts-from-quickbooks');
    Route::post('/quickbooks/sync/chart-of-accounts-from-quickbooks', $placeholder('chart of accounts from QuickBooks'))->name('quickbooks.sync.chart-of-accounts-from-quickbooks');
    Route::post('/quickbooks/sync/credit-notes', $placeholder('credit notes to QuickBooks'))->name('quickbooks.sync.credit-notes');
    Route::post('/quickbooks/sync/credit-notes-from-quickbooks', $placeholder('credit notes from QuickBooks'))->name('quickbooks.sync.credit-notes-from-quickbooks');
    Route::post('/quickbooks/sync/purchase-orders', $placeholder('purchase orders to QuickBooks'))->name('quickbooks.sync.purchase-orders');
    Route::post('/quickbooks/sync/purchase-orders-from-quickbooks', $placeholder('purchase orders from QuickBooks'))->name('quickbooks.sync.purchase-orders-from-quickbooks');
});
