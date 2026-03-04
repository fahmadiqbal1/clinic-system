<?php

namespace App\Policies;

use App\Models\ProcurementRequest;
use App\Models\User;

class ProcurementRequestPolicy
{
    /**
     * Determine if the user can view any procurement requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']);
    }

    /**
     * Determine if the user can view a procurement request.
     */
    public function view(User $user, ProcurementRequest $procurementRequest): bool
    {
        // Owner sees all
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Creator sees their own
        if ($procurementRequest->requested_by === $user->id) {
            return true;
        }

        // Department staff can see requests for their department
        $userDeptRole = null;
        if ($user->hasRole('Pharmacy')) {
            $userDeptRole = 'pharmacy';
        } elseif ($user->hasRole('Laboratory')) {
            $userDeptRole = 'laboratory';
        } elseif ($user->hasRole('Radiology')) {
            $userDeptRole = 'radiology';
        } elseif ($user->hasRole('Receptionist')) {
            // Receptionist can see all departments
            return true;
        }

        return $userDeptRole === $procurementRequest->department;
    }

    /**
     * Determine if the user can create a procurement request.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']);
    }

    /**
     * Determine if the user can approve a procurement request.
     * Only Owner can approve.
     */
    public function approve(User $user, ProcurementRequest $procurementRequest): bool
    {
        return $user->hasRole('Owner') && in_array(
            $procurementRequest->status,
            ['pending']
        );
    }

    /**
     * Determine if the user can receive/record a procurement (inventory type only).
     */
    public function receive(User $user, ProcurementRequest $procurementRequest): bool
    {
        // Must be approved first
        if ($procurementRequest->status !== 'approved') {
            return false;
        }

        // Only inventory procurements can be received
        if ($procurementRequest->type !== 'inventory') {
            return false;
        }

        // Staff and Owner can receive
        return $user->hasAnyRole(['Owner', 'Pharmacy', 'Laboratory', 'Radiology', 'Receptionist']);
    }
}
