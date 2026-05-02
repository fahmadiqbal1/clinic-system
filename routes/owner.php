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
use App\Http\Controllers\Owner\ArchitectureController;
use App\Http\Controllers\Owner\AiOversightController;
use App\Http\Controllers\Owner\NocobaseController;
use App\Http\Controllers\Owner\RetentionPolicyController;
use App\Http\Controllers\Owner\AdminAiController;
use App\Http\Controllers\Owner\OpsAiController;
use App\Http\Controllers\Owner\ComplianceAiController;
use App\Http\Controllers\Owner\ExternalLabController;
use App\Http\Controllers\Owner\VendorController;
use App\Http\Controllers\Api\AiAssistantController;
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

    // External Labs & Referrals
    Route::get('/owner/external-labs', [ExternalLabController::class, 'index'])->name('owner.external-labs.index');
    Route::get('/owner/external-labs/create', [ExternalLabController::class, 'create'])->name('owner.external-labs.create');
    Route::post('/owner/external-labs', [ExternalLabController::class, 'store'])->name('owner.external-labs.store');
    Route::get('/owner/external-labs/{externalLab}/edit', [ExternalLabController::class, 'edit'])->name('owner.external-labs.edit');
    Route::patch('/owner/external-labs/{externalLab}', [ExternalLabController::class, 'update'])->name('owner.external-labs.update');
    Route::post('/owner/external-referrals/{referral}/decide', [ExternalLabController::class, 'decideReferral'])->name('owner.external-referrals.decide');
    Route::post('/owner/external-referrals/{referral}/status', [ExternalLabController::class, 'updateStatus'])->name('owner.external-referrals.status');

    // Vendor Management
    Route::get('/owner/vendors', [VendorController::class, 'index'])->name('owner.vendors.index');
    Route::get('/owner/vendors/create', [VendorController::class, 'create'])->name('owner.vendors.create');
    Route::post('/owner/vendors', [VendorController::class, 'store'])->name('owner.vendors.store');
    Route::get('/owner/vendors/{vendor}/edit', [VendorController::class, 'edit'])->name('owner.vendors.edit');
    Route::patch('/owner/vendors/{vendor}', [VendorController::class, 'update'])->name('owner.vendors.update');
    Route::delete('/owner/vendors/{vendor}', [VendorController::class, 'destroy'])->name('owner.vendors.destroy');

    // Price List Approval (owner side — approve/reject a price_list procurement request)
    // handled by existing ProcurementApprovalController

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
    Route::post('/owner/platform-settings/flag', [PlatformSettingsController::class, 'toggleFlag'])->name('owner.platform-settings.flag');
    Route::post('/owner/platform-settings/model-config', [PlatformSettingsController::class, 'saveModelConfig'])->name('owner.platform-settings.model-config');
    Route::post('/owner/platform-settings/test-provider', [PlatformSettingsController::class, 'testProvider'])->name('owner.platform-settings.test-provider');

    // FBR IRIS Digital Invoicing Settings
    Route::patch('/owner/fbr-settings', [FbrSettingsController::class, 'update'])->name('owner.fbr-settings.update');
    Route::post('/owner/fbr-settings/test', [FbrSettingsController::class, 'testConnection'])->name('owner.fbr-settings.test');

    // Architecture / GitNexus (Phase 1 — flag-gated read-only view)
    Route::get('/owner/architecture', [ArchitectureController::class, 'index'])->name('owner.architecture');

    // AI & Infrastructure (Phase 3 — flag-gated)
    Route::get('/owner/ai-oversight', [AiOversightController::class, 'index'])->name('owner.ai-oversight');

    // Property & Equipment Admin — NocoBase gateway (Phase 4 — flag-gated)
    Route::get('/owner/nocobase', [NocobaseController::class, 'index'])->name('owner.nocobase');

    // Data Retention Policy (Phase 5)
    Route::get('/owner/retention-policy',   [RetentionPolicyController::class, 'index'])->name('owner.retention-policy.index');
    Route::patch('/owner/retention-policy', [RetentionPolicyController::class, 'update'])->name('owner.retention-policy.update');

    // Phase 8 — Administrative / Operations / Compliance AI personas (flag-gated)
    // Rate-limited to 20 AI requests per minute per authenticated user.
    Route::get('/owner/admin-ai',           [AdminAiController::class, 'index'])->name('owner.admin-ai.index');
    Route::post('/owner/admin-ai/analyse',  [AdminAiController::class, 'analyse'])->middleware('throttle:20,1')->name('owner.admin-ai.analyse');

    Route::get('/owner/ops-ai',             [OpsAiController::class, 'index'])->name('owner.ops-ai.index');
    Route::post('/owner/ops-ai/analyse',    [OpsAiController::class, 'analyse'])->middleware('throttle:20,1')->name('owner.ops-ai.analyse');

    Route::get('/owner/compliance-ai',      [ComplianceAiController::class, 'index'])->name('owner.compliance-ai.index');
    Route::post('/owner/compliance-ai/run', [ComplianceAiController::class, 'run'])->middleware('throttle:10,1')->name('owner.compliance-ai.run');
});

// AI Assistant AJAX — accessible to any authenticated user; flag-checked per role inside controller
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/ai-assistant/query', [AiAssistantController::class, 'query'])->name('ai.assistant.query');
    Route::post('/ai-assistant/flag',  [AiAssistantController::class, 'flag'])->name('ai.assistant.flag');
});
