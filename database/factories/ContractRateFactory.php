<?php

namespace Database\Factories;

use App\Models\ContractRate;
use App\Models\HotelContract;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractRate>
 */
class ContractRateFactory extends Factory
{
    protected $model = ContractRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hotelContract = HotelContract::factory()->create();

        return [
            'hotel_contract_id' => $hotelContract,
            'room_type_id' => RoomType::factory(['hotel_id' => $hotelContract->hotel_id]),
            'starts_on' => $hotelContract->starts_on,
            'ends_on' => $hotelContract->ends_on,
            'purchase_price_cents' => fake()->numberBetween(8000, 15000),  // per bed, per night
            'sale_price_cents' => fake()->numberBetween(15000, 25000),
        ];
    }
}
