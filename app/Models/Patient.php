<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'gender',
        'date_of_birth',
        'doctor_id',
        'status',
        'consultation_notes',
        'registered_at',
        'triage_started_at',
        'doctor_started_at',
        'completed_at',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'registered_at' => 'datetime',
        'triage_started_at' => 'datetime',
        'doctor_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function triageVitals(): HasMany
    {
        return $this->hasMany(TriageVital::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }

    /**
     * Full name accessor: $patient->full_name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
