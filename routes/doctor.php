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
use App\Http\Controllers\Doctor\ResultsController as DoctorResultsController;
use App\Http\Controllers\Doctor\PrescriptionController;
use App\Http\Controllers\Doctor\ReferralPatientController;
use App\Http\Controllers\Doctor\AppointmentController as DoctorAppointmentController;
use App\Http\Controllers\Doctor\AvailabilityController;
use App\Http\Controllers\Doctor\SoapKeywordController;
use App\Http\Controllers\Doctor\CredentialController;
use Illuminate\Support\Facades\Route;

// Public API: doctor availability (no auth — for city-facing booking feature)
Route::get('/api/doctor-availability', [AvailabilityController::class, 'available'])
    ->name('api.doctor.availability');

Route::middleware('role:Doctor')->group(function () {

    // ─── Shared dashboard entry-point ───────────────────────────────────────
    // Redirects independent doctors to their own dashboard automatically.
    Route::get('/doctor/dashboard', [DoctorDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('doctor.dashboard');

    // ─── Credential upload (no require.credentials gate — this IS the gate exit) ─
    Route::get('/doctor/credentials/upload', [CredentialController::class, 'upload'])->name('doctor.credentials.upload');
    Route::post('/doctor/credentials/upload', [CredentialController::class, 'store'])->name('doctor.credentials.store');

    // ─── Regular OPD Doctor routes (credential-gated) ────────────────────────
    Route::middleware('require.credentials')->group(function () {
        Route::get('/doctor/patients', [PatientController::class, 'index'])->name('doctor.patients.index');
        Route::get('/doctor/patients/{patient}', [PatientController::class, 'show'])->name('doctor.patients.show');
        Route::post('/doctor/patients/{patient}/complete', [PatientController::class, 'complete'])->name('doctor.patients.complete');

        Route::get('/doctor/consultations/{patient}', [ConsultationController::class, 'show'])->name('doctor.consultation.show');
        Route::post('/doctor/consultations/{patient}/save-notes', [ConsultationController::class, 'saveNotes'])->name('doctor.consultation.save-notes');
        Route::post('/doctor/consultations/{patient}/create-invoice', [ConsultationController::class, 'createInvoice'])->name('doctor.consultation.create-invoice');
        Route::get('/doctor/consultations/{patient}/suggest-drugs', [ConsultationController::class, 'suggestDrugs'])->name('doctor.consultation.suggest-drugs');

        Route::get('/doctor/invoices', [DoctorInvoiceController::class, 'index'])->name('doctor.invoices.index');
        Route::get('/doctor/invoices/{invoice}', [DoctorInvoiceController::class, 'show'])->name('doctor.invoices.show');

        // ─── Clinical Results (lab + radiology, no financial framing) ─────────
        Route::get('/doctor/results', [DoctorResultsController::class, 'index'])->name('doctor.results.index');
        Route::get('/doctor/results/{invoice}', [DoctorResultsController::class, 'show'])->name('doctor.results.show');

        Route::get('/doctor/prescriptions', [PrescriptionController::class, 'index'])->name('doctor.prescriptions.index');
        Route::get('/doctor/prescriptions/{patient}/create', [PrescriptionController::class, 'create'])->name('doctor.prescriptions.create');
        Route::post('/doctor/prescriptions/{patient}', [PrescriptionController::class, 'store'])->name('doctor.prescriptions.store');

        // ─── Appointments ──────────────────────────────────────────────────────
        Route::get('/doctor/appointments', [DoctorAppointmentController::class, 'index'])->name('doctor.appointments.index');

        // ─── Availability Calendar ─────────────────────────────────────────────
        Route::get('/doctor/availability', [AvailabilityController::class, 'index'])->name('doctor.availability.index');
        Route::post('/doctor/availability', [AvailabilityController::class, 'store'])->name('doctor.availability.store');
        Route::delete('/doctor/availability/{slot}', [AvailabilityController::class, 'destroy'])->name('doctor.availability.destroy');

        // ─── SOAP Keyword chips (AJAX) ─────────────────────────────────────────
        Route::get('/doctor/soap-keywords', [SoapKeywordController::class, 'index'])->name('doctor.soap-keywords.index');
        Route::post('/doctor/soap-keywords', [SoapKeywordController::class, 'store'])->name('doctor.soap-keywords.store');
        Route::post('/doctor/soap-keywords/{soapKeyword}/use', [SoapKeywordController::class, 'use'])->name('doctor.soap-keywords.use');
    });

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

