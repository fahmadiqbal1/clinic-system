<?php

/**
 * Radiology Routes
 *
 * Radiology-only invoice routes + shared Radiology|Owner catalog/equipment routes.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Radiology\RadiologyDashboardController;
use App\Http\Controllers\Radiology\InvoiceController as RadiologyInvoiceController;
use App\Http\Controllers\Radiology\ImagingCatalogController;
use App\Http\Controllers\Radiology\EquipmentController as RadiologyEquipmentController;
use Illuminate\Support\Facades\Route;

// Radiology-only routes
Route::middleware('role:Radiology')->group(function () {

    // Dashboard
    Route::get('/radiology/dashboard', [RadiologyDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('radiology.dashboard');

    // Invoice Work Queue
    Route::get('/radiology/invoices', [RadiologyInvoiceController::class, 'index'])->name('radiology.invoices.index');
    Route::get('/radiology/invoices/{invoice}', [RadiologyInvoiceController::class, 'show'])->name('radiology.invoices.show');
    Route::post('/radiology/invoices/{invoice}/start-work', [RadiologyInvoiceController::class, 'startWork'])->name('radiology.invoices.start-work');
    Route::post('/radiology/invoices/{invoice}/save-report', [RadiologyInvoiceController::class, 'saveReport'])->name('radiology.invoices.save-report');
    Route::post('/radiology/invoices/{invoice}/upload-images', [RadiologyInvoiceController::class, 'uploadImages'])->name('radiology.invoices.upload-images');
    Route::delete('/radiology/invoices/{invoice}/delete-image/{index}', [RadiologyInvoiceController::class, 'deleteImage'])->name('radiology.invoices.delete-image');
    Route::post('/radiology/invoices/{invoice}/complete', [RadiologyInvoiceController::class, 'markComplete'])->name('radiology.invoices.mark-complete');
});

// Radiology + Owner shared routes
Route::middleware('role:Radiology|Owner')->group(function () {

    // Imaging Catalog
    Route::get('/radiology/catalog', [ImagingCatalogController::class, 'index'])->name('radiology.catalog.index');
    Route::get('/radiology/catalog/create', [ImagingCatalogController::class, 'create'])->name('radiology.catalog.create');
    Route::post('/radiology/catalog', [ImagingCatalogController::class, 'store'])->name('radiology.catalog.store');
    Route::get('/radiology/catalog/{serviceCatalog}/edit', [ImagingCatalogController::class, 'edit'])->name('radiology.catalog.edit');
    Route::patch('/radiology/catalog/{serviceCatalog}', [ImagingCatalogController::class, 'update'])->name('radiology.catalog.update');
    Route::delete('/radiology/catalog/{serviceCatalog}', [ImagingCatalogController::class, 'destroy'])->name('radiology.catalog.destroy');

    // Equipment
    Route::get('/radiology/equipment', [RadiologyEquipmentController::class, 'index'])->name('radiology.equipment.index');
    Route::get('/radiology/equipment/create', [RadiologyEquipmentController::class, 'create'])->name('radiology.equipment.create');
    Route::post('/radiology/equipment', [RadiologyEquipmentController::class, 'store'])->name('radiology.equipment.store');
    Route::get('/radiology/equipment/{equipment}/edit', [RadiologyEquipmentController::class, 'edit'])->name('radiology.equipment.edit');
    Route::patch('/radiology/equipment/{equipment}', [RadiologyEquipmentController::class, 'update'])->name('radiology.equipment.update');
});
