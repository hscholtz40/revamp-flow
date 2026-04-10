<?php

use App\Http\Controllers\ClientZoneController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/client-zone/register', [ClientZoneController::class, 'showRegistrationForm'])->name('client-zone.register');
    Route::post('/client-zone/register', [ClientZoneController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('client-zone.register.store');
});

Route::middleware(['auth', 'approved.client'])->group(function () {
    Route::get('/client-zone', [ClientZoneController::class, 'dashboard'])->name('client-zone.dashboard');
    Route::get('/client-zone/documents', [ClientZoneController::class, 'documents'])->name('client-zone.documents');
    Route::get('/client-zone/request-update', [ClientZoneController::class, 'requestUpdateForm'])->name('client-zone.request-update');
    Route::post('/client-zone/update-request', [ClientZoneController::class, 'requestProfileUpdate'])->name('client-zone.update-request');
    Route::get('/client-zone/invoices/{invoiceId}', [ClientZoneController::class, 'showInvoice'])->name('client-zone.invoices.show');
    Route::get('/client-zone/invoices/{invoiceId}/download-pdf', [ClientZoneController::class, 'downloadInvoicePdf'])->name('client-zone.invoices.download-pdf');
    Route::post('/client-zone/invoices/{invoiceId}/sign', [ClientZoneController::class, 'signInvoice'])->name('client-zone.invoices.sign');
    Route::get('/client-zone/quotes/{quoteId}', [ClientZoneController::class, 'showQuote'])->name('client-zone.quotes.show');
    Route::get('/client-zone/quotes/{quoteId}/download-pdf', [ClientZoneController::class, 'downloadQuotePdf'])->name('client-zone.quotes.download-pdf');
    Route::post('/client-zone/quotes/{quoteId}/sign', [ClientZoneController::class, 'signQuote'])->name('client-zone.quotes.sign');
    Route::get('/client-zone/jobcards/{jobcardId}', [ClientZoneController::class, 'showJobcard'])->name('client-zone.jobcards.show');
    Route::get('/client-zone/jobcards/{jobcardId}/download-pdf', [ClientZoneController::class, 'downloadJobcardPdf'])->name('client-zone.jobcards.download-pdf');
    Route::post('/client-zone/jobcards/{jobcardId}/sign', [ClientZoneController::class, 'signJobcard'])->name('client-zone.jobcards.sign');
    Route::get('/client-zone/statement/download-pdf', [ClientZoneController::class, 'downloadStatementPdf'])->name('client-zone.statement.download-pdf');
});
