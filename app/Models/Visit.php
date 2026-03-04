<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'triage_nurse_id',
        'visit_date',
        'status',
        'consultation_fee_invoice_id',
        'consultation_notes',
        'registered_at',
        'triage_started_at',
        'doctor_started_at',
        'completed_at',
    ];

    protected $casts = [
        'registered_at' => 'datetime',
        'triage_started_at' => 'datetime',
        'doctor_started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function consultationFeeInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'consultation_fee_invoice_id');
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
}
