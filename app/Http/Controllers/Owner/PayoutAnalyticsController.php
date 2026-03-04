<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\DoctorPayout;
use App\Models\Invoice;
use App\Models\RevenueLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutAnalyticsController extends Controller
{
    /**
     * Owner payout dashboard — staff overview + payouts needing approval + all payouts.
     */
    public function index(Request $request): View
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::today()->subDays(30)->startOfDay();
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))->endOfDay()     : Carbon::today()->endOfDay();

        $query = DoctorPayout::with('doctor', 'creator')
            ->whereBetween('created_at', [$from, $to])
            ->latest();

        if ($request->filled('staff_id')) {
            $query->where('doctor_id', $request->query('staff_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $payouts = $query->paginate(20)->withQueryString();

        // Summary stats (for the filtered period)
        $totalPaid      = DoctorPayout::whereBetween('created_at', [$from, $to])->sum('paid_amount');
        $pendingCount   = DoctorPayout::whereBetween('created_at', [$from, $to])->where('status', 'pending')->count();
        $confirmedCount = DoctorPayout::whereBetween('created_at', [$from, $to])->where('status', 'confirmed')->count();

        // Staff list for filter dropdown
        $staffMembers = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Owner'))
            ->whereIn('compensation_type', ['commission', 'hybrid'])
            ->orderBy('name')
            ->get(['id', 'name']);

        // ---- Staff overview: unpaid commission per staff ----
        $allStaff = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Owner'))
            ->whereIn('compensation_type', ['commission', 'hybrid'])
            ->orderBy('name')
            ->get();

        $staffOverview = [];
        foreach ($allStaff as $member) {
            $unpaid = (float) RevenueLedger::where('user_id', $member->id)
                ->where('category', 'commission')
                ->whereNull('payout_id')
                ->sum('amount');

            $totalPaidToStaff = (float) DoctorPayout::where('doctor_id', $member->id)
                ->where('status', 'confirmed')
                ->sum('paid_amount');

            $staffOverview[] = [
                'id'          => $member->id,
                'name'        => $member->name,
                'roles'       => $member->getRoleNames()->implode(', '),
                'isDoctor'    => $member->hasRole('Doctor'),
                'baseSalary'  => (float) ($member->base_salary ?? 0),
                'unpaid'      => $unpaid,
                'totalPaid'   => $totalPaidToStaff,
            ];
        }

        // ---- Payouts awaiting owner approval ----
        $awaitingApproval = DoctorPayout::with('doctor', 'creator')
            ->where('approval_status', DoctorPayout::APPROVAL_PENDING)
            ->latest()
            ->get();

        return view('owner.payouts.index', [
            'payouts'          => $payouts,
            'totalPaid'        => $totalPaid,
            'pendingCount'     => $pendingCount,
            'confirmedCount'   => $confirmedCount,
            'staffMembers'     => $staffMembers,
            'staffOverview'    => $staffOverview,
            'awaitingApproval' => $awaitingApproval,
            'filters'          => $request->only(['staff_id', 'status', 'from', 'to']),
            'from'             => $from->format('Y-m-d'),
            'to'               => $to->format('Y-m-d'),
        ]);
    }

    /**
     * Staff performance deep-dive — department breakdown + analytics.
     */
    public function staffPerformance(Request $request, User $user): View
    {
        $from = $request->query('from') ? Carbon::parse($request->query('from'))->startOfDay() : Carbon::today()->subDays(30)->startOfDay();
        $to   = $request->query('to')   ? Carbon::parse($request->query('to'))->endOfDay()     : Carbon::today()->endOfDay();

        // ---- Department earnings breakdown ----
        // Commission earnings from RevenueLedger grouped by invoice department
        $deptEarnings = RevenueLedger::where('user_id', $user->id)
            ->where('category', 'commission')
            ->whereBetween('revenue_ledgers.created_at', [$from, $to])
            ->join('invoices', 'revenue_ledgers.invoice_id', '=', 'invoices.id')
            ->groupBy('invoices.department')
            ->selectRaw('invoices.department, SUM(revenue_ledgers.amount) as total, COUNT(DISTINCT invoices.id) as invoice_count')
            ->get()
            ->keyBy('department')
            ->toArray();

        $totalEarnings = array_sum(array_column($deptEarnings, 'total'));

        // ---- Lab ordering analysis (invoices prescribed by this doctor) ----
        $labOrders = Invoice::where('prescribing_doctor_id', $user->id)
            ->where('department', 'lab')
            ->where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(COALESCE(net_amount, total_amount)) as total_value, AVG(COALESCE(net_amount, total_amount)) as avg_value')
            ->first();

        // ---- Radiology ordering analysis ----
        $radOrders = Invoice::where('prescribing_doctor_id', $user->id)
            ->where('department', 'radiology')
            ->where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(COALESCE(net_amount, total_amount)) as total_value, AVG(COALESCE(net_amount, total_amount)) as avg_value')
            ->first();

        // ---- Pharmacy orders ----
        $pharmacyOrders = Invoice::where('prescribing_doctor_id', $user->id)
            ->where('department', 'pharmacy')
            ->where('status', Invoice::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('COUNT(*) as count, SUM(COALESCE(net_amount, total_amount)) as total_value')
            ->first();

        // ---- Top earning services ----
        $topServices = RevenueLedger::where('revenue_ledgers.user_id', $user->id)
            ->where('revenue_ledgers.category', 'commission')
            ->whereBetween('revenue_ledgers.created_at', [$from, $to])
            ->join('invoices', 'revenue_ledgers.invoice_id', '=', 'invoices.id')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->leftJoin('service_catalog', 'invoice_items.service_catalog_id', '=', 'service_catalog.id')
            ->groupBy('service_catalog.name', 'invoices.department')
            ->selectRaw('COALESCE(service_catalog.name, invoices.service_name) as service_name, invoices.department, SUM(revenue_ledgers.amount) as total_earned, COUNT(DISTINCT invoices.id) as times_used')
            ->orderByDesc('total_earned')
            ->limit(10)
            ->get();

        // ---- Payout history ----
        $payouts = DoctorPayout::where('doctor_id', $user->id)
            ->with('creator')
            ->latest()
            ->limit(20)
            ->get();

        // ---- Unpaid commission balance ----
        $unpaidBalance = RevenueLedger::where('user_id', $user->id)
            ->where('category', 'commission')
            ->whereNull('payout_id')
            ->sum('amount');

        return view('owner.payouts.performance', [
            'staff'          => $user,
            'from'           => $from->format('Y-m-d'),
            'to'             => $to->format('Y-m-d'),
            'deptEarnings'   => $deptEarnings,
            'totalEarnings'  => $totalEarnings,
            'labOrders'      => $labOrders,
            'radOrders'      => $radOrders,
            'pharmacyOrders' => $pharmacyOrders,
            'topServices'    => $topServices,
            'payouts'        => $payouts,
            'unpaidBalance'  => $unpaidBalance,
        ]);
    }
}
