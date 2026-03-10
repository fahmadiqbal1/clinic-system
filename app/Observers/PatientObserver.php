<?php

namespace App\Observers;

use App\Models\Patient;
use App\Services\AuditableService;

/**
 * Observer for Patient model to centralize side effects.
 */
class PatientObserver
{
    /**
     * Handle the Patient "created" event.
     */
    public function created(Patient $patient): void
    {
        AuditableService::logCreate(
            $patient,
            'Patient',
            ['name' => $patient->full_name, 'status' => $patient->status]
        );
    }

    /**
     * Handle the Patient "updated" event.
     */
    public function updated(Patient $patient): void
    {
        $changes = $patient->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        // Log status transitions specially
        if ($patient->wasChanged('status')) {
            $from = $patient->getOriginal('status');
            $to = $patient->status;
            
            AuditableService::logTransition(
                $patient,
                'Patient',
                'status',
                $from,
                $to
            );
        } else {
            AuditableService::logUpdate(
                $patient,
                'Patient',
                [
                    'before' => array_intersect_key($patient->getOriginal(), $changes),
                    'after' => $changes,
                ]
            );
        }
    }

    /**
     * Handle the Patient "deleted" event.
     */
    public function deleted(Patient $patient): void
    {
        AuditableService::logDelete($patient, 'Patient');
    }
}
