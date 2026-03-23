<?php

use App\Http\Controllers\NotesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notes', [NotesController::class, 'index'])->name('notes.index');
    Route::get('/notes/list', [NotesController::class, 'listAll'])->name('notes.list');
    Route::get('/notes/record', [NotesController::class, 'listForRecord'])->name('notes.record');
    Route::get('/notes/related-records', [NotesController::class, 'relatedRecords'])->name('notes.related-records');
    Route::post('/notes', [NotesController::class, 'store'])->name('notes.store');
    Route::get('/notes/{note}/download', [NotesController::class, 'download'])->name('notes.download');
});
