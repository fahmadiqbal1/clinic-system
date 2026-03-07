<?php

/**
 * Doctor Routes
 *
 * Doctor-only routes for dashboard, patients, consultations, and invoices.
 * web + auth middleware applied by bootstrap/app.php.
 *
 * Independent doctors (is_independent = true) have a separate route group
 * for referral patient management; they are blocked from OPD consultation,
 * triage, and AI features.
 */

use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\Doctor\IndependentDoctorDashboardController;
use App\Http\Controllers\Doctor\PatientController;
use App\Http\Controllers\Doctor\ConsultationController;
use App\Http\Controllers\Doctor\InvoiceController as DoctorInvoiceController;
use App\Http\Controllers\Doctor\PrescriptionController;
use App\Http\Controllers\Doctor\ReferralPatientController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Doctor')->group(function () {

    // ─── Shared dashboard entry-point ───────────────────────────────────────
    // Redirects independent doctors to their own dashboard automatically.
    Route::get('/doctor/dashboard', [DoctorDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('doctor.dashboard');

    // ─── Regular OPD Doctor routes (blocked for independent doctors) ─────────
    Route::get('/doctor/patients', [PatientController::class, 'index'])->name('doctor.patients.index');
    Route::get('/doctor/patients/{patient}', [PatientController::class, 'show'])->name('doctor.patients.show');
    Route::post('/doctor/patients/{patient}/complete', [PatientController::class, 'complete'])->name('doctor.patients.complete');

    Route::get('/doctor/consultations/{patient}', [ConsultationController::class, 'show'])->name('doctor.consultation.show');
    Route::post('/doctor/consultations/{patient}/save-notes', [ConsultationController::class, 'saveNotes'])->name('doctor.consultation.save-notes');
    Route::post('/doctor/consultations/{patient}/create-invoice', [ConsultationController::class, 'createInvoice'])->name('doctor.consultation.create-invoice');

    Route::get('/doctor/invoices', [DoctorInvoiceController::class, 'index'])->name('doctor.invoices.index');
    Route::get('/doctor/invoices/{invoice}', [DoctorInvoiceController::class, 'show'])->name('doctor.invoices.show');

    Route::get('/doctor/prescriptions', [PrescriptionController::class, 'index'])->name('doctor.prescriptions.index');
    Route::get('/doctor/prescriptions/{patient}/create', [PrescriptionController::class, 'create'])->name('doctor.prescriptions.create');
    Route::post('/doctor/prescriptions/{patient}', [PrescriptionController::class, 'store'])->name('doctor.prescriptions.store');

    // ─── Independent Doctor routes ────────────────────────────────────────────
    // These routes are only accessible to independent doctors (is_independent = true).
    // Access is enforced in the controller via abort_unless($user->is_independent, 403).

    Route::get('/independent-doctor/dashboard', [IndependentDoctorDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('independent-doctor.dashboard');

    Route::get('/independent-doctor/patients', [ReferralPatientController::class, 'index'])
        ->name('independent-doctor.patients.index');

    Route::get('/independent-doctor/patients/create', [ReferralPatientController::class, 'create'])
        ->name('independent-doctor.patients.create');

    Route::post('/independent-doctor/patients', [ReferralPatientController::class, 'store'])
        ->name('independent-doctor.patients.store');

    Route::get('/independent-doctor/patients/{patient}', [ReferralPatientController::class, 'show'])
        ->name('independent-doctor.patients.show');

    Route::post('/independent-doctor/patients/{patient}/add-invoice', [ReferralPatientController::class, 'addInvoice'])
        ->name('independent-doctor.patients.add-invoice');
});

