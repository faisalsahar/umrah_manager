<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'vehicle_type_id' => VehicleType::factory(),
            'transport_supplier_id' => null,
            'plate_no' => strtoupper(fake()->bothify('### ???')),
            'driver_name' => fake()->name('male'),
            'driver_phone' => fake()->e164PhoneNumber(),
            'is_active' => true,
        ];
    }
}
