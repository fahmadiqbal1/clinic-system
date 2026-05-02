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
        'manufacturer',
        'manufacturer_tag',
        'chemical_formula',
        'sku',
        'barcode',
        'unit',
        'pack_size',
        'minimum_stock_level',
        'purchase_price',
        'selling_price',
        'weighted_avg_cost',
        'requires_prescription',
        'is_active',
        'vendor_id',
        'last_stocked_at',
    ];

    protected $casts = [
        'minimum_stock_level' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'weighted_avg_cost' => 'decimal:2',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
        'last_stocked_at' => 'datetime',
    ];

    /**
     * Find an item by its identity triple (name + manufacturer + department).
     * Returns null if not found — no side effects.
     */
    public static function findByIdentity(string $name, string $manufacturer, string $department): ?self
    {
        return static::where('name', $name)
            ->where('manufacturer', $manufacturer)
            ->where('department', $department)
            ->first();
    }

    /**
     * Derive a short manufacturer tag (≤8 chars, uppercase) from the full manufacturer name.
     */
    public static function deriveManufacturerTag(string $manufacturer): string
    {
        // Use known short forms for common pharma names; otherwise take first word up to 8 chars
        $known = [
            'muller & phipps' => 'M&P',
            'muller and phipps' => 'M&P',
            'glaxosmithkline' => 'GSK',
            'gsk' => 'GSK',
            'f. hoffmann-la roche' => 'Roche',
            'roche' => 'Roche',
            'pfizer' => 'Pfizer',
            'abbott' => 'Abbott',
            'novartis' => 'Novartis',
            'sanofi' => 'Sanofi',
            'bayer' => 'Bayer',
            'astrazeneca' => 'AZ',
            'johnson & johnson' => 'J&J',
        ];

        $lower = strtolower(trim($manufacturer));
        foreach ($known as $key => $tag) {
            if (str_contains($lower, $key)) {
                return $tag;
            }
        }

        // Fallback: first word, uppercased, truncated to 8 chars
        $words = preg_split('/\s+/', trim($manufacturer));
        return strtoupper(substr($words[0] ?? $manufacturer, 0, 8));
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function procurementRequestItems(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }
}
