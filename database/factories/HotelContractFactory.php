<?php

namespace Database\Factories;

use App\Enums\HotelContractType;
use App\Models\Hotel;
use App\Models\HotelContract;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelContract>
 */
class HotelContractFactory extends Factory
{
    protected $model = HotelContract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('+1 week', '+1 month');

        return [
            'hotel_id' => Hotel::factory(),
            'operator_id' => Organization::factory()->operator(),
            'contract_type' => HotelContractType::Allotment,
            'starts_on' => $startsOn,
            'ends_on' => (clone $startsOn)->modify('+30 days'),
            'release_days' => 14,
            'currency' => 'SAR',
            'is_active' => true,
        ];
    }
}
