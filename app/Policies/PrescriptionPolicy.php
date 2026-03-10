<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    /**
     * Determine if user can view the prescription.
     */
    public function view(User $user, Prescription $prescription): bool
    {
        // Owner can view all prescriptions
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Prescribing doctor can view their prescriptions
        if ($prescription->doctor_id === $user->id) {
            return true;
        }

        // Pharmacy can view prescriptions for dispensing
        if ($user->hasRole('Pharmacy')) {
            return true;
        }

        // Receptionist can view for invoicing
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can create a prescription.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Doctor');
    }

    /**
     * Determine if user can update the prescription.
     */
    public function update(User $user, Prescription $prescription): bool
    {
        // Owner can update any prescription
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Prescribing doctor can update their own prescriptions
        // Only if not yet dispensed
        if ($prescription->doctor_id === $user->id && $prescription->status !== 'dispensed') {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete the prescription.
     */
    public function delete(User $user, Prescription $prescription): bool
    {
        // Owner can delete
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Doctor can delete their own pending prescriptions
        if ($prescription->doctor_id === $user->id && $prescription->status === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can dispense the prescription.
     */
    public function dispense(User $user, Prescription $prescription): bool
    {
        return $user->hasRole('Pharmacy') && $prescription->status !== 'dispensed';
    }
}
