<?php

use App\Http\Controllers\TimeEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/time-entries', [TimeEntryController::class, 'index'])->middleware('timesheet.permission:view')->name('time-entries.index');
    Route::post('/time-entries', [TimeEntryController::class, 'store'])->middleware('timesheet.permission:create')->name('time-entries.store');
    Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->middleware('timesheet.permission:edit')->name('time-entries.update');
    Route::delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->middleware('timesheet.permission:delete')->name('time-entries.destroy');

    Route::post('/time-entries/start-timer', [TimeEntryController::class, 'startTimer'])->middleware('timesheet.permission:create')->name('time-entries.start-timer');
    Route::post('/time-entries/stop-timer', [TimeEntryController::class, 'stopTimer'])->middleware('timesheet.permission:edit')->name('time-entries.stop-timer');
    Route::post('/time-entries/pause-timer', [TimeEntryController::class, 'pauseTimer'])->middleware('timesheet.permission:edit')->name('time-entries.pause-timer');
    Route::post('/time-entries/resume-timer', [TimeEntryController::class, 'resumeTimer'])->middleware('timesheet.permission:edit')->name('time-entries.resume-timer');

    Route::post('/jobcards/{jobcard}/time-entries/convert', [TimeEntryController::class, 'convertToLineItems'])->middleware('module.permission:jobcards,edit')->name('jobcards.time-entries.convert');
});
