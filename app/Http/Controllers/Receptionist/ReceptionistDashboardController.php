<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\RevenueLedger;
use App\Models\User;
use Illuminate\View\View;

class ReceptionistDashboardController extends Controller
{
    /**
     * Show the receptionist dashboard - can view doctor earnings but not commission %
     */
    public function index(): View
    {
        $registeredCount = Patient::where('status', 'registered')->count();
        $triageCount = Patient::where('status', 'triage')->count();
        $withDoctorCount = Patient::where('status', 'with_doctor')->count();

        // Count unpaid invoices READY for payment (completed or in_progress, non-pharmacy — receptionists can't pay pharmacy)
        $unpaidInvoicesCount = Invoice::whereIn('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_IN_PROGRESS])
            ->where('department', '!=', 'pharmacy')
            ->count();

        // Count invoices pending payment (not yet completed by departments)
        $pendingPaymentCount = Invoice::where('status', '!=', Invoice::STATUS_PAID)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->count();

        // Pending upfront payment (lab/radiology invoices in pending status)
        $pendingUpfrontCount = Invoice::where('status', Invoice::STATUS_PENDING)
            ->whereIn('department', ['lab', 'radiology'])
            ->count();

        // Pending discount requests awaiting Owner approval
        $pendingDiscountCount = Invoice::where('discount_status', Invoice::DISCOUNT_PENDING)->count();

        // Get commission-earning doctors with today's earnings & unpaid totals
        // Salaried doctors are excluded — they don't receive daily payouts
        $doctors = User::role('Doctor')
            ->whereIn('compensation_type', ['commission', 'hybrid'])
            ->get();
        $doctorEarnings = [];
        
        $today = now()->format('Y-m-d');
        
        foreach ($doctors as $doctor) {
            $todayEarnings = RevenueLedger::where('user_id', $doctor->id)
                ->where('role_type', 'Doctor')
                ->whereDate('created_at', $today)
                ->sum('amount');
            
            $unpaid = RevenueLedger::where('user_id', $doctor->id)
                ->where('role_type', 'Doctor')
                ->whereNull('payout_id')
                ->sum('amount');

            if ($todayEarnings > 0 || $unpaid > 0) {
                $doctorEarnings[$doctor->id] = [
                    'id' => $doctor->id,
                    'name' => $doctor->name,
                    'todayEarnings' => $todayEarnings,
                    'unpaidEarnings' => $unpaid,
                ];
            }
        }

        // Recent patients for quick overview
        $recentPatients = Patient::latest()
            ->limit(5)
            ->get();

        // Unpaid invoices list (completed or in_progress, non-pharmacy, awaiting payment)
        $unpaidInvoices = Invoice::whereIn('status', [Invoice::STATUS_COMPLETED, Invoice::STATUS_IN_PROGRESS])
            ->where('department', '!=', 'pharmacy')
            ->with('patient')
            ->latest()
            ->limit(10)
            ->get();

        // Pending upfront invoices (lab/radiology pending)
        $pendingUpfrontInvoices = Invoice::where('status', Invoice::STATUS_PENDING)
            ->whereIn('department', ['lab', 'radiology'])
            ->with('patient')
            ->latest()
            ->limit(10)
            ->get();

        // Today's paid count
        $paidTodayCount = Invoice::where('status', Invoice::STATUS_PAID)
            ->whereDate('updated_at', now()->format('Y-m-d'))
            ->count();

        return view('receptionist.dashboard', [
            'registeredCount' => $registeredCount,
            'triageCount' => $triageCount,
            'withDoctorCount' => $withDoctorCount,
            'unpaidInvoicesCount' => $unpaidInvoicesCount,
            'pendingPaymentCount' => $pendingPaymentCount,
            'pendingUpfrontCount' => $pendingUpfrontCount,
            'pendingDiscountCount' => $pendingDiscountCount,
            'paidTodayCount' => $paidTodayCount,
            'doctorEarnings' => $doctorEarnings,
            'recentPatients' => $recentPatients,
            'unpaidInvoices' => $unpaidInvoices,
            'pendingUpfrontInvoices' => $pendingUpfrontInvoices,
        ]);
    }
}
