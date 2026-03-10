<?php

/**
 * API Routes
 *
 * REST API endpoints for external integrations.
 * All routes require Sanctum authentication.
 */

use App\Http\Controllers\Api\V1\PatientController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\AiAnalysisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned API routes for the Clinic ERP system.
| Use Laravel Sanctum for token authentication.
|
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // Patients
    Route::apiResource('patients', PatientController::class);
    Route::get('patients/{patient}/invoices', [PatientController::class, 'invoices']);
    Route::get('patients/{patient}/analyses', [PatientController::class, 'analyses']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'show']);
    Route::get('invoices/{invoice}/items', [InvoiceController::class, 'items']);

    // AI Analyses
    Route::get('analyses', [AiAnalysisController::class, 'index']);
    Route::get('analyses/{analysis}', [AiAnalysisController::class, 'show']);
});
