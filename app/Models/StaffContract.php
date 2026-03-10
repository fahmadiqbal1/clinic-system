<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffContract extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staff_contracts';

    protected $fillable = [
        'user_id',
        'version',
        'contract_html_snapshot',
        'minimum_term_months',
        'effective_from',
        'status',
        'signed_at',
        'signed_ip',
        'signed_user_agent',
        'created_by',
        'resignation_notice_submitted_at',
        'early_exit_flag',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'signed_at' => 'datetime',
        'resignation_notice_submitted_at' => 'datetime',
        'early_exit_flag' => 'boolean',
        'minimum_term_months' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Staff member assigned to this contract.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * User who created this contract (typically Owner).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the minimum end date for the contract term.
     */
    public function getMinimumTermEndAttribute(): \DateTime
    {
        return $this->effective_from->copy()->addMonths($this->minimum_term_months);
    }

    /**
     * Check if user is within minimum term.
     */
    public function isWithinMinimumTerm(): bool
    {
        return now()->toDateString() < $this->getMinimumTermEndAttribute()->format('Y-m-d');
    }

    /**
     * Check if contract is signed.
     */
    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /**
     * Check if contract is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope: contracts for a specific user.
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId)->orderBy('version', 'desc');
    }
}
