<?php

/**
 * Receptionist Routes
 *
 * Receptionist-only routes for dashboard, patient registration, and invoice management.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Receptionist\ReceptionistDashboardController;
use App\Http\Controllers\Receptionist\PatientRegistrationController;
use App\Http\Controllers\Receptionist\InvoiceController as ReceptionistInvoiceController;
use App\Http\Controllers\Receptionist\PayoutController as ReceptionistPayoutController;
use App\Http\Controllers\Receptionist\AppointmentController;
use App\Http\Controllers\Owner\InvoiceDiscountController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Receptionist|Owner')->group(function () {

    // Dashboard
    Route::get('/receptionist/dashboard', [ReceptionistDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('receptionist.dashboard');

    // Patient Registration
    Route::get('/receptionist/patients', [PatientRegistrationController::class, 'index'])->name('receptionist.patients.index');
    Route::get('/receptionist/patients/create', [PatientRegistrationController::class, 'create'])->name('receptionist.patients.create');
    Route::post('/receptionist/patients', [PatientRegistrationController::class, 'store'])->name('receptionist.patients.store');
    Route::get('/receptionist/patients/{patient}', [PatientRegistrationController::class, 'show'])->name('receptionist.patients.show');
    Route::post('/receptionist/patients/{patient}/revisit', [PatientRegistrationController::class, 'revisit'])->name('receptionist.patients.revisit');
    Route::post('/receptionist/patients/{patient}/reassign', [PatientRegistrationController::class, 'reassign'])->name('receptionist.patients.reassign');

    // Invoices
    Route::get('/receptionist/invoices', [ReceptionistInvoiceController::class, 'index'])->name('receptionist.invoices.index');
    Route::get('/receptionist/invoices/create', [ReceptionistInvoiceController::class, 'create'])->name('receptionist.invoices.create');
    Route::post('/receptionist/invoices', [ReceptionistInvoiceController::class, 'store'])->name('receptionist.invoices.store');
    Route::get('/receptionist/invoices/{invoice}', [ReceptionistInvoiceController::class, 'show'])->name('receptionist.invoices.show');
    Route::post('/receptionist/invoices/{invoice}/start-work', [ReceptionistInvoiceController::class, 'startWork'])->name('receptionist.invoices.start-work');
    Route::post('/receptionist/invoices/{invoice}/complete', [ReceptionistInvoiceController::class, 'markComplete'])->name('receptionist.invoices.mark-complete');
    Route::post('/receptionist/invoices/{invoice}/pay', [ReceptionistInvoiceController::class, 'markPaid'])->name('receptionist.invoices.mark-paid');
    Route::post('/receptionist/invoices/{invoice}/cancel', [ReceptionistInvoiceController::class, 'cancel'])->name('receptionist.invoices.cancel');
    Route::post('/receptionist/invoices/{invoice}/fbr-resubmit', [ReceptionistInvoiceController::class, 'resubmitToFbr'])->name('receptionist.invoices.fbr-resubmit');

    // Discount requests (Receptionist can request, Owner approves)
    Route::post('/invoices/{invoice}/discount/request', [InvoiceDiscountController::class, 'requestDiscount'])->name('invoices.discount.request');

    // Payout Dashboard (one-click payout)
    Route::get('/receptionist/payouts/dashboard', [ReceptionistPayoutController::class, 'dashboard'])->name('receptionist.payouts.dashboard');
    Route::post('/receptionist/payouts/{user}/quick-pay', [ReceptionistPayoutController::class, 'quickPay'])->name('receptionist.payouts.quick-pay');

    // Appointments
    Route::get('/receptionist/appointments', [AppointmentController::class, 'index'])->name('receptionist.appointments.index');
    Route::get('/receptionist/appointments/create', [AppointmentController::class, 'create'])->name('receptionist.appointments.create');
    Route::post('/receptionist/appointments', [AppointmentController::class, 'store'])->name('receptionist.appointments.store');
    Route::get('/receptionist/appointments/{appointment}', [AppointmentController::class, 'show'])->name('receptionist.appointments.show');
    Route::post('/receptionist/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('receptionist.appointments.cancel');
});
