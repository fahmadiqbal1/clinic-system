<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiActionRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_token',
        'requested_by_source',
        'target_type',
        'target_id',
        'proposed_action',
        'proposed_payload',
        'status',
        'approver_user_id',
        'decided_at',
    ];

    protected $casts = [
        'proposed_payload' => 'json',
        'created_at'       => 'datetime',
        'decided_at'       => 'datetime',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
