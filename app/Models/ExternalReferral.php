<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalReferral extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_SENT      = 'sent';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'patient_id', 'invoice_id', 'external_lab_id', 'referred_by_user_id',
        'approved_by_id', 'test_name', 'department', 'clinical_notes', 'reason',
        'patient_price', 'commission_pct', 'status', 'owner_notes', 'approved_at',
    ];

    protected $casts = [
        'patient_price'  => 'decimal:2',
        'commission_pct' => 'decimal:2',
        'approved_at'    => 'datetime',
    ];

    public function patient(): BelongsTo    { return $this->belongsTo(Patient::class); }
    public function invoice(): BelongsTo    { return $this->belongsTo(Invoice::class); }
    public function externalLab(): BelongsTo { return $this->belongsTo(ExternalLab::class); }
    public function referredBy(): BelongsTo { return $this->belongsTo(User::class, 'referred_by_user_id'); }
    public function approvedBy(): BelongsTo { return $this->belongsTo(User::class, 'approved_by_id'); }
}
