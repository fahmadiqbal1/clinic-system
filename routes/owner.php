<?php

/**
 * Owner Routes
 *
 * Owner-only routes for dashboard, user management, finances, and system admin.
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\Owner\UserController;
use App\Http\Controllers\Owner\ServiceCatalogController;
use App\Http\Controllers\Owner\PayoutAnalyticsController;
use App\Http\Controllers\Owner\ExpenseController;
use App\Http\Controllers\Owner\RevenueLedgerController;
use App\Http\Controllers\Owner\ZakatController;
use App\Http\Controllers\Owner\InvoiceDiscountController;
use App\Http\Controllers\Owner\DiscountApprovalController;
use App\Http\Controllers\Owner\PlatformSettingsController;
use App\Http\Controllers\Owner\FbrSettingsController;
use App\Http\Controllers\Dashboard\ExpenseIntelligenceController;
use App\Http\Controllers\Dashboard\InventoryHealthController;
use App\Http\Controllers\Dashboard\ProcurementPipelineController;
use App\Http\Controllers\Owner\RevenueForecastController;
use Illuminate\Support\Facades\Route;

Route::middleware('role:Owner')->group(function () {

    // Dashboard
    Route::get('/owner/dashboard', [OwnerDashboardController::class, 'index'])
        ->middleware('verified')
        ->name('owner.dashboard');

    // Financial Report
    Route::get('/owner/financial-report', [OwnerDashboardController::class, 'financialReport'])
        ->name('owner.financial-report');

    // Department P&L
    Route::get('/owner/department-pnl', [OwnerDashboardController::class, 'departmentPnl'])
        ->name('owner.department-pnl');

    // Revenue Forecast
    Route::get('/owner/revenue-forecast', [RevenueForecastController::class, 'index'])->name('owner.revenue-forecast');

    // User Management
    Route::get('/owner/users', [UserController::class, 'index'])->name('owner.users.index');
    Route::get('/owner/users/create', [UserController::class, 'create'])->name('owner.users.create');
    Route::post('/owner/users', [UserController::class, 'store'])->name('owner.users.store');
    Route::get('/owner/users/{user}/edit', [UserController::class, 'edit'])->name('owner.users.edit');
    Route::patch('/owner/users/{user}', [UserController::class, 'update'])->name('owner.users.update');

    // Service Catalog Management
    Route::get('/owner/service-catalog', [ServiceCatalogController::class, 'index'])->name('owner.service-catalog.index');
    Route::get('/owner/service-catalog/create', [ServiceCatalogController::class, 'create'])->name('owner.service-catalog.create');
    Route::post('/owner/service-catalog', [ServiceCatalogController::class, 'store'])->name('owner.service-catalog.store');
    Route::get('/owner/service-catalog/{serviceCatalog}/edit', [ServiceCatalogController::class, 'edit'])->name('owner.service-catalog.edit');
    Route::patch('/owner/service-catalog/{serviceCatalog}', [ServiceCatalogController::class, 'update'])->name('owner.service-catalog.update');
    Route::delete('/owner/service-catalog/{serviceCatalog}', [ServiceCatalogController::class, 'destroy'])->name('owner.service-catalog.destroy');
    Route::patch('/owner/service-catalog/{serviceCatalog}/quick-update', [ServiceCatalogController::class, 'quickUpdate'])->name('owner.service-catalog.quick-update');

    // Expenses
    Route::get('/owner/expenses', [ExpenseController::class, 'index'])->name('owner.expenses.index');
    Route::get('/owner/expenses/create', [ExpenseController::class, 'create'])->name('owner.expenses.create');
    Route::post('/owner/expenses', [ExpenseController::class, 'store'])->name('owner.expenses.store');
    Route::get('/owner/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('owner.expenses.edit');
    Route::patch('/owner/expenses/{expense}', [ExpenseController::class, 'update'])->name('owner.expenses.update');
    Route::delete('/owner/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('owner.expenses.destroy');

    // Revenue Ledger
    Route::get('/owner/revenue-ledger', [RevenueLedgerController::class, 'index'])->name('owner.revenue-ledger.index');

    // Zakat
    Route::get('/owner/zakat', [ZakatController::class, 'index'])->name('owner.zakat.index');
    Route::post('/owner/zakat/calculate', [ZakatController::class, 'calculate'])->name('owner.zakat.calculate');

    // Discount Approvals
    Route::post('/invoices/{invoice}/discount/approve', [InvoiceDiscountController::class, 'approveDiscount'])->name('invoices.discount.approve');
    Route::post('/invoices/{invoice}/discount/reject', [InvoiceDiscountController::class, 'rejectDiscount'])->name('invoices.discount.reject');
    Route::post('/invoices/{invoice}/discount/apply', [InvoiceDiscountController::class, 'applyDiscount'])->name('invoices.discount.apply');

    // Discount Approval Dashboard
    Route::get('/owner/discount-approvals', [DiscountApprovalController::class, 'index'])->name('owner.discount-approvals.index');

    // Expense Intelligence
    Route::get('/owner/expense-intelligence', [ExpenseIntelligenceController::class, 'index'])->name('owner.expense-intelligence');

    // Inventory Health
    Route::get('/owner/inventory-health', [InventoryHealthController::class, 'index'])->name('owner.inventory-health');

    // Procurement Pipeline
    Route::get('/owner/procurement-pipeline', [ProcurementPipelineController::class, 'index'])->name('owner.procurement-pipeline');

    // Payout Analytics
    Route::get('/owner/payouts', [PayoutAnalyticsController::class, 'index'])->name('owner.payouts.index');
    Route::get('/owner/payouts/{user}/performance', [PayoutAnalyticsController::class, 'staffPerformance'])->name('owner.payouts.performance');

    // Activity Feed
    Route::get('/owner/activity-feed', [OwnerDashboardController::class, 'activityFeed'])->name('owner.activity-feed');

    // Platform Settings (AI / API integrations)
    Route::get('/owner/platform-settings', [PlatformSettingsController::class, 'index'])->name('owner.platform-settings.index');
    Route::patch('/owner/platform-settings', [PlatformSettingsController::class, 'update'])->name('owner.platform-settings.update');
    Route::post('/owner/platform-settings/test', [PlatformSettingsController::class, 'testConnection'])->name('owner.platform-settings.test');

    // FBR IRIS Digital Invoicing Settings
    Route::patch('/owner/fbr-settings', [FbrSettingsController::class, 'update'])->name('owner.fbr-settings.update');
    Route::post('/owner/fbr-settings/test', [FbrSettingsController::class, 'testConnection'])->name('owner.fbr-settings.test');
});
