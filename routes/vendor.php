<?php

/**
 * Vendor Portal Routes
 *
 * Accessible to users with the Vendor role. Vendors can view their profile,
 * upload price lists, and track MOU commission status.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\Vendor\VendorDashboardController;
use App\Http\Controllers\Vendor\VendorPriceListController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Vendor')->prefix('vendor')->name('vendor.')->group(function () {

    Route::get('/dashboard', [VendorDashboardController::class, 'index'])->name('dashboard');

    // Price list uploads — vendor uploads their latest price sheet
    Route::get('/price-lists', [VendorPriceListController::class, 'index'])->name('price-lists.index');
    Route::get('/price-lists/upload', [VendorPriceListController::class, 'create'])->name('price-lists.create');
    Route::post('/price-lists', [VendorPriceListController::class, 'store'])->name('price-lists.store');
    Route::get('/price-lists/{priceList}', [VendorPriceListController::class, 'show'])->name('price-lists.show');
});
