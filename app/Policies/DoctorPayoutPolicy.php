<?php

namespace App\Policies;

use App\Models\DoctorPayout;
use App\Models\User;

class DoctorPayoutPolicy
{
    /**
     * Owner can do everything.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('Owner')) {
            return true;
        }
        return null;
    }

    /**
     * Who can view the list of payouts.
     */
    public function viewAny(User $user): bool
    {
        // Any commission-earning staff can view their own payouts (filtered in controller)
        // Receptionist can view all payouts
        return $user->hasAnyRole(['Doctor', 'Laboratory', 'Radiology', 'Pharmacy', 'Receptionist']);
    }

    /**
     * Who can view a specific payout.
     */
    public function view(User $user, DoctorPayout $payout): bool
    {
        // Staff can view their own payout
        if ($payout->doctor_id === $user->id) {
            return true;
        }
        return $user->hasRole('Receptionist');
    }

    /**
     * Who can create payouts (Owner + Receptionist).
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Receptionist');
    }

    /**
     * Who can confirm a payout (the staff member who owns it).
     */
    public function confirm(User $user, DoctorPayout $payout): bool
    {
        return $payout->doctor_id === $user->id && $payout->canBeConfirmed();
    }

    /**
     * Who can approve/reject payouts — Owner only (handled by before()).
     */
    public function approve(User $user, DoctorPayout $payout): bool
    {
        return false; // Owner only, handled by before()
    }

    /**
     * Who can create corrections.
     */
    public function createCorrection(User $user, DoctorPayout $payout): bool
    {
        return false; // Owner only, handled by before()
    }
}
