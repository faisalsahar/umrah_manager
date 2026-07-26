<?php

namespace Database\Factories;

use App\Enums\MovementCategory;
use App\Models\MovementType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovementType>
 */
class MovementTypeFactory extends Factory
{
    protected $model = MovementType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(MovementCategory::cases());

        return [
            'operator_id' => Organization::factory()->operator(),
            'name_ar' => $category->label(),
            'name_en' => $category->value,
            'category' => $category,
            'is_active' => true,
        ];
    }
}
