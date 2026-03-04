<?php

/**
 * Triage Routes
 *
 * Triage nurse routes for dashboard and patient vitals management.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Triage\TriageDashboardController;
use App\Http\Controllers\Triage\TriageController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Triage')->group(function () {

    // Dashboard
    Route::get('/triage/dashboard', [TriageDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('triage.dashboard');

    // Patient Queue & Vitals
    Route::get('/triage/patients', [TriageController::class, 'index'])->name('triage.patients.index');
    Route::get('/triage/patients/{patient}/vitals', [TriageController::class, 'showVitals'])->name('triage.patients.vitals');
    Route::post('/triage/patients/{patient}/vitals', [TriageController::class, 'saveVitals'])->name('triage.patients.save-vitals');
    Route::post('/triage/patients/{patient}/send-to-doctor', [TriageController::class, 'sendToDoctor'])->name('triage.patients.send-to-doctor');
});
