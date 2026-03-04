<?php

namespace App\Services\Queries;

use App\Models\InventoryItem;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

class InventoryHealthQueryService
{
    /**
     * Get all inventory items with current stock levels (ledger-derived).
     * 
     * Uses single aggregated query with JOIN + GROUP BY to avoid N+1.
     * Stock is calculated as SUM(stock_movements.quantity) per item.
     * 
     * IMPORTANT: Requires index on stock_movements(inventory_item_id)
     * for optimal performance. See database/migrations/ for details.
     */
    public function getAllItemsWithStock(string $department = null): Collection
    {
        $items = $this->buildAggregatedQuery($department)->get();

        return $items->map(fn ($item) => $this->formatItemWithStock($item));
    }

    /**
     * Get all items below minimum stock level.
     * 
     * Filters aggregated result set. Does NOT execute separate query.
     * Reuses same aggregation logic as getAllItemsWithStock().
     */
    public function getItemsBelowMinimum(string $department = null): Collection
    {
        return $this->getAllItemsWithStock($department)
            ->filter(fn ($item) => $item['below_minimum'])
            ->values();
    }

    /**
     * Count items below minimum stock by department.
     * 
     * Provides department-wise shortage statistics.
     * Example: ['pharmacy' => 5, 'laboratory' => 2, 'radiology' => 0]
     */
    public function countBelowMinimumByDepartment(): array
    {
        $items = InventoryItem::where('is_active', true)
            ->leftJoinSub(
                $this->getStockMovementsSummary(),
                'stock_summary',
                function ($join) {
                    $join->on('inventory_items.id', '=', 'stock_summary.inventory_item_id');
                }
            )
            ->selectRaw('inventory_items.department')
            ->selectRaw('COUNT(CASE WHEN COALESCE(stock_summary.total_stock, 0) < inventory_items.minimum_stock_level THEN 1 END) as below_minimum_count')
            ->groupBy('inventory_items.department')
            ->get();

        $result = [
            'pharmacy' => 0,
            'laboratory' => 0,
            'radiology' => 0,
        ];

        foreach ($items as $row) {
            if (isset($result[$row->department])) {
                $result[$row->department] = (int) $row->below_minimum_count;
            }
        }

        return $result;
    }

    /**
     * Get items grouped by department (with all stock data).
     */
    public function getItemsByDepartment(): array
    {
        $items = $this->getAllItemsWithStock();

        return $items->groupBy('department')->toArray();
    }

    /**
     * Build aggregated query for inventory items with stock calculation.
     * 
     * Single source of truth for stock ledger aggregation.
     * Joins with stock_movements and sums quantity per item.
     * 
     * @param string|null $department Optional department filter
     * @return EloquentBuilder
     */
    private function buildAggregatedQuery(string $department = null): EloquentBuilder
    {
        $query = InventoryItem::where('is_active', true)
            ->leftJoinSub(
                $this->getStockMovementsSummary(),
                'stock_summary',
                function ($join) {
                    $join->on('inventory_items.id', '=', 'stock_summary.inventory_item_id');
                }
            )
            ->select(
                'inventory_items.id',
                'inventory_items.department',
                'inventory_items.name',
                'inventory_items.chemical_formula',
                'inventory_items.sku',
                'inventory_items.unit',
                'inventory_items.minimum_stock_level',
                'inventory_items.purchase_price',
                'inventory_items.selling_price',
                'inventory_items.requires_prescription',
                'stock_summary.total_stock'
            );

        if ($department) {
            $query->where('inventory_items.department', $department);
        }

        return $query;
    }

    /**
     * Stock movements subquery.
     * 
     * Aggregates all stock movements per inventory item in one query.
     * Returns (inventory_item_id, total_stock) pairs.
     * 
     * CRITICAL: This is the single source of truth for stock calculation.
     * All stock ledger logic flows through here.
     * 
     * @return Builder
     */
    private function getStockMovementsSummary(): Builder
    {
        return DB::table('stock_movements')
            ->selectRaw('inventory_item_id, COALESCE(SUM(quantity), 0) as total_stock')
            ->groupBy('inventory_item_id');
    }

    /**
     * Format item row with computed stock and below-minimum flags.
     * 
     * @param object $item Raw database row
     * @return array
     */
    private function formatItemWithStock(object $item): array
    {
        $currentStock = (int) ($item->total_stock ?? 0);
        $minimumLevel = (int) $item->minimum_stock_level;
        $belowMinimum = $currentStock < $minimumLevel;
        $shortage = $belowMinimum ? ($minimumLevel - $currentStock) : 0;

        return [
            'id' => $item->id,
            'department' => $item->department,
            'name' => $item->name,
            'chemical_formula' => $item->chemical_formula,
            'sku' => $item->sku,
            'unit' => $item->unit,
            'current_stock' => $currentStock,
            'minimum_stock_level' => $minimumLevel,
            'below_minimum' => $belowMinimum,
            'shortage' => $shortage,
            'purchase_price' => $item->purchase_price,
            'selling_price' => $item->selling_price,
            'requires_prescription' => $item->requires_prescription,
        ];
    }
}
