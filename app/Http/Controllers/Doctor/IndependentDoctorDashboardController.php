<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\RevenueLedger;
use App\Models\DoctorPayout;
use Illuminate\View\View;

class IndependentDoctorDashboardController extends Controller
{
    /**
     * Show the independent doctor dashboard.
     * Only shows referral patients and their commission earnings.
     */
    public function index(): View
    {
        $user = auth()->user();

        abort_unless($user->is_independent, 403, 'Access restricted to independent doctors.');

        // Referral patient counts
        $totalReferrals = Patient::where('referred_by_user_id', $user->id)->count();
        $pendingReferrals = Patient::where('referred_by_user_id', $user->id)
            ->where('status', 'registered')
            ->count();
        $completedReferrals = Patient::where('referred_by_user_id', $user->id)
            ->where('status', 'completed')
            ->count();

        // Today's referrals
        $todayReferrals = Patient::where('referred_by_user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        // Earnings (commissions from referral invoices)
        $totalEarnings = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->sum('amount');

        $unpaidEarnings = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->whereNull('payout_id')
            ->sum('amount');

        $paidEarnings = $totalEarnings - $unpaidEarnings;

        // Pending payouts
        $pendingPayouts = DoctorPayout::where('doctor_id', $user->id)
            ->where('status', 'pending')
            ->count();

        // Invoice summary for this doctor's referrals
        $pendingInvoices = Invoice::where('prescribing_doctor_id', $user->id)
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_IN_PROGRESS])
            ->count();

        $completedInvoices = Invoice::where('prescribing_doctor_id', $user->id)
            ->whereIn('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_PAID])
            ->count();

        // Recent referral patients
        $recentPatients = Patient::where('referred_by_user_id', $user->id)
            ->with(['invoices' => fn ($q) => $q->whereIn('department', ['lab', 'radiology', 'pharmacy'])])
            ->latest()
            ->limit(10)
            ->get();

        // Recent earnings
        $recentTransactions = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->with('invoice')
            ->latest()
            ->limit(10)
            ->get();

        return view('independent-doctor.dashboard', [
            'totalReferrals'     => $totalReferrals,
            'pendingReferrals'   => $pendingReferrals,
            'completedReferrals' => $completedReferrals,
            'todayReferrals'     => $todayReferrals,
            'totalEarnings'      => $totalEarnings,
            'unpaidEarnings'     => $unpaidEarnings,
            'paidEarnings'       => $paidEarnings,
            'pendingPayouts'     => $pendingPayouts,
            'pendingInvoices'    => $pendingInvoices,
            'completedInvoices'  => $completedInvoices,
            'recentPatients'     => $recentPatients,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
