<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\City;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'city_id' => City::factory(),
            'type' => fake()->randomElement(LocationType::cases()),
            'name_ar' => fake()->streetName(),
            'name_en' => fake()->streetName(),
        ];
    }
}
