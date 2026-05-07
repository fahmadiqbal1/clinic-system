<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorCredential extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'file_path',
        'original_filename',
        'uploaded_at',
        'verified_at',
        'verified_by',
        'verification_notes',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }
}
