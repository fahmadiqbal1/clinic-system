<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'department' => $this->faker->randomElement(['pharmacy', 'laboratory', 'radiology']),
            'name' => $this->faker->word(),
            'chemical_formula' => $this->faker->optional()->bothify('??#??'),
            'sku' => $this->faker->unique()->bothify('SKU-####-###'),
            'unit' => $this->faker->randomElement(['tablet', 'vial', 'ml', 'roll', 'kit']),
            'minimum_stock_level' => 10,
            'purchase_price' => $this->faker->randomFloat(2, 5, 100),
            'selling_price' => $this->faker->randomFloat(2, 10, 200),
            'requires_prescription' => $this->faker->boolean(30),
            'is_active' => true,
        ];
    }

    public function pharmacy(): self
    {
        return $this->state([
            'department' => 'pharmacy',
        ]);
    }

    public function laboratory(): self
    {
        return $this->state([
            'department' => 'laboratory',
        ]);
    }

    public function radiology(): self
    {
        return $this->state([
            'department' => 'radiology',
        ]);
    }

    public function requiresPrescription(): self
    {
        return $this->state([
            'requires_prescription' => true,
        ]);
    }

    public function inactive(): self
    {
        return $this->state([
            'is_active' => false,
        ]);
    }
}
