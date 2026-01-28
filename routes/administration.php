<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\SMSSettingsController;
use App\Http\Controllers\WhatsAppSettingsController;
use App\Http\Controllers\Administration\PdfTemplateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])->name('administration.index');
    
    // Database Upgrade route
    Route::post('/administration/upgrade-database', [AdministrationController::class, 'upgradeDatabase'])->name('administration.upgrade-database');
    
    // Module Visibility routes
    Route::get('/administration/module-visibility', [AdministrationController::class, 'moduleVisibility'])->name('administration.module-visibility');
    Route::put('/administration/module-visibility', [AdministrationController::class, 'update'])->name('administration.module-visibility.update');
    
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
});
