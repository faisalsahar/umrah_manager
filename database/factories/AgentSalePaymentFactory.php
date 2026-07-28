<?php

namespace Database\Factories;

use App\Enums\AgentSalePaymentDirection;
use App\Models\AgentSale;
use App\Models\AgentSalePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSalePayment>
 */
class AgentSalePaymentFactory extends Factory
{
    protected $model = AgentSalePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_sale_id' => AgentSale::factory(),
            'direction' => AgentSalePaymentDirection::FromCustomer,
            'amount_cents' => fake()->numberBetween(5000, 50000),
            'paid_on' => fake()->dateTimeBetween('-1 month', 'now'),
            'note' => null,
        ];
    }
}
