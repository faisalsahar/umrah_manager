<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    protected $model = RoomType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        [$name, $capacity] = fake()->randomElement([
            ['Double', 2],
            ['Triple', 3],
            ['Quad', 4],
            ['Quint', 5],
        ]);

        return [
            'hotel_id' => Hotel::factory(),
            'name' => $name,
            'capacity' => $capacity,
        ];
    }
}
