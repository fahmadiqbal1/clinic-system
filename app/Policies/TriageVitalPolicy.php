<?php

namespace App\Policies;

use App\Models\TriageVital;
use App\Models\User;

class TriageVitalPolicy
{
    /**
     * Determine if user can view the triage vital.
     */
    public function view(User $user, TriageVital $vital): bool
    {
        // Owner can view all vitals
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Triage staff can view all vitals
        if ($user->hasRole('Triage')) {
            return true;
        }

        // Receptionist can view vitals
        if ($user->hasRole('Receptionist')) {
            return true;
        }

        // Doctor can view vitals for their patients
        if ($user->hasRole('Doctor')) {
            $patient = $vital->patient;
            return $patient && $patient->doctor_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if user can create triage vitals.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Owner', 'Triage']);
    }

    /**
     * Determine if user can update the triage vital.
     */
    public function update(User $user, TriageVital $vital): bool
    {
        // Owner can update all
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Triage staff can update vitals they recorded
        if ($user->hasRole('Triage') && $vital->recorded_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can delete the triage vital.
     */
    public function delete(User $user, TriageVital $vital): bool
    {
        // Only Owner can delete vitals (medical records should be preserved)
        return $user->hasRole('Owner');
    }
}
