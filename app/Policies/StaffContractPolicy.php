<?php

namespace App\Policies;

use App\Models\StaffContract;
use App\Models\User;

class StaffContractPolicy
{
    /**
     * Owner has full access to everything.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->hasRole('Owner')) {
            return true;
        }
        return null;
    }

    /**
     * Only Owner can create contracts.
     */
    public function createContract(User $user): bool
    {
        return false; // Owner handled in before()
    }

    /**
     * Any staff member can view their own contract; Receptionist can view all.
     */
    public function view(User $user, StaffContract $contract): bool
    {
        if ($contract->user_id === $user->id) {
            return true;
        }
        return $user->hasRole('Receptionist');
    }

    /**
     * Any staff member can sign their own contract.
     */
    public function sign(User $user, StaffContract $contract): bool
    {
        return $contract->user_id === $user->id;
    }

    /**
     * Only Owner can update contracts (mark early exit, etc).
     */
    public function updateContract(User $user, StaffContract $contract): bool
    {
        return false; // Owner handled in before()
    }

    /**
     * Staff can view their own contracts; Owner can view any.
     */
    public function viewContract(User $user, User $staff): bool
    {
        if ($user->id === $staff->id) {
            return true;
        }
        return $user->hasRole('Owner');
    }
}
