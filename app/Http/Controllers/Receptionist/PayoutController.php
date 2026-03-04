<?php

namespace App\Http\Controllers\Receptionist;

use App\Http\Controllers\Controller;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Services\DoctorPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PayoutController extends Controller
{
    protected DoctorPayoutService $payoutService;

    public function __construct(DoctorPayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }

    /**
     * Payout dashboard showing all commission-earning staff with department breakdown.
     * Distinguishes doctors (daily commission) from other staff (monthly salary+commission).
     */
    public function dashboard(): View
    {
        $staff = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Owner'))
            ->whereIn('compensation_type', ['commission', 'hybrid'])
            ->orderBy('name')
            ->get();

        $doctorCards = [];
        $staffCards = [];

        foreach ($staff as $member) {
            // Get unpaid commission totals grouped by department (via invoice join)
            $deptBreakdown = RevenueLedger::where('revenue_ledgers.user_id', $member->id)
                ->where('revenue_ledgers.category', 'commission')
                ->whereNull('revenue_ledgers.payout_id')
                ->join('invoices', 'revenue_ledgers.invoice_id', '=', 'invoices.id')
                ->groupBy('invoices.department')
                ->selectRaw('invoices.department, SUM(revenue_ledgers.amount) as total')
                ->get()
                ->keyBy('department')
                ->toArray();

            $totalUnpaid = array_sum(array_column($deptBreakdown, 'total'));

            $isDoctor = $member->hasRole('Doctor');

            // Skip staff with no unpaid earnings (but keep non-doctor staff with salary)
            if ($totalUnpaid <= 0 && ($isDoctor || (float) ($member->base_salary ?? 0) <= 0)) {
                continue;
            }

            $card = [
                'id'             => $member->id,
                'name'           => $member->name,
                'roles'          => $member->getRoleNames()->implode(', '),
                'totalUnpaid'    => $totalUnpaid,
                'deptBreakdown'  => $deptBreakdown,
                'baseSalary'     => (float) ($member->base_salary ?? 0),
                'compensationType' => $member->compensation_type,
            ];

            if ($isDoctor) {
                $doctorCards[] = $card;
            } else {
                $staffCards[] = $card;
            }
        }

        return view('receptionist.payouts.dashboard', [
            'doctorCards' => $doctorCards,
            'staffCards'  => $staffCards,
        ]);
    }

    /**
     * One-click full payout for a doctor — pays all unpaid commission.
     */
    public function quickPay(Request $request, User $user): RedirectResponse
    {
        try {
            $unpaidTotal = (float) RevenueLedger::where('user_id', $user->id)
                ->where('category', 'commission')
                ->whereNull('payout_id')
                ->sum('amount');

            if ($unpaidTotal <= 0) {
                return redirect()->route('receptionist.payouts.dashboard')
                    ->with('error', "No unpaid commission for {$user->name}.");
            }

            // Doctor → daily commission payout, no approval needed
            // Other staff → monthly payout with salary, needs owner approval
            if ($user->hasRole('Doctor')) {
                $payout = $this->payoutService->generatePayout(
                    $user,
                    null,
                    null,
                    $unpaidTotal,
                    Auth::user()
                );
                $msg = "Payout of " . number_format($unpaidTotal, 2) . " generated for {$user->name}. Payout #{$payout->id}";
            } else {
                $salary = (float) ($user->base_salary ?? 0);
                $totalAmount = $unpaidTotal + $salary;
                $payout = $this->payoutService->generateMonthlyPayout(
                    $user,
                    null,
                    null,
                    $totalAmount,
                    Auth::user(),
                    $salary
                );
                $msg = "Monthly payout of " . number_format($totalAmount, 2) . " (salary: " . number_format($salary, 2) . " + commission: " . number_format($unpaidTotal, 2) . ") generated for {$user->name}. Awaiting owner approval. Payout #{$payout->id}";
            }

            return redirect()->route('receptionist.payouts.dashboard')
                ->with('success', $msg);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('receptionist.payouts.dashboard')
                ->with('error', $e->getMessage());
        }
    }
}
