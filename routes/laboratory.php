<?php

/**
 * Laboratory Routes
 *
 * Lab-only invoice routes + shared Lab|Owner catalog/equipment routes.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Laboratory\LaboratoryDashboardController;
use App\Http\Controllers\Laboratory\InvoiceController as LaboratoryInvoiceController;
use App\Http\Controllers\Laboratory\TestCatalogController;
use App\Http\Controllers\Laboratory\EquipmentController as LabEquipmentController;
use Illuminate\Support\Facades\Route;

// Lab-only routes
Route::middleware('role:Laboratory')->group(function () {

    // Dashboard
    Route::get('/laboratory/dashboard', [LaboratoryDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('laboratory.dashboard');

    // Invoice Work Queue
    Route::get('/laboratory/invoices', [LaboratoryInvoiceController::class, 'index'])->name('laboratory.invoices.index');
    Route::get('/laboratory/invoices/{invoice}', [LaboratoryInvoiceController::class, 'show'])->name('laboratory.invoices.show');
    Route::post('/laboratory/invoices/{invoice}/start-work', [LaboratoryInvoiceController::class, 'startWork'])->name('laboratory.invoices.start-work');
    Route::post('/laboratory/invoices/{invoice}/save-report', [LaboratoryInvoiceController::class, 'saveReport'])->name('laboratory.invoices.save-report');
    Route::post('/laboratory/invoices/{invoice}/save-results', [LaboratoryInvoiceController::class, 'saveResults'])->name('laboratory.invoices.save-results');
    Route::post('/laboratory/invoices/{invoice}/complete', [LaboratoryInvoiceController::class, 'markComplete'])->name('laboratory.invoices.mark-complete');
    Route::get('/laboratory/invoices/{invoice}/report-pdf', [LaboratoryInvoiceController::class, 'reportPdf'])->name('laboratory.invoices.report-pdf');
});

// Lab + Owner shared routes
Route::middleware('role:Laboratory|Owner')->group(function () {

    // Test Catalog
    Route::get('/laboratory/catalog', [TestCatalogController::class, 'index'])->name('laboratory.catalog.index');
    Route::get('/laboratory/catalog/create', [TestCatalogController::class, 'create'])->name('laboratory.catalog.create');
    Route::post('/laboratory/catalog', [TestCatalogController::class, 'store'])->name('laboratory.catalog.store');
    Route::get('/laboratory/catalog/{serviceCatalog}/edit', [TestCatalogController::class, 'edit'])->name('laboratory.catalog.edit');
    Route::patch('/laboratory/catalog/{serviceCatalog}', [TestCatalogController::class, 'update'])->name('laboratory.catalog.update');
    Route::delete('/laboratory/catalog/{serviceCatalog}', [TestCatalogController::class, 'destroy'])->name('laboratory.catalog.destroy');

    // Equipment
    Route::get('/laboratory/equipment', [LabEquipmentController::class, 'index'])->name('laboratory.equipment.index');
    Route::get('/laboratory/equipment/create', [LabEquipmentController::class, 'create'])->name('laboratory.equipment.create');
    Route::post('/laboratory/equipment', [LabEquipmentController::class, 'store'])->name('laboratory.equipment.store');
    Route::get('/laboratory/equipment/{equipment}/edit', [LabEquipmentController::class, 'edit'])->name('laboratory.equipment.edit');
    Route::patch('/laboratory/equipment/{equipment}', [LabEquipmentController::class, 'update'])->name('laboratory.equipment.update');
});
