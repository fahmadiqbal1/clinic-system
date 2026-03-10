<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'cnic',
        'email',
        'gender',
        'date_of_birth',
        'doctor_id',
        'user_id',
        'status',
        'registration_type',
        'referred_by_user_id',
        'consultation_notes',
        'registered_at',
        'triage_started_at',
        'doctor_started_at',
        'completed_at',
    ];

    /**
     * PHI (Protected Health Information) fields are encrypted at rest.
     * Phone and email are encrypted for HIPAA compliance.
     * Consultation notes contain sensitive medical information.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'registered_at' => 'datetime',
        'triage_started_at' => 'datetime',
        'doctor_started_at' => 'datetime',
        'completed_at' => 'datetime',
        // PHI encryption
        'phone' => 'encrypted',
        'email' => 'encrypted',
        'consultation_notes' => 'encrypted',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
