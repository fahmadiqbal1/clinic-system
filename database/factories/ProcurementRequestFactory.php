<?php

namespace Database\Factories;

use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcurementRequestFactory extends Factory
{
    protected $model = ProcurementRequest::class;

    public function definition(): array
    {
        return [
            'department' => $this->faker->randomElement(['pharmacy', 'laboratory', 'radiology']),
            'type' => 'inventory',
            'requested_by' => User::factory(),
            'approved_by' => null,
            'status' => 'pending',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function inventory(): self
    {
        return $this->state([
            'type' => 'inventory',
        ]);
    }

    public function service(): self
    {
        return $this->state([
            'type' => 'service',
        ]);
    }

    public function pending(): self
    {
        return $this->state([
            'status' => 'pending',
            'approved_by' => null,
        ]);
    }

    public function approved(): self
    {
        return $this->state([
            'status' => 'approved',
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state([
            'status' => 'rejected',
            'approved_by' => User::factory(),
        ]);
    }

    public function received(): self
    {
        return $this->state([
            'status' => 'received',
        ]);
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
}
