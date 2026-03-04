<?php

namespace App\Http\Controllers\Radiology;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Models\ProcurementRequest;
use Illuminate\View\View;

class RadiologyDashboardController extends Controller
{
    public function index(): View
    {
        $pendingInvoices = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_PENDING)->count();
        $inProgressCount = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_IN_PROGRESS)->count();
        $completedToday = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_COMPLETED)
            ->whereDate('updated_at', today())->count();
        $paidToday = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_PAID)
            ->whereDate('updated_at', today())->count();

        $lowStockItems = InventoryItem::where('department', 'radiology')
            ->where('minimum_stock_level', '>', 0)
            ->get()
            ->filter(fn ($item) => $item->stockMovements()->sum('quantity') < $item->minimum_stock_level)
            ->count();

        $pendingProcurements = ProcurementRequest::where('department', 'radiology')
            ->where('status', 'pending')->count();

        // Paid invoices awaiting work (upfront-payment flow)
        $paidAwaitingWork = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_PAID)
            ->whereNull('performed_by_user_id')
            ->count();

        $workQueue = Invoice::where('department', 'radiology')
            ->where(function ($q) {
                $q->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_IN_PROGRESS])
                  ->orWhere(function ($q2) {
                      // Upfront-paid: work not yet finished (no report or no performer)
                      $q2->where('status', Invoice::STATUS_PAID)
                         ->where(function ($q3) {
                             $q3->whereNull('performed_by_user_id')
                                ->orWhereNull('report_text');
                         });
                  });
            })
            ->with('patient', 'prescribingDoctor')
            ->latest()
            ->limit(20)
            ->get();

        $recentCompleted = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_COMPLETED)
            ->with('patient')
            ->latest()
            ->limit(10)
            ->get();

        return view('radiology.dashboard', compact(
            'pendingInvoices',
            'inProgressCount',
            'completedToday',
            'paidToday',
            'paidAwaitingWork',
            'lowStockItems',
            'pendingProcurements',
            'workQueue',
            'recentCompleted'
        ));
    }
}
