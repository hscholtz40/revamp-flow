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
    ->name('api.licenses.validate');
