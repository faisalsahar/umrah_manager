<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\Organization;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'type' => fake()->randomElement(ServiceType::cases()),
            'name' => fake()->randomElement(['Visa Processing', 'Ziyarat Tour', 'Daily Catering', 'SIM Card']),
            'default_sale_price_cents' => fake()->numberBetween(2000, 20000),
            'currency' => 'SAR',
            'is_active' => true,
        ];
    }
}
