<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Prescription;
use App\Models\RevenueLedger;
use App\Models\DoctorPayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
    /**
     * Show the doctor dashboard - role isolated to own data only
     */
    public function index(): View|\Illuminate\Http\RedirectResponse
    {
        $user = auth()->guard('web')->user();

        // Independent doctors are redirected to their own dashboard
        if ($user->is_independent) {
            return redirect()->route('independent-doctor.dashboard');
        }
        
        // Patient counts by status
        $patientCount = \App\Models\Patient::where('doctor_id', $user->id)->count();
        $activePatients = \App\Models\Patient::where('doctor_id', $user->id)
            ->where('status', 'with_doctor')
            ->count();
        $completedToday = \App\Models\Patient::where('doctor_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', today())
            ->count();

        // Earnings data - ONLY THIS DOCTOR'S REVENUE
        $totalEarnings = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->sum('amount');

        $unpaidEarnings = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->whereNull('payout_id')
            ->sum('amount');

        $paidEarnings = $totalEarnings - $unpaidEarnings;

        // Pending payouts (awaiting doctor confirmation)
        $pendingPayouts = DoctorPayout::where('doctor_id', $user->id)
            ->where('status', 'pending')
            ->count();

        // Recent transactions (only this doctor)
        $recentTransactions = RevenueLedger::where('user_id', $user->id)
            ->where('role_type', 'Doctor')
            ->with('invoice')
            ->latest()
            ->limit(10)
            ->get();
        
        // Recent patients waiting for this doctor
        $waitingPatients = \App\Models\Patient::where('doctor_id', $user->id)
            ->where('status', 'with_doctor')
            ->latest('doctor_started_at')
            ->limit(5)
            ->get();
        
        // Invoice summary
        $invoiceCount = \App\Models\Invoice::where('prescribing_doctor_id', $user->id)->count();
        $todayInvoices = \App\Models\Invoice::where('prescribing_doctor_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        // Results ready — lab/rad invoices completed or work-completed (paid with revenue distributed)
        $resultsReadyCount = \App\Models\Invoice::where('prescribing_doctor_id', $user->id)
            ->whereIn('department', ['lab', 'radiology'])
            ->where(function ($q) {
                $q->where('status', \App\Models\Invoice::STATUS_COMPLETED)
                  ->orWhere(function ($q2) {
                      $q2->where('status', \App\Models\Invoice::STATUS_PAID)
                         ->whereHas('revenueLedgers');
                  });
            })
            ->count();

        // Recent prescriptions
        $recentPrescriptions = Prescription::where('doctor_id', $user->id)
            ->with(['patient', 'items'])
            ->latest()
            ->limit(5)
            ->get();

        // Today's appointments for timeline widget
        $todayAppointments = Appointment::forDoctor($user->id)
            ->with('patient')
            ->whereDate('scheduled_at', today())
            ->whereIn('status', ['scheduled', 'confirmed', 'in_progress'])
            ->orderBy('scheduled_at')
            ->limit(12)
            ->get();

        return view('doctor.dashboard', [
            'patientCount' => $patientCount,
            'activePatients' => $activePatients,
            'completedToday' => $completedToday,
            'totalEarnings' => $totalEarnings,
            'unpaidEarnings' => $unpaidEarnings,
            'paidEarnings' => $paidEarnings,
            'pendingPayouts' => $pendingPayouts,
            'recentTransactions' => $recentTransactions,
            'waitingPatients' => $waitingPatients,
            'invoiceCount' => $invoiceCount,
            'todayInvoices' => $todayInvoices,
            'resultsReadyCount' => $resultsReadyCount,
            'recentPrescriptions' => $recentPrescriptions,
            'todayAppointments' => $todayAppointments,
        ]);
    }
}
