<?php

use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/time-entries', [TimeEntryController::class, 'index'])->name('time-entries.index');
    Route::post('/time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
    Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');
    
    // Timer actions
    Route::post('/time-entries/start-timer', [TimeEntryController::class, 'startTimer'])->name('time-entries.start-timer');
    Route::post('/time-entries/stop-timer', [TimeEntryController::class, 'stopTimer'])->name('time-entries.stop-timer');
    Route::post('/time-entries/pause-timer', [TimeEntryController::class, 'pauseTimer'])->name('time-entries.pause-timer');
    Route::post('/time-entries/resume-timer', [TimeEntryController::class, 'resumeTimer'])->name('time-entries.resume-timer');
    
    // Convert to line items
    Route::post('/jobcards/{jobcard}/time-entries/convert', [TimeEntryController::class, 'convertToLineItems'])->name('jobcards.time-entries.convert');
});

