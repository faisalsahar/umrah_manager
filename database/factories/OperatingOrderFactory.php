<?php

namespace Database\Factories;

use App\Enums\OperatingOrderStatus;
use App\Models\OperatingOrder;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperatingOrder>
 */
class OperatingOrderFactory extends Factory
{
    protected $model = OperatingOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Organization::factory()->operator(),
            'agent_id' => Organization::factory()->agent(),
            'group_id' => null,
            'entered_by_user_id' => null,
            'status' => OperatingOrderStatus::Draft,
            'arrival_date' => fake()->dateTimeBetween('+1 week', '+2 months'),
            'travel_mode' => fake()->randomElement(['SV 799', 'BS 361', 'Overland']),
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => OperatingOrderStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
