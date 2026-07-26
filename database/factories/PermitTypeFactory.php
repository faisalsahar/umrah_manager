<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\PermitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PermitType>
 */
class PermitTypeFactory extends Factory
{
    protected $model = PermitType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'name_ar' => fake()->word(),
            'name_en' => fake()->word(),
            'notes' => null,
        ];
    }
}
