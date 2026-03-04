<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Determine if user can view patient.
     */
    public function view(User $user, Patient $patient): bool
    {
        // Owner can view all patients
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Receptionist can view all patients
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        // Triage can view all patients
        if ($user->hasRole('Triage')) {
            return true;
        }

        // Doctor can view only their assigned patients
        if ($user->hasRole('Doctor') && $patient->doctor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can update patient.
     */
    public function update(User $user, Patient $patient): bool
    {
        // Owner can update any patient
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Receptionist can update patient details
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        // Triage can update vital signs / status
        if ($user->hasRole('Triage')) {
            return true;
        }

        // Doctor can only update consultation notes for their patient
        if ($user->hasRole('Doctor') && $patient->doctor_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete patient.
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Only Owner can delete patients
        return $user->hasRole('Owner');
    }
}
