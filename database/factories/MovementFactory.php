<?php

namespace Database\Factories;

use App\Enums\MovementStatus;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\OperatingOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movement>
 */
class MovementFactory extends Factory
{
    protected $model = Movement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operating_order_id' => OperatingOrder::factory(),
            'movement_type_id' => MovementType::factory(),
            'vehicle_id' => null,
            'transport_supplier_id' => null,
            'status' => MovementStatus::Pending,
            'from_location' => fake()->randomElement(['Jeddah Airport', 'Makkah Hotel', 'Madinah Hotel']),
            'to_location' => fake()->randomElement(['Makkah Hotel', 'Madinah Hotel', 'Madinah Airport']),
            'movement_at' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'pilgrim_count' => fake()->numberBetween(1, 45),
            'supervisor_name' => fake()->name(),
        ];
    }
}
