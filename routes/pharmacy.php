<?php

/**
 * Pharmacy Routes
 *
 * Pharmacy-only routes for dashboard, invoice processing, and prescriptions.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Pharmacy\PharmacyDashboardController;
use App\Http\Controllers\Pharmacy\InvoiceController as PharmacyInvoiceController;
use App\Http\Controllers\Pharmacy\PrescriptionQueueController;
use App\Http\Controllers\Pharmacy\PriceListController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Pharmacy')->group(function () {

    // Dashboard
    Route::get('/pharmacy/dashboard', [PharmacyDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('pharmacy.dashboard');

    // Invoice Work Queue
    Route::get('/pharmacy/invoices', [PharmacyInvoiceController::class, 'index'])->name('pharmacy.invoices.index');
    Route::get('/pharmacy/invoices/{invoice}', [PharmacyInvoiceController::class, 'show'])->name('pharmacy.invoices.show');
    Route::post('/pharmacy/invoices/{invoice}/start-work', [PharmacyInvoiceController::class, 'startWork'])->name('pharmacy.invoices.start-work');
    Route::post('/pharmacy/invoices/{invoice}/complete', [PharmacyInvoiceController::class, 'markComplete'])->name('pharmacy.invoices.mark-complete');
    Route::post('/pharmacy/invoices/{invoice}/pay', [PharmacyInvoiceController::class, 'markPaid'])->name('pharmacy.invoices.mark-paid');
    Route::post('/pharmacy/invoices/{invoice}/cancel', [PharmacyInvoiceController::class, 'cancel'])->name('pharmacy.invoices.cancel');

    // Price List Upload (sends to owner for approval)
    Route::get('/pharmacy/price-list/upload', [PriceListController::class, 'create'])->name('pharmacy.price-list.upload');
    Route::post('/pharmacy/price-list/upload', [PriceListController::class, 'store'])->name('pharmacy.price-list.store');

    // Prescription Queue
    Route::get('/pharmacy/prescriptions', [PrescriptionQueueController::class, 'index'])->name('pharmacy.prescriptions.index');
    Route::get('/pharmacy/prescriptions/{prescription}', [PrescriptionQueueController::class, 'show'])->name('pharmacy.prescriptions.show');
    Route::post('/pharmacy/prescriptions/{prescription}/dispense', [PrescriptionQueueController::class, 'markDispensed'])->name('pharmacy.prescriptions.dispense');
});
