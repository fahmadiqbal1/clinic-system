<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'procurement_request_id',
        'inventory_item_id',
        'quantity_requested',
        'quoted_unit_price',
        'quantity_received',
        'unit_price',
    ];

    protected $casts = [
        'quantity_requested' => 'integer',
        'quantity_received' => 'integer',
        'unit_price' => 'decimal:2',
        'quoted_unit_price' => 'decimal:2',
    ];

    public function procurementRequest(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
