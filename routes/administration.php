<?php

use App\Http\Controllers\Administration\EmailTemplateController;
use App\Http\Controllers\Administration\ImportController;
use App\Http\Controllers\Administration\PdfTemplateController;
use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\GoogleIntegrationController;
use App\Http\Controllers\SMSSettingsController;
use App\Http\Controllers\WhatsAppSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])->name('administration.index');
    Route::get('/administration/license', [AdministrationController::class, 'license'])->name('administration.license');
    Route::post('/administration/license', [AdministrationController::class, 'updateLicense'])->name('administration.license.update');
    Route::get('/administration/document-numbering', [AdministrationController::class, 'documentNumbering'])->name('administration.document-numbering');
    Route::put('/administration/document-numbering', [AdministrationController::class, 'updateDocumentNumbering'])->name('administration.document-numbering.update');
    Route::get('/administration/localization', [AdministrationController::class, 'localization'])->name('administration.localization');
    Route::put('/administration/localization', [AdministrationController::class, 'updateLocalization'])->name('administration.localization.update');
    Route::get('/administration/status-editor', [AdministrationController::class, 'statusEditor'])->name('administration.status-editor');
    Route::put('/administration/status-editor', [AdministrationController::class, 'updateStatusEditor'])->name('administration.status-editor.update');

    // Database Upgrade route
    Route::post('/administration/upgrade-database', [AdministrationController::class, 'upgradeDatabase'])->name('administration.upgrade-database');

    // Module Visibility routes
    Route::get('/administration/module-visibility', [AdministrationController::class, 'moduleVisibility'])->name('administration.module-visibility');
    Route::put('/administration/module-visibility', [AdministrationController::class, 'update'])->name('administration.module-visibility.update');

    Route::get('/administration/other-integrations', [GoogleIntegrationController::class, 'show'])->name('administration.other-integrations');
    Route::put('/administration/other-integrations', [GoogleIntegrationController::class, 'update'])->name('administration.other-integrations.update');
    Route::redirect('/administration/google-integration', '/administration/other-integrations');

    // SMS Settings routes
    Route::get('/administration/sms-settings', [SMSSettingsController::class, 'index'])->name('sms-settings.index');
    Route::post('/administration/sms-settings', [SMSSettingsController::class, 'store'])->name('sms-settings.store');
    Route::put('/administration/sms-settings/{smsSettings}', [SMSSettingsController::class, 'update'])->name('sms-settings.update');
    Route::delete('/administration/sms-settings/{smsSettings}', [SMSSettingsController::class, 'destroy'])->name('sms-settings.destroy');

    // WhatsApp Settings routes
    Route::get('/administration/whatsapp-settings', [WhatsAppSettingsController::class, 'index'])->name('whatsapp-settings.index');
    Route::post('/administration/whatsapp-settings', [WhatsAppSettingsController::class, 'store'])->name('whatsapp-settings.store');
    Route::put('/administration/whatsapp-settings/{whatsAppSettings}', [WhatsAppSettingsController::class, 'update'])->name('whatsapp-settings.update');
    Route::delete('/administration/whatsapp-settings/{whatsAppSettings}', [WhatsAppSettingsController::class, 'destroy'])->name('whatsapp-settings.destroy');

    // PDF Templates routes
    Route::get('/administration/pdf-templates', [PdfTemplateController::class, 'index'])->name('administration.pdf-templates.index');
    Route::get('/administration/pdf-templates/create', [PdfTemplateController::class, 'create'])->name('administration.pdf-templates.create');
    Route::post('/administration/pdf-templates', [PdfTemplateController::class, 'store'])->name('administration.pdf-templates.store');
    Route::get('/administration/pdf-templates/{pdfTemplate}/edit', [PdfTemplateController::class, 'edit'])->name('administration.pdf-templates.edit');
    Route::put('/administration/pdf-templates/{pdfTemplate}', [PdfTemplateController::class, 'update'])->name('administration.pdf-templates.update');
    Route::delete('/administration/pdf-templates/{pdfTemplate}', [PdfTemplateController::class, 'destroy'])->name('administration.pdf-templates.destroy');
    Route::post('/administration/pdf-templates/upload-image', [PdfTemplateController::class, 'uploadImage'])->name('administration.pdf-templates.upload-image');

    // Email Templates routes
    Route::get('/administration/email-templates', [EmailTemplateController::class, 'index'])->name('administration.email-templates.index');
    Route::get('/administration/email-templates/create', [EmailTemplateController::class, 'create'])->name('administration.email-templates.create');
    Route::post('/administration/email-templates', [EmailTemplateController::class, 'store'])->name('administration.email-templates.store');
    Route::get('/administration/email-templates/{emailTemplate}/edit', [EmailTemplateController::class, 'edit'])->name('administration.email-templates.edit');
    Route::put('/administration/email-templates/{emailTemplate}', [EmailTemplateController::class, 'update'])->name('administration.email-templates.update');
    Route::delete('/administration/email-templates/{emailTemplate}', [EmailTemplateController::class, 'destroy'])->name('administration.email-templates.destroy');
    Route::post('/administration/email-templates/upload-image', [EmailTemplateController::class, 'uploadImage'])->name('administration.email-templates.upload-image');

    Route::get('/administration/import', [ImportController::class, 'index'])->name('administration.import');
    Route::post('/administration/import/upload', [ImportController::class, 'upload'])->name('administration.import.upload');
    Route::post('/administration/import/run', [ImportController::class, 'run'])->name('administration.import.run');
});
