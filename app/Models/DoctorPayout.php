<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoctorPayout extends Model
{
    use HasFactory, SoftDeletes;

    // Payout types
    const TYPE_COMMISSION = 'commission';  // Daily — doctors only
    const TYPE_MONTHLY    = 'monthly';     // Monthly — salary + commission for non-doctor staff

    // Approval statuses (null means no approval needed)
    const APPROVAL_PENDING  = 'pending';
    const APPROVAL_APPROVED = 'approved';
    const APPROVAL_REJECTED = 'rejected';

    protected $fillable = [
        'doctor_id',
        'period_start',
        'period_end',
        'total_amount',
        'paid_amount',
        'salary_amount',
        'status',
        'payout_type',
        'approval_status',
        'approved_by',
        'approved_at',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'correction_of_id',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'salary_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    /**
     * Doctor who receives the payout
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * User who created the payout (typically Reception)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User who confirmed the payout (Doctor)
     */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * User (Owner) who approved the payout
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Original payout if this is a correction
     */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(DoctorPayout::class, 'correction_of_id');
    }

    /**
     * Correction payouts referencing this one
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(DoctorPayout::class, 'correction_of_id');
    }

    /**
     * Revenue ledger entries attached to this payout
     */
    public function revenueLedgers(): HasMany
    {
        return $this->hasMany(RevenueLedger::class, 'payout_id');
    }

    /**
     * Get outstanding balance (total - paid)
     */
    public function getOutstandingBalanceAttribute(): float
    {
        return (float) ($this->total_amount - $this->paid_amount);
    }

    /**
     * Check if payout is locked (confirmed or is a correction)
     */
    public function isLocked(): bool
    {
        return $this->status === 'confirmed' || $this->correction_of_id !== null;
    }

    /**
     * Whether this payout requires owner approval (non-doctor staff monthly payouts).
     */
    public function needsApproval(): bool
    {
        return $this->payout_type === self::TYPE_MONTHLY;
    }

    /**
     * Whether this payout has been approved by the owner.
     */
    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * Whether this payout has been rejected by the owner.
     */
    public function isRejected(): bool
    {
        return $this->approval_status === self::APPROVAL_REJECTED;
    }

    /**
     * Whether the staff member can confirm receipt.
     * Doctors: can always confirm pending payouts.
     * Other staff: can only confirm after owner approval.
     */
    public function canBeConfirmed(): bool
    {
        if ($this->status === 'confirmed') {
            return false;
        }

        // Doctor daily payouts — no approval gate
        if ($this->payout_type === self::TYPE_COMMISSION) {
            return $this->status === 'pending';
        }

        // Monthly payouts — must be approved first
        return $this->isApproved() && $this->status === 'pending';
    }

    /**
     * Whether the payout is a monthly type (salary + commission).
     */
    public function isMonthly(): bool
    {
        return $this->payout_type === self::TYPE_MONTHLY;
    }
}
