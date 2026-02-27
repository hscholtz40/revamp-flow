<?php

use App\Http\Controllers\Api\LicenseValidationController;
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
