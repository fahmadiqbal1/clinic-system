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

    /**
     * Generic log for model creation.
     */
    public static function logCreate($model, string $modelName, array $afterState = []): void
    {
        AuditLog::log(
            action: strtolower($modelName) . '_created',
            auditableType: get_class($model),
            auditableId: $model->id,
            afterState: $afterState ?: $model->toArray(),
        );
    }

    /**
     * Generic log for model update.
     */
    public static function logUpdate($model, string $modelName, array $changes = []): void
    {
        AuditLog::log(
            action: strtolower($modelName) . '_updated',
            auditableType: get_class($model),
            auditableId: $model->id,
            beforeState: $changes['before'] ?? [],
            afterState: $changes['after'] ?? $model->getChanges(),
        );
    }

    /**
     * Generic log for model deletion.
     */
    public static function logDelete($model, string $modelName): void
    {
        AuditLog::log(
            action: strtolower($modelName) . '_deleted',
            auditableType: get_class($model),
            auditableId: $model->id,
            beforeState: $model->toArray(),
        );
    }

    /**
     * Generic log for status/state transitions.
     */
    public static function logTransition($model, string $modelName, string $field, $oldValue, $newValue): void
    {
        AuditLog::log(
            action: strtolower($modelName) . '_' . $field . '_changed',
            auditableType: get_class($model),
            auditableId: $model->id,
            beforeState: [$field => $oldValue],
            afterState: [$field => $newValue],
        );
    }
}
