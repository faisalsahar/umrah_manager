<?php

namespace Database\Factories;

use App\Models\ContractAllotment;
use App\Models\HotelContract;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractAllotment>
 */
class ContractAllotmentFactory extends Factory
{
    protected $model = ContractAllotment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hotelContract = HotelContract::factory()->create();

        return [
            'hotel_contract_id' => $hotelContract,
            'room_type_id' => RoomType::factory(['hotel_id' => $hotelContract->hotel_id]),
            'date' => $hotelContract->starts_on,
            'rooms_allotted' => fake()->numberBetween(5, 30),
        ];
    }
}
