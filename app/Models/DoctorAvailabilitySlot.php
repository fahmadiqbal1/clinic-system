<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorAvailabilitySlot extends Model
{
    protected $fillable = [
        'doctor_id',
        'room_id',
        'date',
        'day_of_week',
        'start_time',
        'end_time',
        'slot_duration_mins',
        'is_recurring',
        'is_active',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_recurring' => 'boolean',
        'is_active'    => 'boolean',
    ];

    /**
     * The doctor who owns this availability slot.
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * The room assigned to this slot (optional).
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ClinicRoom::class);
    }

    /**
     * Scope: only active slots.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: slots for a specific doctor.
     */
    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Get the number of appointment slots within this availability window.
     * Calculated as the number of slot_duration_mins intervals between start_time and end_time.
     */
    public function getSlotCountAttribute(): int
    {
        $start = Carbon::createFromTimeString($this->start_time);
        $end   = Carbon::createFromTimeString($this->end_time);

        $diffMins = $start->diffInMinutes($end);

        if ($this->slot_duration_mins <= 0) {
            return 0;
        }

        return (int) floor($diffMins / $this->slot_duration_mins);
    }
}
