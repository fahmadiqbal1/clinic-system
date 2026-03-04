<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use App\Models\Prescription;
use Illuminate\View\View;

class PharmacyDashboardController extends Controller
{
    public function index(): View
    {
        $pendingInvoices = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_PENDING)->count();
        $inProgressCount = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_IN_PROGRESS)->count();
        $awaitingPayment = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_COMPLETED)->count();
        $paidToday = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_PAID)
            ->whereDate('updated_at', today())->count();

        $lowStockItems = InventoryItem::where('department', 'pharmacy')
            ->where('minimum_stock_level', '>', 0)
            ->get()
            ->filter(fn ($item) => $item->stockMovements()->sum('quantity') < $item->minimum_stock_level)
            ->count();

        $pendingProcurements = ProcurementRequest::where('department', 'pharmacy')
            ->where('status', 'pending')->count();

        $pendingPrescriptions = Prescription::where('status', 'active')->count();

        $workQueue = Invoice::where('department', 'pharmacy')
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_IN_PROGRESS])
            ->with(['patient', 'prescribingDoctor'])
            ->latest()
            ->limit(20)
            ->get();

        $readyForPayment = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_COMPLETED)
            ->with('patient')
            ->latest()
            ->limit(10)
            ->get();

        return view('pharmacy.dashboard', compact(
            'pendingInvoices',
            'inProgressCount',
            'awaitingPayment',
            'paidToday',
            'lowStockItems',
            'pendingProcurements',
            'pendingPrescriptions',
            'workQueue',
            'readyForPayment'
        ));
    }
}
