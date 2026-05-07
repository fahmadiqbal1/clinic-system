<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalLabTestPrice extends Model
{
    protected $fillable = [
        'external_lab_id',
        'test_name',
        'test_code',
        'price',
        'currency',
        'commission_pct',
        'effective_from',
        'effective_until',
        'source_price_list_id',
        'is_active',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'commission_pct'  => 'decimal:2',
        'effective_from'  => 'date',
        'effective_until' => 'date',
        'is_active'       => 'boolean',
    ];

    public function lab(): BelongsTo
    {
        return $this->belongsTo(ExternalLab::class, 'external_lab_id');
    }

    public function sourcePriceList(): BelongsTo
    {
        return $this->belongsTo(VendorPriceList::class, 'source_price_list_id');
    }
}
