<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\SafeEncryptedString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiAnalysis extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'invoice_id',
        'requested_by',
        'context_type',
        'prompt_summary',
        'ai_response',
        'status',
    ];

    /**
     * PHI fields encrypted at rest for HIPAA compliance.
     * AI response may contain sensitive medical analysis.
     */
    protected $casts = [
        'prompt_summary' => SafeEncryptedString::class,
        'ai_response' => SafeEncryptedString::class,
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
