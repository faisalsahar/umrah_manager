<?php

namespace Database\Factories;

use App\Enums\OrderLineType;
use App\Models\OperatingOrder;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchase = fake()->numberBetween(8000, 15000);

        return [
            'operating_order_id' => OperatingOrder::factory(),
            'line_type' => OrderLineType::Service,
            'quantity' => fake()->numberBetween(1, 10),
            'purchase_price_cents' => $purchase,
            'sale_price_cents' => $purchase + fake()->numberBetween(1000, 5000),
            'currency' => 'SAR',
        ];
    }
}
