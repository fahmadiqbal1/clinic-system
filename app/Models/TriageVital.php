<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\SafeEncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TriageVital extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'triage_vitals';

    protected $fillable = [
        'patient_id',
        'visit_id',
        'blood_pressure',
        'temperature',
        'pulse_rate',
        'respiratory_rate',
        'weight',
        'height',
        'oxygen_saturation',
        'chief_complaint',
        'priority',
        'notes',
        'recorded_by',
    ];

    /**
     * PHI fields encrypted at rest for HIPAA compliance.
     * Chief complaint and notes contain sensitive medical information.
     */
    protected $casts = [
        'temperature' => 'decimal:1',
        'pulse_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'oxygen_saturation' => 'decimal:1',
        // PHI encryption
        'chief_complaint' => SafeEncryptedString::class,
        'notes' => SafeEncryptedString::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
