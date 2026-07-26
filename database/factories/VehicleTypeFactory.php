<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    protected $model = VehicleType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $capacity] = fake()->randomElement([
            ['Bus - 49 Seater', 49],
            ['Hiace', 15],
            ['GMC', 8],
            ['Sedan', 4],
        ]);

        return [
            'operator_id' => Organization::factory()->operator(),
            'name' => $name,
            'capacity' => $capacity,
            'is_active' => true,
        ];
    }
}
