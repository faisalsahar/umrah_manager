<?php

namespace Database\Factories;

use App\Enums\HotelCity;
use App\Models\Hotel;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'managed_by_organization_id' => null,
            'name' => fake()->company().' Hotel',
            'city' => fake()->randomElement(HotelCity::cases()),
            'stars' => fake()->numberBetween(3, 5),
            'is_active' => true,
        ];
    }
}
