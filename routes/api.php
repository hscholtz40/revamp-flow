<?php

use App\Http\Controllers\Api\LicenseValidationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DispatchController;
use App\Http\Controllers\Api\V1\JobcardController;
use App\Http\Controllers\Api\V1\MessagingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are stateless and intended for external integrations.
|
*/

Route::post('/api/licenses/validate', [LicenseValidationController::class, 'validate'])
    ->middleware('license.api.auth')
    ->name('api.licenses.validate');

Route::post('/api/licenses/report-version', [LicenseValidationController::class, 'reportVersion'])
    ->middleware('license.api.auth')
    ->name('api.licenses.report-version');

Route::prefix('/api/v1')->name('api.v1.')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

        Route::get('/jobcards', [JobcardController::class, 'index'])->middleware('api.module.permission:jobcards,list');
        Route::get('/jobcards/{jobcard}', [JobcardController::class, 'show'])->middleware('api.module.permission:jobcards,view');
        Route::patch('/jobcards/{jobcard}/status', [JobcardController::class, 'updateStatus'])->middleware('api.module.permission:jobcards,edit');

        Route::get('/messages/conversations', [MessagingController::class, 'index']);
        Route::post('/messages/conversations', [MessagingController::class, 'storeConversation']);
        Route::get('/messages/conversations/{conversation}', [MessagingController::class, 'show']);
        Route::post('/messages/conversations/{conversation}/messages', [MessagingController::class, 'storeMessage']);

        Route::get('/dispatch/board', [DispatchController::class, 'board']);
        Route::patch('/dispatch/jobcards/{jobcard}/assign', [DispatchController::class, 'assignJobcard']);
        Route::post('/dispatch/routes/generate', [DispatchController::class, 'generateRoute']);

        Route::get('/tracking/vehicles', [TrackingController::class, 'vehicles']);
        Route::get('/tracking/pings/latest', [TrackingController::class, 'latest']);
        Route::post('/tracking/pings', [TrackingController::class, 'ingest']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::patch('/tasks/{task}', [TaskController::class, 'update']);
        Route::post('/tasks/{task}/notes', [TaskController::class, 'addNote']);
    });
});
