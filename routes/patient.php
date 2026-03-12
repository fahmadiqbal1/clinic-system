<?php

/**
 * Patient Portal Routes
 *
 * Self-service portal for patients to view their treatment history.
 */

use App\Http\Controllers\Patient\PatientPortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Patient')->prefix('patient')->name('patient.')->group(function () {
    Route::get('/dashboard', [PatientPortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/invoices/{invoice}', [PatientPortalController::class, 'invoice'])->name('invoice');
    Route::get('/invoices/{invoice}/download', [PatientPortalController::class, 'downloadInvoicePdf'])->name('invoice.download');
});
