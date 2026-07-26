<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    protected $model = City::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'name_ar' => fake()->city(),
            'name_en' => fake()->city(),
            'is_port' => true,
            'is_accommodation' => true,
            'has_train' => false,
        ];
    }
}
