<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Appointment model for scheduling system.
 */
class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Appointment type constants.
     */
    public const TYPE_FIRST_VISIT = 'first_visit';
    public const TYPE_FOLLOW_UP = 'follow_up';
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_EMERGENCY = 'emergency';

    /**
     * Appointment status constants.
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'booked_by',
        'scheduled_at',
        'ended_at',
        'type',
        'status',
        'reason',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'reminder_sent',
        'reminder_sent_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reminder_sent' => 'boolean',
        'reminder_sent_at' => 'datetime',
    ];

    /**
     * Get the patient for the appointment.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the doctor for the appointment.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Get the user who booked the appointment.
     */
    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    /**
     * Get the user who cancelled the appointment.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Check if appointment is upcoming.
     */
    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture() && 
               in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    /**
     * Check if appointment can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED]);
    }

    /**
     * Scope for upcoming appointments.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
                     ->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_CONFIRMED])
                     ->orderBy('scheduled_at');
    }

    /**
     * Scope for today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('scheduled_at', today());
    }

    /**
     * Scope for a specific doctor.
     */
    public function scopeForDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_FIRST_VISIT => 'First Visit',
            self::TYPE_FOLLOW_UP => 'Follow Up',
            self::TYPE_CONSULTATION => 'Consultation',
            self::TYPE_PROCEDURE => 'Procedure',
            self::TYPE_EMERGENCY => 'Emergency',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_NO_SHOW => 'No Show',
            default => ucfirst($this->status),
        };
    }
}
