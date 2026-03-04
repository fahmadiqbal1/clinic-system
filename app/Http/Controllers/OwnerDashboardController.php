<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\InventoryItem;
use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerDashboardController extends Controller
{
    /**
     * Show the owner dashboard.
     */
    public function index(): View
    {
        $financialService = new FinancialReportService();

        // Count unpaid invoices (not in paid status)
        $unpaidInvoicesCount = Invoice::where('status', '!=', Invoice::STATUS_PAID)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->count();

        // Count pending work by department
        $pendingLab = Invoice::where('department', 'lab')
            ->where('status', Invoice::STATUS_PENDING)
            ->count();
        $pendingRadiology = Invoice::where('department', 'radiology')
            ->where('status', Invoice::STATUS_PENDING)
            ->count();
        $pendingPharmacy = Invoice::where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_PENDING)
            ->count();

        // Count low stock items
        $lowStockCount = InventoryItem::where('is_active', true)
            ->whereRaw('(
                SELECT COALESCE(SUM(quantity), 0) 
                FROM stock_movements 
                WHERE inventory_item_id = inventory_items.id
            ) <= minimum_stock_level')
            ->count();

        // Count pending discount requests
        $pendingDiscountCount = Invoice::where('discount_status', Invoice::DISCOUNT_PENDING)->count();

        // Fetch latest pending discount invoices for actionable list
        $pendingDiscounts = Invoice::where('discount_status', Invoice::DISCOUNT_PENDING)
            ->with('patient', 'discountRequester')
            ->latest()
            ->take(5)
            ->get();

        // 7-day revenue & expense trend for charts
        $trendLabels = [];
        $trendRevenue = [];
        $trendExpenses = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i);
            $trendLabels[] = $day->format('D');
            $trendRevenue[] = round($financialService->getRevenueBetween($day->copy()->startOfDay(), $day->copy()->endOfDay()), 2);
            $trendExpenses[] = round($financialService->getExpensesBetween($day->copy()->startOfDay(), $day->copy()->endOfDay()), 2);
        }

        // Department distribution (today's invoices by department)
        $deptCounts = Invoice::whereDate('created_at', Carbon::today())
            ->selectRaw('department, COUNT(*) as cnt')
            ->groupBy('department')
            ->pluck('cnt', 'department')
            ->toArray();

        return view('owner.dashboard', [
            'today_revenue' => $financialService->getTodayRevenue(),
            'today_expenses' => $financialService->getTodayExpenses(),
            'today_net' => $financialService->getTodayNetProfit(),
            'pending_payout_total' => $financialService->getPendingPayouts(),
            'pending_payout_count' => $financialService->getPendingPayoutsCount(),
            'unpaid_invoices_count' => $unpaidInvoicesCount,
            'pending_lab_count' => $pendingLab,
            'pending_radiology_count' => $pendingRadiology,
            'pending_pharmacy_count' => $pendingPharmacy,
            'low_stock_count' => $lowStockCount,
            'pending_discount_count' => $pendingDiscountCount,
            'pending_discounts' => $pendingDiscounts,
            'trend_labels' => $trendLabels,
            'trend_revenue' => $trendRevenue,
            'trend_expenses' => $trendExpenses,
            'dept_counts' => $deptCounts,
        ]);
    }

    /**
     * Show the financial report.
     */
    public function financialReport(Request $request): View
    {
        $from = $request->query('from') ? Carbon::createFromFormat('Y-m-d', $request->query('from'))->startOfDay() : Carbon::today()->startOfDay();
        $to = $request->query('to') ? Carbon::createFromFormat('Y-m-d', $request->query('to'))->endOfDay() : Carbon::today()->endOfDay();

        // Ensure from is before or equal to to
        if ($from > $to) {
            $from = Carbon::today()->startOfDay();
            $to = Carbon::today()->endOfDay();
        }

        $financialService = new FinancialReportService();

        return view('owner.financial-report', [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'revenue' => $financialService->getRevenueBetween($from, $to),
            'expenses' => $financialService->getExpensesBetween($from, $to),
            'net_profit' => $financialService->getNetProfitBetween($from, $to),
            'department_breakdown' => $financialService->getDepartmentBreakdown($from, $to),
            'doctor_breakdown' => $financialService->getDoctorBreakdown($from, $to),
        ]);
    }

    /**
     * Show comprehensive Department P&L report.
     */
    public function departmentPnl(Request $request): View
    {
        $from = $request->query('from') ? Carbon::createFromFormat('Y-m-d', $request->query('from'))->startOfDay() : Carbon::today()->startOfMonth()->startOfDay();
        $to = $request->query('to') ? Carbon::createFromFormat('Y-m-d', $request->query('to'))->endOfDay() : Carbon::today()->endOfDay();

        if ($from > $to) {
            $from = Carbon::today()->startOfMonth()->startOfDay();
            $to = Carbon::today()->endOfDay();
        }

        $financialService = new FinancialReportService();
        $pnl = $financialService->getDepartmentPnl($from, $to);

        // Calculate totals
        $totals = [
            'revenue' => 0, 'cogs' => 0, 'commissions' => 0,
            'expenses_fixed' => 0, 'expenses_variable' => 0, 'expenses_procurement' => 0,
            'total_expenses' => 0, 'net_profit' => 0,
        ];
        foreach ($pnl as $data) {
            foreach ($totals as $key => &$val) {
                $val += $data[$key] ?? 0;
            }
        }

        return view('owner.department-pnl', [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'pnl' => $pnl,
            'totals' => $totals,
        ]);
    }

    /**
     * Show the activity feed / audit timeline.
     */
    public function activityFeed(Request $request): View
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $logs = $query->paginate(50)->withQueryString();

        // Get unique actions for filter dropdown
        $actions = AuditLog::distinct()->pluck('action')->sort()->values();

        // Get users for filter
        $users = \App\Models\User::orderBy('name')->get(['id', 'name']);

        return view('owner.activity-feed', compact('logs', 'actions', 'users'));
    }
}
