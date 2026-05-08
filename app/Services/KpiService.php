<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\DoctorPayout;
use App\Models\StaffShift;
use App\Models\TriageVital;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiService
{
    /**
     * Net Profit Score: ((revenue_attributed - compensation_cost) / revenue_attributed) × 100
     * Returns 0 when no revenue exists to avoid division by zero.
     */
    public function staffNps(User $user, Carbon $from, Carbon $to): float
    {
        $revenue = $this->revenueAttributed($user, $from, $to);
        if ($revenue <= 0) {
            return 0.0;
        }

        $compensation = $this->compensationCost($user, $from, $to);
        return round((($revenue - $compensation) / $revenue) * 100, 1);
    }

    /**
     * Sum of paid invoice amounts where user was the performer or prescribing doctor.
     */
    public function revenueAttributed(User $user, Carbon $from, Carbon $to): float
    {
        return (float) Invoice::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->where(function ($q) use ($user) {
                $q->where('performed_by_user_id', $user->id)
                  ->orWhere('prescribing_doctor_id', $user->id);
            })
            ->sum('total_amount');
    }

    /**
     * Prorated base_salary + total payouts disbursed in the period.
     */
    public function compensationCost(User $user, Carbon $from, Carbon $to): float
    {
        $days        = max(1, $from->diffInDays($to) + 1);
        $monthDays   = (int) $from->daysInMonth;
        $salaryShare = ($user->base_salary ?? 0) * ($days / $monthDays);

        $payouts = DoctorPayout::where('doctor_id', $user->id)
            ->whereBetween('created_at', [$from, $to])
            ->where(function ($q) {
                $q->where('approval_status', 'approved')->orWhereNull('approval_status');
            })
            ->sum('total_amount');

        return (float) ($salaryShare + $payouts);
    }

    /**
     * Count patients seen by this doctor in the period.
     */
    public function patientsSeenCount(User $user, Carbon $from, Carbon $to): int
    {
        return (int) Patient::where('doctor_id', $user->id)
            ->whereBetween('doctor_started_at', [$from, $to])
            ->whereNotNull('doctor_started_at')
            ->count();
    }

    /**
     * Current pending queue count for a user (role-aware).
     */
    public function pendingQueueCount(User $user): int
    {
        $role = $user->getRoleNames()->first();

        return match ($role) {
            'Doctor' => Patient::where('doctor_id', $user->id)
                ->where('status', 'with_doctor')
                ->count(),
            'Pharmacy' => Invoice::where('department', 'pharmacy')
                ->where('status', 'in_progress')
                ->count(),
            'Laboratory' => Invoice::where('department', 'lab')
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
            'Radiology' => Invoice::where('department', 'radiology')
                ->whereIn('status', ['pending', 'in_progress'])
                ->count(),
            'Triage' => Patient::whereIn('status', ['registered', 'triage'])->count(),
            default => 0,
        };
    }

    /**
     * Average wait time in minutes: patient registered_at → first triage vital created_at.
     */
    public function avgWaitMinutes(User $user, Carbon $from, Carbon $to): float
    {
        $result = DB::select(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, p.created_at, tv.created_at)) AS avg_wait
             FROM triage_vitals tv
             JOIN patients p ON p.id = tv.patient_id
             WHERE tv.recorded_by = ?
               AND tv.created_at BETWEEN ? AND ?",
            [$user->id, $from, $to]
        );

        return round((float) ($result[0]->avg_wait ?? 0), 1);
    }

    /**
     * Count items processed (tests / scans / prescriptions) in the period.
     */
    public function itemsProcessedCount(User $user, Carbon $from, Carbon $to): int
    {
        $role = $user->getRoleNames()->first();

        $dept = match ($role) {
            'Laboratory' => 'lab',
            'Radiology'  => 'radiology',
            'Pharmacy'   => 'pharmacy',
            default      => null,
        };

        if ($dept === null) {
            return 0;
        }

        return (int) Invoice::where('department', $dept)
            ->where('performed_by_user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$from, $to])
            ->count();
    }

    /**
     * Count shifts and total hours worked in the period.
     * Returns ['shifts' => int, 'hours' => float]
     */
    public function shiftSummary(User $user, Carbon $from, Carbon $to): array
    {
        $shifts = StaffShift::where('user_id', $user->id)
            ->whereBetween('clocked_in_at', [$from, $to])
            ->whereNotNull('clocked_out_at')
            ->get();

        $totalMinutes = $shifts->sum(fn ($s) => $s->durationMinutes() ?? 0);

        return [
            'shifts' => $shifts->count(),
            'hours'  => round($totalMinutes / 60, 1),
        ];
    }

    /**
     * 3-tier classification by revenue (for non-GP doctors and clinical support roles).
     * T1 < 50k PKR | T2 50k–150k | T3 > 150k
     */
    public function revenueTier(float $revenue): int
    {
        if ($revenue >= 150_000) return 3;
        if ($revenue >= 50_000)  return 2;
        return 1;
    }

    /**
     * 3-tier classification by shifts attended (for support roles with no direct revenue).
     * T1 < 15 shifts | T2 15–19 | T3 ≥ 20
     */
    public function shiftTier(int $shifts): int
    {
        if ($shifts >= 20) return 3;
        if ($shifts >= 15) return 2;
        return 1;
    }

    /**
     * Days attended (shifts with clock-in) in the period.
     */
    public function daysAttended(User $user, Carbon $from, Carbon $to): int
    {
        return (int) StaffShift::where('user_id', $user->id)
            ->whereBetween('clocked_in_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT DATE(clocked_in_at)) AS days')
            ->value('days');
    }

    /**
     * Patients per working day (for GP tier calculation).
     */
    public function patientsPerWorkingDay(User $user, Carbon $from, Carbon $to): float
    {
        $days = max(1, $this->daysAttended($user, $from, $to));
        return round($this->patientsSeenCount($user, $from, $to) / $days, 1);
    }
}
