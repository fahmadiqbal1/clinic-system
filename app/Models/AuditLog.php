<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $table = 'audit_logs';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'before_state',
        'after_state',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'before_state' => 'json',
        'after_state' => 'json',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a human-readable description of the audit action.
     */
    public function getHumanReadableAttribute(): string
    {
        $modelName = class_basename($this->auditable_type ?? 'Record');
        $action = str_replace('_', ' ', $this->action ?? 'updated');

        $descriptions = [
            'patient_registered' => "registered a new patient",
            'patient_updated' => "updated patient record",
            'invoice_created' => "created a new invoice",
            'invoice_paid' => "marked an invoice as paid",
            'invoice_updated' => "updated an invoice",
            'invoice_cancelled' => "cancelled an invoice",
            'payout_created' => "generated a staff payout",
            'payout_confirmed' => "confirmed a payout",
            'prescription_created' => "created a prescription",
            'lab_results_uploaded' => "uploaded lab results",
            'radiology_results_uploaded' => "uploaded radiology results",
            'vitals_recorded' => "recorded patient vitals",
            'expense_created' => "recorded an expense",
            'expense_updated' => "updated an expense",
            'user_created' => "created a new user",
            'user_updated' => "updated a user",
            'discount_requested' => "requested a discount",
            'discount_approved' => "approved a discount",
            'discount_rejected' => "rejected a discount",
            'procurement_created' => "created a procurement request",
            'procurement_approved' => "approved a procurement request",
            'procurement_received' => "received procurement items",
            'stock_adjusted' => "adjusted stock levels",
            'contract_created' => "created a staff contract",
            'contract_signed' => "signed a staff contract",
            'login' => "logged in",
        ];

        return $descriptions[$this->action] ?? ucfirst($action) . " {$modelName}";
    }

    /**
     * Get the icon for this audit action.
     */
    public function getIconAttribute(): string
    {
        $icons = [
            'patient_registered' => 'bi-person-plus',
            'patient_updated' => 'bi-person-gear',
            'invoice_created' => 'bi-receipt',
            'invoice_paid' => 'bi-cash-coin',
            'invoice_cancelled' => 'bi-x-circle',
            'payout_created' => 'bi-wallet2',
            'payout_confirmed' => 'bi-check-circle',
            'prescription_created' => 'bi-capsule',
            'lab_results_uploaded' => 'bi-droplet',
            'radiology_results_uploaded' => 'bi-radioactive',
            'vitals_recorded' => 'bi-heart-pulse',
            'expense_created' => 'bi-cash-stack',
            'user_created' => 'bi-person-badge',
            'discount_requested' => 'bi-tag',
            'discount_approved' => 'bi-check2-circle',
            'procurement_created' => 'bi-cart3',
            'stock_adjusted' => 'bi-box-seam',
            'login' => 'bi-box-arrow-in-right',
        ];
        return $icons[$this->action] ?? 'bi-activity';
    }

    /**
     * Get the color for this audit action.
     */
    public function getColorAttribute(): string
    {
        $colors = [
            'patient_registered' => 'success',
            'invoice_created' => 'primary',
            'invoice_paid' => 'success',
            'invoice_cancelled' => 'danger',
            'payout_created' => 'warning',
            'expense_created' => 'danger',
            'discount_approved' => 'success',
            'discount_rejected' => 'danger',
            'login' => 'info',
        ];
        return $colors[$this->action] ?? 'primary';
    }

    /**
     * Log an action to audit trail.
     */
    public static function log(
        string $action,
        string $auditableType,
        int $auditableId,
        ?array $beforeState = null,
        ?array $afterState = null,
        ?int $userId = null,
        ?string $ipAddress = null
    ): self {
        $finalUserId = $userId ?? \Illuminate\Support\Facades\Auth::id();
        return AuditLog::create([
            'user_id' => $finalUserId,
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'ip_address' => $ipAddress ?? request()->ip(),
            'created_at' => now(),
        ]);
    }
}
