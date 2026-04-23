<?php

/**
 * Shared Routes
 *
 * Routes accessible by multiple roles (cross-role features).
 * web + auth middleware applied by bootstrap/app.php.
 */

use App\Http\Controllers\StaffContractController;
use App\Http\Controllers\DoctorPayoutController;
use App\Http\Controllers\InventoryItemController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\ProcurementRequestController;
use App\Http\Controllers\ProcurementApprovalController;
use App\Http\Controllers\ProcurementReceiptController;
use App\Http\Controllers\Dashboard\LowStockAlertController;
use App\Http\Controllers\AiAnalysisController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Owner\InvoiceDiscountController;
use App\Http\Controllers\InvoicePdfController;
use Illuminate\Support\Facades\Route;

// ── Contracts (all authenticated staff) ──
Route::middleware('role:Owner|Doctor|Receptionist|Triage|Laboratory|Radiology|Pharmacy')->group(function () {
    Route::get('/contracts', [StaffContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/show/{staff?}', [StaffContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/view/{contract}', [StaffContractController::class, 'view'])->name('contracts.view');
    Route::get('/contracts/{contract}/pdf', [StaffContractController::class, 'downloadPdf'])->name('contracts.pdf');
});

Route::middleware('role:Owner')->group(function () {
    Route::get('/contracts/create/{staff?}', [StaffContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [StaffContractController::class, 'store'])->name('contracts.store');
    Route::post('/contracts/{contract}/mark-early-exit', [StaffContractController::class, 'markEarlyExit'])->name('contracts.mark-early-exit');
});

Route::middleware('role:Doctor|Receptionist|Triage|Laboratory|Radiology|Pharmacy')->group(function () {
    Route::post('/contracts/{contract}/sign', [StaffContractController::class, 'sign'])->name('contracts.sign');
    Route::post('/contracts/{contract}/resign', [StaffContractController::class, 'submitResignation'])->name('contracts.resign');
});

// ── Payouts (Owner + Receptionist + Doctor) ──
Route::middleware('role:Owner|Receptionist')->group(function () {
    Route::get('/payouts/create', [DoctorPayoutController::class, 'create'])->name('reception.payouts.create');
    Route::post('/payouts', [DoctorPayoutController::class, 'store'])->name('reception.payouts.store');
});

Route::middleware('role:Owner|Receptionist|Doctor|Laboratory|Radiology|Pharmacy')->group(function () {
    Route::get('/payouts', [DoctorPayoutController::class, 'index'])->name('reception.payouts.index');
    Route::get('/payouts/{payout}', [DoctorPayoutController::class, 'show'])->name('reception.payouts.show');
    Route::get('/payouts/{payout}/pdf', [DoctorPayoutController::class, 'downloadPdf'])->name('payouts.pdf');
});

Route::middleware('role:Doctor|Laboratory|Radiology|Pharmacy')->group(function () {
    Route::post('/payouts/{payout}/confirm', [DoctorPayoutController::class, 'confirm'])->name('payouts.confirm');
});

Route::middleware('role:Owner')->group(function () {
    Route::post('/payouts/{payout}/approve', [DoctorPayoutController::class, 'approve'])->name('payouts.approve');
    Route::post('/payouts/{payout}/reject', [DoctorPayoutController::class, 'reject'])->name('payouts.reject');
    Route::get('/payouts/{payout}/correction', [DoctorPayoutController::class, 'createCorrection'])->name('owner.payouts.correction-create');
    Route::post('/payouts/{payout}/correction', [DoctorPayoutController::class, 'storeCorrection'])->name('owner.payouts.correction-store');
});

// ── Inventory (Owner + Pharmacy + Laboratory + Radiology) ──
Route::middleware('role:Owner|Pharmacy|Laboratory|Radiology')->group(function () {
    Route::get('/inventory', [InventoryItemController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/barcode-lookup', [InventoryItemController::class, 'barcodeLookup'])->name('inventory.barcode-lookup');
    Route::get('/inventory/create', [InventoryItemController::class, 'create'])->name('inventory.create');
    Route::post('/inventory', [InventoryItemController::class, 'store'])->name('inventory.store');
    Route::get('/inventory/{inventoryItem}/edit', [InventoryItemController::class, 'edit'])->name('inventory.edit');
    Route::patch('/inventory/{inventoryItem}', [InventoryItemController::class, 'update'])->name('inventory.update');
    Route::patch('/inventory/{inventoryItem}/quick-update', [InventoryItemController::class, 'quickUpdate'])->name('inventory.quick-update');
    Route::get('/inventory/{inventoryItem}/adjust', [InventoryItemController::class, 'showAdjust'])->name('inventory.adjust');
    Route::post('/inventory/{inventoryItem}/adjust', [InventoryItemController::class, 'storeAdjust'])->name('inventory.adjust.store');
});

// ── Stock Movements (Owner + Pharmacy + Laboratory + Radiology) ──
Route::middleware('role:Owner|Pharmacy|Laboratory|Radiology')->group(function () {
    Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');
});

// ── Procurement (Owner + Pharmacy + Laboratory + Radiology + Receptionist) ──
Route::middleware('role:Owner|Pharmacy|Laboratory|Radiology|Receptionist')->group(function () {
    Route::get('/procurement', [ProcurementRequestController::class, 'index'])->name('procurement.index');
    Route::get('/procurement/create', [ProcurementRequestController::class, 'create'])->name('procurement.create');
    Route::post('/procurement', [ProcurementRequestController::class, 'store'])->name('procurement.store');
    Route::get('/procurement/{request}', [ProcurementRequestController::class, 'show'])->name('procurement.show');
});

Route::middleware('role:Owner')->group(function () {
    Route::post('/procurement/{procurementRequest}/approve', [ProcurementApprovalController::class, 'approve'])->name('procurement.approve');
    Route::post('/procurement/{procurementRequest}/reject', [ProcurementApprovalController::class, 'reject'])->name('procurement.reject');
    Route::post('/procurement/bulk-approve', [ProcurementApprovalController::class, 'bulkApprove'])->name('procurement.bulk-approve');
});

Route::middleware('role:Owner|Pharmacy|Laboratory|Radiology')->group(function () {
    Route::get('/procurement/{procurementRequest}/receive', [ProcurementReceiptController::class, 'create'])->name('procurement.receive');
    Route::post('/procurement/{procurementRequest}/receive', [ProcurementReceiptController::class, 'store'])->name('procurement.receive.store');
    Route::post('/procurement/{procurementRequest}/upload-invoice', [ProcurementReceiptController::class, 'uploadInvoice'])->name('procurement.upload-invoice');
});

// ── Discount Requests (Receptionist + Pharmacy can request, Owner approves) ──
Route::middleware('role:Owner|Receptionist|Pharmacy')->group(function () {
    Route::post('/invoices/{invoice}/discount/request', [InvoiceDiscountController::class, 'requestDiscount'])->name('invoices.discount.request');
});

// ── Low Stock Alerts (Owner + Pharmacy + Laboratory + Radiology) ──
Route::middleware('role:Owner|Pharmacy|Laboratory|Radiology')->group(function () {
    Route::get('/dashboard/low-stock-alerts', [LowStockAlertController::class, 'index'])->name('dashboard.low-stock-alerts');
});

// ── Notifications (all authenticated users) ──
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

// ── Invoice PDF Download (all staff roles) ──
Route::middleware('role:Owner|Receptionist|Doctor|Pharmacy|Laboratory|Radiology|Triage')
    ->get('/invoices/{invoice}/pdf', [InvoicePdfController::class, 'download'])
    ->name('invoices.pdf');
Route::get('/notifications/unread', [NotificationController::class, 'unread'])->middleware('throttle:notifications-poll')->name('notifications.unread');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');

// ── MedGemma AI Analysis (with rate limiting) ──
Route::middleware(['role:Doctor', 'throttle:ai-analysis'])->group(function () {
    Route::post('/ai-analysis/consultation/{patient}', [AiAnalysisController::class, 'analyseConsultation'])->name('ai-analysis.consultation');
    Route::post('/ai-analysis/chat/{patient}', [AiAnalysisController::class, 'quickChat'])->name('ai-analysis.quick-chat');
    Route::get('/ai-analysis/patient/{patient}', [AiAnalysisController::class, 'patientAnalyses'])->name('ai-analysis.patient');
});

// Status check for polling (Doctor + Lab + Radiology)
Route::middleware('role:Doctor|Laboratory|Radiology')
    ->get('/ai-analysis/{analysis}/status', [AiAnalysisController::class, 'statusCheck'])
    ->name('ai-analysis.status-check');

Route::middleware(['role:Laboratory', 'throttle:ai-analysis'])->group(function () {
    Route::post('/ai-analysis/lab/{invoice}', [AiAnalysisController::class, 'analyseLab'])->name('ai-analysis.lab');
});

Route::middleware(['role:Radiology', 'throttle:ai-analysis'])->group(function () {
    Route::post('/ai-analysis/radiology/{invoice}', [AiAnalysisController::class, 'analyseRadiology'])->name('ai-analysis.radiology');
});

// ── Global Search (command palette) with rate limiting ──
Route::middleware('throttle:global-search')->group(function () {
    Route::get('/search/global', [SearchController::class, 'global'])->name('search.global');
});
