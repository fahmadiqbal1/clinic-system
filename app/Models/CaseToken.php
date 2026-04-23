<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseToken extends Model
{
    public $incrementing = false;
    public $timestamps   = false;

    protected $primaryKey = 'token';
    protected $keyType    = 'string';

    protected $fillable = ['token', 'patient_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
