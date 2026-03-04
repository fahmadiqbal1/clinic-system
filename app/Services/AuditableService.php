<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditableService
{
    /**
     * Log invoice status transition.
     */
    public static function logInvoiceStatusChange($invoice, $oldStatus, $newStatus): void
    {
        AuditLog::log(
            action: "invoice_status_changed: {$oldStatus} → {$newStatus}",
            auditableType: 'App\Models\Invoice',
            auditableId: $invoice->id,
            beforeState: ['status' => $oldStatus],
            afterState: ['status' => $newStatus],
        );
    }

    /**
     * Log invoice payment.
     */
    public static function logInvoicePayment($invoice, $paymentMethod): void
    {
        AuditLog::log(
            action: 'invoice_marked_paid',
            auditableType: 'App\Models\Invoice',
            auditableId: $invoice->id,
            beforeState: ['status' => 'completed'],
            afterState: ['status' => 'paid', 'payment_method' => $paymentMethod],
        );
    }

    /**
     * Log invoice cancellation.
     */
    public static function logInvoiceCancellation($invoice, $reason = null): void
    {
        AuditLog::log(
            action: 'invoice_cancelled',
            auditableType: 'App\Models\Invoice',
            auditableId: $invoice->id,
            beforeState: ['status' => $invoice->status],
            afterState: ['status' => 'cancelled', 'reason' => $reason],
        );
    }

    /**
     * Log report save (Lab/Radiology).
     */
    public static function logReportSave($invoice): void
    {
        AuditLog::log(
            action: 'report_saved',
            auditableType: 'App\Models\Invoice',
            auditableId: $invoice->id,
            afterState: ['has_report' => true, 'report_length' => strlen($invoice->report_text ?? '')],
        );
    }

    /**
     * Log consultation notes save.
     */
    public static function logConsultationNotesSave($patient): void
    {
        AuditLog::log(
            action: 'consultation_notes_saved',
            auditableType: 'App\Models\Patient',
            auditableId: $patient->id,
            afterState: ['consultation_notes_length' => strlen($patient->consultation_notes ?? '')],
        );
    }

    /**
     * Log user role change.
     */
    public static function logUserRoleChange($user, $oldRole, $newRole): void
    {
        AuditLog::log(
            action: 'user_role_changed',
            auditableType: 'App\Models\User',
            auditableId: $user->id,
            beforeState: ['role' => $oldRole],
            afterState: ['role' => $newRole],
        );
    }

    /**
     * Log user activation/deactivation.
     */
    public static function logUserActivityChange($user, $isActive): void
    {
        AuditLog::log(
            action: $isActive ? 'user_activated' : 'user_deactivated',
            auditableType: 'App\Models\User',
            auditableId: $user->id,
            afterState: ['is_active' => $isActive],
        );
    }

    /**
     * Log stock deduction.
     */
    public static function logStockDeduction($movement): void
    {
        AuditLog::log(
            action: 'stock_deducted',
            auditableType: 'App\Models\StockMovement',
            auditableId: $movement->id,
            afterState: [
                'inventory_item_id' => $movement->inventory_item_id,
                'quantity' => $movement->quantity,
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
            ],
        );
    }
}
