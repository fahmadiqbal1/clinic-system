<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\SafeEncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * PHI fields encrypted at rest for HIPAA compliance.
     */
    protected $casts = [
        'registered_at' => 'datetime',
        'triage_started_at' => 'datetime',
        'doctor_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'consultation_notes' => SafeEncryptedString::class,
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
