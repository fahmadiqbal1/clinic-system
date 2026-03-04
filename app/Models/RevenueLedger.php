<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RevenueLedger extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'role_type',
        'entry_type',
        'category',
        'percentage',
        'amount',
        'net_cost',
        'gross_profit',
        'commission_amount',
        'is_prescribed',
        'payout_status',
        'paid_at',
        'paid_by',
        'payout_id',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'net_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'is_prescribed' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(DoctorPayout::class, 'payout_id');
    }
}
