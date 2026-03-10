<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AuditableService;
use Illuminate\Support\Facades\Auth;

/**
 * Observer for Invoice model to centralize side effects.
 * 
 * Handles audit logging, notifications, and state-change side effects
 * that were previously scattered across controllers.
 */
class InvoiceObserver
{
    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        AuditableService::logCreate(
            $invoice,
            'Invoice',
            [
                'department' => $invoice->department,
                'patient_id' => $invoice->patient_id,
                'total' => $invoice->total,
            ]
        );
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        $changes = $invoice->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        // Log status transitions specially
        if ($invoice->wasChanged('status')) {
            $from = $invoice->getOriginal('status');
            $to = $invoice->status;
            
            AuditableService::logTransition(
                $invoice,
                'Invoice',
                'status',
                $from,
                $to
            );
        } else {
            AuditableService::logUpdate(
                $invoice,
                'Invoice',
                [
                    'before' => array_intersect_key($invoice->getOriginal(), $changes),
                    'after' => $changes,
                ]
            );
        }
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        AuditableService::logDelete($invoice, 'Invoice');
    }
}
