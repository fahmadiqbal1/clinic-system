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
use App\Http\Controllers\Api\NocobaseAuditHookController;
use App\Http\Controllers\Api\OmniDimensionController;
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

// Phase 4 — NocoBase webhook (HMAC-verified, no Sanctum — NocoBase has no Laravel session)
Route::post('/nocobase/audit-hook', [NocobaseAuditHookController::class, 'handle'])
    ->name('nocobase.audit-hook');

// Phase 10B — OmniDimension phone AI webhook (HMAC-verified, no Sanctum)
Route::post('/omnidimension/webhook', [OmniDimensionController::class, 'webhook'])
    ->name('api.omnidimension.webhook');

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
