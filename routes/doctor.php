<?php

/**
 * Doctor Routes
 *
 * Doctor-only routes for dashboard, patients, consultations, and invoices.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Doctor\DoctorDashboardController;
use App\Http\Controllers\Doctor\PatientController;
use App\Http\Controllers\Doctor\ConsultationController;
use App\Http\Controllers\Doctor\InvoiceController as DoctorInvoiceController;
use App\Http\Controllers\Doctor\PrescriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Doctor')->group(function () {

    // Dashboard
    Route::get('/doctor/dashboard', [DoctorDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('doctor.dashboard');

    // Patients
    Route::get('/doctor/patients', [PatientController::class, 'index'])->name('doctor.patients.index');
    Route::get('/doctor/patients/{patient}', [PatientController::class, 'show'])->name('doctor.patients.show');
    Route::post('/doctor/patients/{patient}/complete', [PatientController::class, 'complete'])->name('doctor.patients.complete');

    // Consultations
    Route::get('/doctor/consultations/{patient}', [ConsultationController::class, 'show'])->name('doctor.consultation.show');
    Route::post('/doctor/consultations/{patient}/save-notes', [ConsultationController::class, 'saveNotes'])->name('doctor.consultation.save-notes');
    Route::post('/doctor/consultations/{patient}/create-invoice', [ConsultationController::class, 'createInvoice'])->name('doctor.consultation.create-invoice');

    // Invoices (read-only)
    Route::get('/doctor/invoices', [DoctorInvoiceController::class, 'index'])->name('doctor.invoices.index');
    Route::get('/doctor/invoices/{invoice}', [DoctorInvoiceController::class, 'show'])->name('doctor.invoices.show');

    // Prescriptions
    Route::get('/doctor/prescriptions', [PrescriptionController::class, 'index'])->name('doctor.prescriptions.index');
    Route::get('/doctor/prescriptions/{patient}/create', [PrescriptionController::class, 'create'])->name('doctor.prescriptions.create');
    Route::post('/doctor/prescriptions/{patient}', [PrescriptionController::class, 'store'])->name('doctor.prescriptions.store');
});
