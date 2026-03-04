<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'patient_type' => 'clinic',
            'department' => $this->faker->randomElement(['lab', 'radiology', 'pharmacy', 'consultation']),
            'service_name' => $this->faker->sentence(3),
            'total_amount' => $this->faker->numberBetween(500, 5000),
            'discount_amount' => 0,
            'net_amount' => null,
            'prescribing_doctor_id' => User::factory(),
            'referrer_name' => null,
            'referrer_percentage' => null,
            'status' => 'pending',
        ];
    }

    public function clinic(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_type' => 'clinic',
            'patient_id' => Patient::factory(),
        ]);
    }

    public function walkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'patient_type' => 'walk_in',
            'patient_id' => null,
            'referrer_name' => $this->faker->name(),
            'referrer_percentage' => 10.00,
        ]);
    }

    public function lab(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'lab',
        ]);
    }

    public function radiology(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'radiology',
        ]);
    }

    public function pharmacy(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'pharmacy',
        ]);
    }

    public function consultation(): static
    {
        return $this->state(fn (array $attributes) => [
            'department' => 'consultation',
        ]);
    }

    /**
     * Create invoice in paid state with net_amount computed.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'payment_method' => 'cash',
            'paid_at' => now(),
            'net_amount' => ($attributes['total_amount'] ?? 1000) - ($attributes['discount_amount'] ?? 0),
        ]);
    }
}
