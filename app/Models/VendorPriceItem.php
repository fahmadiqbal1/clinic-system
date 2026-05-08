<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPriceItem extends Model
{
    protected $fillable = [
        'vendor_price_list_id',
        'item_name',
        'sku_detected',
        'pack_size',
        'unit_detected',
        'detected_price',
        'current_price',
        'inventory_item_id',
        'external_lab_id',
        'test_name_normalized',
        'confidence',
        'needs_review',
        'applied',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'detected_price' => 'decimal:2',
        'current_price'  => 'decimal:2',
        'confidence'     => 'float',
        'needs_review'   => 'boolean',
        'applied'        => 'boolean',
        'reviewed_at'    => 'datetime',
    ];

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(VendorPriceList::class, 'vendor_price_list_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
