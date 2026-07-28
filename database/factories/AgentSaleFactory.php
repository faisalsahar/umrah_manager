<?php

namespace Database\Factories;

use App\Enums\AgentSaleStatus;
use App\Enums\AgentSaleType;
use App\Models\AgentSale;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentSale>
 */
class AgentSaleFactory extends Factory
{
    protected $model = AgentSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = fake()->numberBetween(20000, 80000);

        return [
            'reference_no' => AgentSale::generateReferenceNo(),
            'agent_organization_id' => Organization::factory()->agent(),
            'recorded_by_user_id' => null,
            'sale_type' => AgentSaleType::Ticket,
            'status' => AgentSaleStatus::Issued,
            'customer_name' => fake()->name(),
            'description' => fake()->sentence(4),
            'supplier_reference' => fake()->bothify('PNR-????##'),
            'sale_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'purchase_price_cents' => $purchase,
            'sale_price_cents' => $purchase + fake()->numberBetween(5000, 20000),
            'currency' => 'SAR',
        ];
    }
}
