<?php

namespace App\Policies;

use App\Models\AiAnalysis;
use App\Models\User;

class AiAnalysisPolicy
{
    /**
     * Determine if user can view the AI analysis.
     */
    public function view(User $user, AiAnalysis $analysis): bool
    {
        // Owner can view all analyses
        if ($user->hasRole('Owner')) {
            return true;
        }

        // Requester can view their own analyses
        if ($analysis->requested_by === $user->id) {
            return true;
        }

        // Doctor can view analyses for their patients
        if ($user->hasRole('Doctor')) {
            $patient = $analysis->patient;
            return $patient && $patient->doctor_id === $user->id;
        }

        // Lab staff can view lab analyses
        if ($user->hasRole('Laboratory') && $analysis->context_type === 'lab') {
            return true;
        }

        // Radiology staff can view radiology analyses
        if ($user->hasRole('Radiology') && $analysis->context_type === 'radiology') {
            return true;
        }

        return false;
    }

    /**
     * Determine if user can create an AI analysis.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['Doctor', 'Laboratory', 'Radiology']);
    }

    /**
     * Determine if user can request consultation analysis.
     */
    public function analyseConsultation(User $user, \App\Models\Patient $patient): bool
    {
        // Only the assigned doctor can analyse their patient's consultation
        return $user->hasRole('Doctor') && $patient->doctor_id === $user->id;
    }

    /**
     * Determine if user can request lab analysis.
     */
    public function analyseLab(User $user, \App\Models\Invoice $invoice): bool
    {
        return $user->hasRole('Laboratory') && $invoice->department === 'lab';
    }

    /**
     * Determine if user can request radiology analysis.
     */
    public function analyseRadiology(User $user, \App\Models\Invoice $invoice): bool
    {
        return $user->hasRole('Radiology') && $invoice->department === 'radiology';
    }

    /**
     * Determine if user can delete an AI analysis.
     */
    public function delete(User $user, AiAnalysis $analysis): bool
    {
        // Only Owner can delete analyses (for compliance, consider soft delete)
        return $user->hasRole('Owner');
    }
}
