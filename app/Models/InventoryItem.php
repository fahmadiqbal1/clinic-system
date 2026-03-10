<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department',
        'name',
        'chemical_formula',
        'sku',
        'unit',
        'minimum_stock_level',
        'purchase_price',
        'selling_price',
        'weighted_avg_cost',
        'requires_prescription',
        'is_active',
    ];

    protected $casts = [
        'minimum_stock_level' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'weighted_avg_cost' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function procurementRequestItems(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }
}
