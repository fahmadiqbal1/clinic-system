<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZakatTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_start',
        'period_end',
        'total_revenue',
        'total_cogs',
        'total_commissions',
        'total_expenses',
        'owner_net_profit',
        'zakat_amount',
        'zakat_percentage',
        'calculated_by',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'total_revenue' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'total_commissions' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'owner_net_profit' => 'decimal:2',
        'zakat_amount' => 'decimal:2',
        'zakat_percentage' => 'decimal:2',
    ];

    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}
