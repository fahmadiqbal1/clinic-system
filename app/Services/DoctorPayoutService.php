<?php

namespace App\Services;

use App\Models\DoctorPayout;
use App\Models\RevenueLedger;
use App\Models\User;
use App\Notifications\PayoutGenerated;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DoctorPayoutService
{
    /**
     * Generate a daily commission payout for a doctor.
     * When periodStart/periodEnd are null, ALL unpaid entries are collected.
     */
    public function generatePayout(
        User $staff,
        ?Carbon $periodStart,
        ?Carbon $periodEnd,
        float $paidAmount,
        User $createdBy
    ): DoctorPayout {
        if ($periodStart && $periodEnd && $periodStart > $periodEnd) {
            throw new InvalidArgumentException('Period start must be before or equal to period end');
        }

        if ($paidAmount < 0) {
            throw new InvalidArgumentException('Paid amount cannot be negative');
        }

        $payout = DB::transaction(function () use ($staff, $periodStart, $periodEnd, $paidAmount, $createdBy) {
            $query = RevenueLedger::where('user_id', $staff->id)
                ->where('category', 'commission')
                ->whereNull('payout_id')
                ->lockForUpdate();

            if ($periodStart && $periodEnd) {
                $query->whereBetween('created_at', [$periodStart, $periodEnd]);
            }

            $unpaidLedgers = $query->get();

            if ($unpaidLedgers->isEmpty()) {
                throw new InvalidArgumentException(
                    "No unpaid commission entries found for {$staff->name}"
                );
            }

            $totalAmount = (float) $unpaidLedgers->sum('amount');

            if ($paidAmount > $totalAmount) {
                throw new InvalidArgumentException(
                    "Paid amount (" . number_format($paidAmount, 2) . ") exceeds total eligible earnings (" . number_format($totalAmount, 2) . ")"
                );
            }

            // Derive actual date range from ledger entries when no period specified
            $actualStart = $periodStart ?? $unpaidLedgers->min('created_at');
            $actualEnd = $periodEnd ?? $unpaidLedgers->max('created_at');

            $payout = DoctorPayout::create([
                'doctor_id' => $staff->id,
                'period_start' => Carbon::parse($actualStart)->toDateString(),
                'period_end' => Carbon::parse($actualEnd)->toDateString(),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'status' => 'pending',
                'payout_type' => DoctorPayout::TYPE_COMMISSION,
                'approval_status' => null, // doctors need no approval
                'created_by' => $createdBy->id,
            ]);

            foreach ($unpaidLedgers as $ledger) {
                $ledger->update(['payout_id' => $payout->id]);
            }

            return $payout;
        });

        // Notify the staff member outside the transaction
        $staff->notify(new PayoutGenerated($payout));

        return $payout;
    }

    /**
     * Generate a monthly payout for non-doctor staff (salary + commission).
     * Requires owner approval before staff can confirm.
     */
    public function generateMonthlyPayout(
        User $staff,
        ?Carbon $periodStart,
        ?Carbon $periodEnd,
        float $paidAmount,
        User $createdBy,
        ?float $salaryAmount = null
    ): DoctorPayout {
        if ($periodStart && $periodEnd && $periodStart > $periodEnd) {
            throw new InvalidArgumentException('Period start must be before or equal to period end');
        }

        if ($paidAmount < 0) {
            throw new InvalidArgumentException('Paid amount cannot be negative');
        }

        // Use the staff member's base salary if none provided
        $salary = $salaryAmount ?? (float) ($staff->base_salary ?? 0);

        $payout = DB::transaction(function () use ($staff, $periodStart, $periodEnd, $paidAmount, $createdBy, $salary) {
            $query = RevenueLedger::where('user_id', $staff->id)
                ->where('category', 'commission')
                ->whereNull('payout_id')
                ->lockForUpdate();

            if ($periodStart && $periodEnd) {
                $query->whereBetween('created_at', [$periodStart, $periodEnd]);
            }

            $unpaidLedgers = $query->get();

            $commissionTotal = $unpaidLedgers->isEmpty() ? 0 : (float) $unpaidLedgers->sum('amount');
            $totalAmount = $commissionTotal + $salary;

            if ($paidAmount > $totalAmount) {
                throw new InvalidArgumentException(
                    "Paid amount (" . number_format($paidAmount, 2) . ") exceeds total eligible amount (" . number_format($totalAmount, 2) . ")"
                );
            }

            // Derive actual date range from ledger entries or use provided period
            $actualStart = $periodStart ?? ($unpaidLedgers->isNotEmpty() ? $unpaidLedgers->min('created_at') : now()->startOfMonth());
            $actualEnd = $periodEnd ?? ($unpaidLedgers->isNotEmpty() ? $unpaidLedgers->max('created_at') : now()->endOfMonth());

            $payout = DoctorPayout::create([
                'doctor_id' => $staff->id,
                'period_start' => Carbon::parse($actualStart)->toDateString(),
                'period_end' => Carbon::parse($actualEnd)->toDateString(),
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'salary_amount' => $salary,
                'status' => 'pending',
                'payout_type' => DoctorPayout::TYPE_MONTHLY,
                'approval_status' => DoctorPayout::APPROVAL_PENDING,
                'created_by' => $createdBy->id,
            ]);

            // Link commission ledger entries to this payout
            foreach ($unpaidLedgers as $ledger) {
                $ledger->update(['payout_id' => $payout->id]);
            }

            return $payout;
        });

        $staff->notify(new PayoutGenerated($payout));

        return $payout;
    }

    /**
     * Approve a monthly payout (Owner action)
     */
    public function approvePayout(DoctorPayout $payout, User $owner): void
    {
        if (!$payout->needsApproval()) {
            throw new InvalidArgumentException('This payout does not require approval');
        }

        if ($payout->approval_status !== DoctorPayout::APPROVAL_PENDING) {
            throw new InvalidArgumentException('Payout is not pending approval');
        }

        $payout->update([
            'approval_status' => DoctorPayout::APPROVAL_APPROVED,
            'approved_by' => $owner->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject a monthly payout (Owner action).
     * Releases tied commission ledger entries so they can be included in a future payout.
     */
    public function rejectPayout(DoctorPayout $payout, User $owner, ?string $reason = null): void
    {
        if (!$payout->needsApproval()) {
            throw new InvalidArgumentException('This payout does not require approval');
        }

        if ($payout->approval_status !== DoctorPayout::APPROVAL_PENDING) {
            throw new InvalidArgumentException('Payout is not pending approval');
        }

        DB::transaction(function () use ($payout, $owner, $reason) {
            // Release commission ledger entries so they can be re-used in a new payout
            RevenueLedger::where('payout_id', $payout->id)->update(['payout_id' => null]);

            $payout->update([
                'approval_status' => DoctorPayout::APPROVAL_REJECTED,
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'notes' => $reason ? "Rejected: {$reason}" : 'Rejected by owner',
            ]);
        });
    }

    /**
     * Confirm a payout (staff member acknowledges receipt)
     *
     * @param DoctorPayout $payout
     * @param User $staff Confirming staff (must match payout.doctor_id)
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return void
     * @throws InvalidArgumentException
     */
    public function confirmPayout(
        DoctorPayout $payout,
        User $staff,
        string $ipAddress = null,
        string $userAgent = null
    ): void {
        if ($payout->doctor_id !== $staff->id) {
            throw new InvalidArgumentException('Staff can only confirm their own payout');
        }

        if ($payout->status === 'confirmed') {
            throw new InvalidArgumentException('Payout already confirmed');
        }

        if ($payout->correction_of_id !== null) {
            throw new InvalidArgumentException('Correction payouts cannot be confirmed');
        }

        if (!$payout->canBeConfirmed()) {
            throw new InvalidArgumentException('This payout cannot be confirmed yet — it may still require owner approval');
        }

        DB::transaction(function () use ($payout, $staff, $ipAddress, $userAgent) {
            $payout->update([
                'status' => 'confirmed',
                'confirmed_by' => $staff->id,
                'confirmed_at' => now(),
            ]);
        });
    }

    /**
     * Create a correction payout (Owner-only action)
     *
     * Correction payouts are adjustment entries that reference the original payout
     * They are created as confirmed immediately
     *
     * @param DoctorPayout $originalPayout
     * @param float $amount Can be positive or negative
     * @param User $createdBy
     * @return DoctorPayout
     * @throws InvalidArgumentException
     */
    public function createCorrection(
        DoctorPayout $originalPayout,
        float $amount,
        User $createdBy,
        ?string $notes = null
    ): DoctorPayout {
        if ($originalPayout->correction_of_id !== null) {
            throw new InvalidArgumentException('Cannot create correction of a correction payout');
        }

        if ($amount == 0) {
            throw new InvalidArgumentException('Correction amount cannot be zero');
        }

        return DB::transaction(function () use ($originalPayout, $amount, $createdBy, $notes) {
            $correction = DoctorPayout::create([
                'doctor_id' => $originalPayout->doctor_id,
                'period_start' => $originalPayout->period_start,
                'period_end' => $originalPayout->period_end,
                'total_amount' => $amount,
                'paid_amount' => $amount,
                'status' => 'confirmed',
                'created_by' => $createdBy->id,
                'confirmed_by' => $createdBy->id,
                'confirmed_at' => now(),
                'correction_of_id' => $originalPayout->id,
                'notes' => $notes,
            ]);

            return $correction;
        });
    }

    /**
     * Get unpaid earnings for a doctor
     *
     * @param User $doctor
     * @return float
     */
    public function getUnpaidEarnings(User $doctor): float
    {
        return (float) RevenueLedger::where('user_id', $doctor->id)
            ->where('category', 'commission')
            ->whereNull('payout_id')
            ->sum('amount');
    }

    /**
     * Get pending payouts for a doctor
     *
     * @param User $doctor
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPendingPayouts(User $doctor)
    {
        return DoctorPayout::where('doctor_id', $doctor->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get payout history for a doctor
     *
     * @param User $doctor
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPayoutHistory(User $doctor, int $limit = 20)
    {
        return DoctorPayout::where('doctor_id', $doctor->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
