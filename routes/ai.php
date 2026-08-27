<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\AiAssistantPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/ai-assistant', [AiAssistantPageController::class, 'index'])->name('ai.assistant.page');
    Route::post('/ai/draft', [AiController::class, 'draft'])->name('ai.draft');
    Route::post('/ai/suggest-technician', [AiController::class, 'suggestTechnician'])->name('ai.suggest-technician');
    Route::post('/ai/suggest-document', [AiController::class, 'suggestDocument'])->name('ai.suggest-document');
    Route::post('/ai/assistant', [AiController::class, 'assistant'])->name('ai.assistant');
    Route::post('/ai/transcribe', [AiController::class, 'transcribe'])->name('ai.transcribe');
});
