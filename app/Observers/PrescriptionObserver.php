<?php

namespace App\Observers;

use App\Models\Prescription;
use App\Services\AuditableService;

/**
 * Observer for Prescription model to centralize side effects.
 */
class PrescriptionObserver
{
    /**
     * Handle the Prescription "created" event.
     */
    public function created(Prescription $prescription): void
    {
        AuditableService::logCreate(
            $prescription,
            'Prescription',
            ['patient_id' => $prescription->patient_id, 'status' => $prescription->status]
        );
    }

    /**
     * Handle the Prescription "updated" event.
     */
    public function updated(Prescription $prescription): void
    {
        $changes = $prescription->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        if ($prescription->wasChanged('status')) {
            $from = $prescription->getOriginal('status');
            $to = $prescription->status;
            
            AuditableService::logTransition(
                $prescription,
                'Prescription',
                'status',
                $from,
                $to
            );
        } else {
            AuditableService::logUpdate(
                $prescription,
                'Prescription',
                [
                    'before' => array_intersect_key($prescription->getOriginal(), $changes),
                    'after' => $changes,
                ]
            );
        }
    }

    /**
     * Handle the Prescription "deleted" event.
     */
    public function deleted(Prescription $prescription): void
    {
        AuditableService::logDelete($prescription, 'Prescription');
    }
}
