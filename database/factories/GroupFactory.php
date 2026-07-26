<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $arrival = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'operator_id' => Organization::factory()->operator(),
            'agent_id' => Organization::factory()->agent(),
            'name' => 'Group '.fake()->unique()->numerify('G-####'),
            'visa_type' => fake()->randomElement(['Umrah', 'Visit', 'Transit']),
            'arrival_date' => $arrival,
            'departure_date' => (clone $arrival)->modify('+'.fake()->numberBetween(7, 21).' days'),
        ];
    }
}
