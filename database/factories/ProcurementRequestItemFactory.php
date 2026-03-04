<?php

namespace Database\Factories;

use App\Models\ProcurementRequestItem;
use App\Models\ProcurementRequest;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcurementRequestItemFactory extends Factory
{
    protected $model = ProcurementRequestItem::class;

    public function definition(): array
    {
        return [
            'procurement_request_id' => ProcurementRequest::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity_requested' => $this->faker->numberBetween(1, 100),
            'quantity_received' => null,
            'unit_price' => null,
        ];
    }

    public function withInventoryItem(InventoryItem $item): self
    {
        return $this->state([
            'inventory_item_id' => $item->id,
        ]);
    }

    public function withProcurement(ProcurementRequest $request): self
    {
        return $this->state([
            'procurement_request_id' => $request->id,
        ]);
    }

    public function received(): self
    {
        return $this->state([
            'quantity_received' => $this->faker->numberBetween(1, 100),
            'unit_price' => $this->faker->randomFloat(2, 5, 100),
        ]);
    }

    public function service(): self
    {
        return $this->state([
            'inventory_item_id' => null,
            'unit_price' => $this->faker->randomFloat(2, 10, 500),
        ]);
    }
}
