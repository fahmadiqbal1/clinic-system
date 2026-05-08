<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffShift extends Model
{
    protected $fillable = [
        'user_id',
        'clocked_in_at',
        'clocked_out_at',
        'clocked_in_ip',
        'notes',
    ];

    protected $casts = [
        'clocked_in_at'  => 'datetime',
        'clocked_out_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->clocked_out_at === null;
    }

    public function durationMinutes(): ?int
    {
        if ($this->clocked_out_at === null) {
            return null;
        }

        return (int) $this->clocked_in_at->diffInMinutes($this->clocked_out_at);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('clocked_in_at', today());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('clocked_out_at');
    }
}
